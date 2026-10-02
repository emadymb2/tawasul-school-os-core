<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Tos\Module\TawasulStaff\Tables;

use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\Domain\Staff\StaffCoverageDateGateway;
use Tos\Module\TawasulStaff\Tables\AbsenceFormats;
use TawasulOS\Domain\Staff\StaffCoverageGateway;
use TawasulOS\Contracts\Services\Session;
use TawasulOS\Contracts\Database\Connection;
use TawasulOS\Domain\Staff\StaffAbsenceDateGateway;
use TawasulOS\Domain\System\SettingGateway;

/**
 * CoverageDates
 *
 * Reusable DataTable class for displaying the info for coverage dates.
 *
 * @version v18
 * @since   v18
 */
class CoverageDates
{
    protected $session;
    protected $db;
    protected $staffCoverageGateway;
    protected $staffCoverageDateGateway;
    protected $staffAbsenceDateGateway;
    protected $coverageMode;

    public function __construct(Session $session, Connection $db, SettingGateway $settingGateway, StaffCoverageGateway $staffCoverageGateway, StaffCoverageDateGateway $staffCoverageDateGateway, StaffAbsenceDateGateway $staffAbsenceDateGateway)
    {
        $this->session = $session;
        $this->db = $db;
        $this->staffCoverageGateway = $staffCoverageGateway;
        $this->staffCoverageDateGateway = $staffCoverageDateGateway;
        $this->staffAbsenceDateGateway = $staffAbsenceDateGateway;
        $this->coverageMode = $settingGateway->getSettingByScope('Staff', 'coverageMode');
    }

    public function create($tawasulStaffCoverageID)
    {
        $coverage = $this->staffCoverageGateway->getByID($tawasulStaffCoverageID);
        $dates = $this->staffCoverageDateGateway->selectDatesByCoverage($tawasulStaffCoverageID)->toDataSet();

        return $this->createFromDates($coverage['status'], $dates);
    }

    public function createFromAbsence($tawasulStaffAbsenceID, $status)
    {
        $dates = $this->staffAbsenceDateGateway->selectDatesByAbsenceWithCoverage($tawasulStaffAbsenceID, true)->toDataSet();

        return $this->createFromDates($status, $dates);
    }

    protected function createFromDates($status, $dates) {
        $guid = $this->session->get('guid');
        $connection2 = $this->db->getConnection();

        $canManage = isActionAccessible($guid, $connection2, '/modules/TawasulStaff/coverage_manage.php');

        $coverageByTimetable = count(array_filter($dates->toArray(), function($item) {
            return !empty($item['foreignTableID']);
        }));

        if ($coverageByTimetable) {
            $dates->transform(function (&$item) {
                if (empty($item['foreignTableID'])) return;

                $times = $this->staffCoverageDateGateway->getCoverageTimesByForeignTable($item['foreignTable'], $item['foreignTableID'], $item['date']);

                $item['period'] = $times['period'] ?? '';
                $item['contextName'] = $times['contextName'] ?? '';
            });
        }

        $table = DataTable::create('staffCoverageDates')->withData($dates);

        $table->modifyRows(function ($coverage, $row) {
            if (!empty($coverage['status']) && $coverage['status'] == 'Cancelled') $row->addClass('dull');
            return $row;
        });

        $table->addMetaData('blankSlate', __('Coverage is required but has not been requested yet.'));

        $table->addColumn('date', __('Date'))
            ->format(Format::using('dateReadable', 'date'))
            ->formatDetails(function ($coverage) {
                return Format::small(Format::dayOfWeekName($coverage['date']));
            });

        if ($coverageByTimetable) {
            $table->addColumn('period', __('Period'))
                ->description(__('Time'))
                ->formatDetails([AbsenceFormats::class, 'timeDetails']);

            $table->addColumn('contextName', __('Cover'));
        } else {
            $table->addColumn('timeStart', __('Time'))
                  ->format([AbsenceFormats::class, 'timeDetails']);
        }

        if ($canManage && $status != 'Pending Approval') {
            $table->addColumn('value', __('Value'));
        }

        if ($status != 'Requested' && $status != 'Pending Approval') {
            $table->addColumn('coverage', __('Coverage'))
                ->width('20%')
                ->format([AbsenceFormats::class, 'coverage']);
        }

        $table->addColumn('notes', __('Notes'))->format(Format::using('truncate', 'notes', 60));

        // ACTIONS
        $canDelete = count($dates) > 1;

        $table->addActionColumn()
            ->addParam('tawasulStaffCoverageID')
            ->addParam('tawasulStaffCoverageDateID')
            ->addParam('tawasulCourseClassID')
            ->addParam('date')
            ->format(function ($coverage, $actions) use ($canDelete, $canManage, $status) {

                if ($canManage && $this->coverageMode == 'Assigned' && $coverage['absenceStatus'] == 'Approved' && $status != 'Declined' && $status != 'Cancelled') {
                    if (empty($coverage['tawasulPersonIDCoverage'])) {
                        $actions->addAction('assign', __('Assign'))
                            ->setURL('/modules/TawasulStaff/coverage_planner_assign.php')
                            ->setIcon('attendance')
                            ->addClass('mr-1 -mt-px')
                            ->modalWindow(900, 700)
                            ->append('<img src="themes/Default/img/page_new.png" class="w-4 h-4 absolute ml-4 mt-4 pointer-events-none">');
                    } else {
                        $actions->addAction('cancel', __('Unassign'))
                            ->setURL('/modules/TawasulStaff/coverage_planner_unassign.php')
                            ->setIcon('attendance')
                            ->addClass('mr-1 -mt-px')
                            ->modalWindow(650, 250)
                            ->append('<img src="themes/Default/img/iconCross.png" class="w-4 h-4 absolute ml-4 mt-4 pointer-events-none">');
                    }
                }

                if ($canManage && $canDelete) {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/TawasulStaff/coverage_manage_edit_edit.php');
                }

                if ($canManage && $canDelete) {
                    $actions->addAction('deleteInstant', __('Delete'))
                        ->setIcon('garbage')
                        ->isDirect()
                        ->setURL('/modules/TawasulStaff/coverage_manage_edit_deleteProcess.php')
                        ->addConfirmation(__('Are you sure you wish to delete this record?'));
                }

                if ($status != 'Declined' && $status != 'Cancelled' && ($coverage['date'] >= date('Y-m-d'))) {
                    $actions->addAction('cancel', __('Cancel'))
                        ->setIcon('iconCross')
                        ->setURL('/modules/TawasulStaff/coverage_view_cancel.php');
                }
            
            });
        

        return $table;
    }
}
