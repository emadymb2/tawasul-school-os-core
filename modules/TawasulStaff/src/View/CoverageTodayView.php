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

namespace Tos\Module\TawasulStaff\View;

use TawasulOS\View\Page;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Domain\Staff\StaffCoverageGateway;
use TawasulOS\Domain\FormGroups\FormGroupGateway;

/**
 * CoverageTodayView
 *
 * @version v18
 * @since   v18
 */
class CoverageTodayView
{
    protected $staffCoverageGateway;
    protected $formGroupGateway;
    protected $userGateway;
    protected $tawasulStaffCoverageID;

    public function __construct(StaffCoverageGateway $staffCoverageGateway, FormGroupGateway $formGroupGateway, UserGateway $userGateway)
    {
        $this->staffCoverageGateway = $staffCoverageGateway;
        $this->formGroupGateway = $formGroupGateway;
        $this->userGateway = $userGateway;
    }

    public function setCoverage($tawasulStaffCoverageID)
    {
        $this->tawasulStaffCoverageID = $tawasulStaffCoverageID;

        return $this;
    }

    public function compose(Page $page)
    {
        $coverage = $this->staffCoverageGateway->getByID($this->tawasulStaffCoverageID);

        $page->write('<details class="border  bg-white rounded-b -mt-5 px-4" open>');
        $page->write('<summary class="py-4 text-gray-700 text-sm cursor-pointer">'.__('View Details').'</summary>');

        // Coverage Request
        $requester = $this->userGateway->getByID($coverage['tawasulPersonIDStatus']);
        $page->writeFromTemplate('statusComment.twig.html', [
            'name'    => Format::name($requester['title'], $requester['preferredName'], $requester['surname'], 'Staff', false, true),
            'action'   => __('Requested Coverage'),
            'photo'   => $requester['image_240'],
            'date'    => Format::relativeTime($coverage['timestampStatus']),
            'comment' => $coverage['notesStatus'],
        ]);

        // Attachment
        if (!empty($coverage['attachmentType'])) {
            $page->writeFromTemplate('statusComment.twig.html', [
                'name'       => __('Attachment'),
                'icon'       => 'internalAssessment',
                'tag'        => 'dull',
                'status'     => __($coverage['attachmentType']),
                'attachment' => $coverage['attachmentType'] != 'Text' ? Format::link($coverage['attachmentContent']) : '',
                'html'       => $coverage['attachmentType'] == 'Text' ? $coverage['attachmentContent'] : '',
            ]);
        }

        // Form Group Info
        $formGroups = $this->formGroupGateway->selectFormGroupsByTutor($coverage['tawasulPersonID'])->toDataSet();

        if (count($formGroups) > 0) {
            $table = DataTable::create('todaysCoverageTimetable');

            $table->addColumn('name', __('Form Group'))->context('primary');
            $table->addColumn('spaceName', __('Location'))->context('primary');

            $table->addActionColumn()
                ->addParam('tawasulFormGroupID')
                ->format(function ($values, $actions) {
                    if ($values['attendance'] == 'Y') {
                        $actions->addAction('attendance', __('Take Attendance'))
                            ->setIcon('attendance')
                            ->setURL('/modules/TawasulAttendance/attendance_take_byFormGroup.php');
                    }

                    $actions->addAction('view', __('View Details'))
                        ->setURL('/modules/TawasulFormGroups/formGroups_details.php');
                });

            $page->write($table->render($formGroups).'<br/>');
        }

        // Timetable Info
        $timetable = $this->staffCoverageGateway->selectTimetableRowsByCoverageDate($this->tawasulStaffCoverageID, date('Y-m-d'))->toDataSet();

        if (count($timetable) > 0) {
            $table = DataTable::create('todaysCoverageTimetable');

            $table->addColumn('period', __('Period'));
            $table->addColumn('time', __('Time'))->format(Format::using('timeRange', ['timeStart', 'timeEnd']))->context('primary');
            $table->addColumn('class', __('Class'))->format(Format::using('courseClassName', ['courseNameShort', 'className']))->context('secondary');
            $table->addColumn('spaceName', __('Location'))->context('primary');

            $table->addActionColumn()
                ->addParam('tawasulCourseClassID')
                ->format(function ($values, $actions) {
                    if ($values['attendance'] == 'Y') {
                        $actions->addAction('attendance', __('Take Attendance'))
                            ->setIcon('attendance')
                            ->setURL('/modules/TawasulAttendance/attendance_take_byCourseClass.php');
                    }

                    $actions->addAction('view', __('View Details'))
                        ->setURL('/modules/TawasulDepartments/department_course_class.php');
                });

            $page->write($table->render($timetable).'<br/>');
        }

        $page->write('</details>');
    }
}
