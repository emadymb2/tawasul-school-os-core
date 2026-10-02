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

use TawasulOS\Forms\Form;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Tables\DataTable;
use TawasulOS\Domain\DataSet;
use TawasulOS\Domain\Staff\StaffAbsenceGateway;
use TawasulOS\Domain\Staff\StaffAbsenceDateGateway;
use TawasulOS\Domain\Staff\StaffAbsenceTypeGateway;
use TawasulOS\Domain\School\SchoolYearGateway;
use Tos\Module\TawasulStaff\Tables\AbsenceFormats;
use Tos\Module\TawasulStaff\Tables\AbsenceCalendar;
use TawasulOS\Domain\System\SettingGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/absences_view_byPerson.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('View Absences'));

    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    $tawasulSchoolYearID = $session->get('tawasulSchoolYearID');

    $settingGateway = $container->get(SettingGateway::class);
    $schoolYearGateway = $container->get(SchoolYearGateway::class);
    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);
    $staffAbsenceTypeGateway = $container->get(StaffAbsenceTypeGateway::class);

    if ($highestAction == 'View Absences_any') {
        $tawasulPersonID = $_GET['tawasulPersonID'] ?? $session->get('tawasulPersonID');

        $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
        $form->setFactory(DatabaseFormFactory::create($pdo));
        $form->setTitle(__('Filter'));
        $form->setClass('noIntBorder w-full');

        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('q', '/modules/TawasulStaff/absences_view_byPerson.php');

        $row = $form->addRow();
            $row->addLabel('tawasulPersonID', __('Person'));
            $row->addSelectStaff('tawasulPersonID')->selected($tawasulPersonID);

        $row = $form->addRow();
            $row->addFooter();
            $row->addSearchSubmit($session);

        echo $form->getOutput();
    } else {
        $tawasulPersonID = $session->get('tawasulPersonID');
    }

    
    $absences = $staffAbsenceDateGateway->selectApprovedAbsenceDatesByPerson($tawasulSchoolYearID, $tawasulPersonID)->fetchGrouped();
    $schoolYear = $schoolYearGateway->getSchoolYearByID($tawasulSchoolYearID);

    $coverageMode = $settingGateway->getSettingByScope('Staff', 'coverageMode');

    // CALENDAR VIEW
    $table = AbsenceCalendar::create($absences, $schoolYear['firstDay'], $schoolYear['lastDay']);
    echo $table->getOutput().'<br/>';

    // COUNT TYPES
    $absenceTypes = $staffAbsenceTypeGateway->selectAllTypes()->fetchAll();
    $types = array_fill_keys(array_column($absenceTypes, 'name'), 0);

    foreach ($absences as $days) {
        foreach ($days as $absence) {
            $types[$absence['type']] += $absence['value'];
        }
    }

    $table = DataTable::create('staffAbsenceTypes');

    foreach ($types as $name => $count) {
        $table->addColumn($name, $name)->context('primary')->width((100 / count($types)).'%');
    }

    echo $table->render(new DataSet([$types]));

    // QUERY
    $criteria = $staffAbsenceGateway->newQueryCriteria(true)
        ->sortBy('date', 'DESC')
        ->filterBy('schoolYear', $tawasulSchoolYearID)
        ->fromPOST();

    $absences = $staffAbsenceGateway->queryAbsencesByPerson($criteria, $tawasulPersonID);

    // Join a set of coverage data per absence
    $absenceIDs = $absences->getColumn('tawasulStaffAbsenceID');
    $coverageData = $staffAbsenceDateGateway->selectDatesByAbsenceWithCoverage($absenceIDs)->fetchGrouped();
    $absences->joinColumn('tawasulStaffAbsenceID', 'coverageList', $coverageData);

    // DATA TABLE
    $table = DataTable::createPaginated('staffAbsences', $criteria);
    $table->setTitle(__('View'));

    $table->modifyRows(function ($absence, $row) {
        if ($absence['status'] == 'Pending Approval') $row->addClass('warning');
        if ($absence['status'] == 'Declined') $row->addClass('dull');
        if ($absence['status'] == 'Cancelled') $row->addClass('dull');
        return $row;
    });

    $table->addMetaData('filterOptions', [
        'schoolYear:'.$tawasulSchoolYearID => __('School Year').': '.__('Current'),
    ]);

    $table->addHeaderAction('add', __('New Absence'))
        ->setURL('/modules/TawasulStaff/absences_add.php')
        ->addParam('tawasulPersonID', $tawasulPersonID)
        ->displayLabel();

    // COLUMNS
    $table->addColumn('date', __('Date'))
        ->format([AbsenceFormats::class, 'dateDetails']);
    
    $table->addColumn('type', __('Type'))
        ->description(__('Reason'))
        ->format([AbsenceFormats::class, 'typeAndReason']);
    
    $table->addColumn('coverage', __('Coverage'))
        ->format([AbsenceFormats::class, 'coverageList']);

    $table->addColumn('timestampCreator', __('Created'))
        ->width('20%')
        ->format([AbsenceFormats::class, 'createdOn']);

    // ACTIONS
    $canManage = isActionAccessible($guid, $connection2, '/modules/TawasulStaff/absences_manage.php');
    $canRequest = isActionAccessible($guid, $connection2, '/modules/TawasulStaff/coverage_request.php');

    $table->addActionColumn()
        ->addParam('tawasulStaffAbsenceID')
        ->addParam('search', $criteria->getSearchText(true))
        ->format(function ($absence, $actions) use ($canManage, $canRequest, $coverageMode) {
            $noApprovalRequired = ($coverageMode == 'Requested' && $absence['status'] == 'Approved') || ($coverageMode == 'Assigned' && $absence['status'] != 'Cancelled');
            if ($canRequest && $noApprovalRequired && $absence['dateEnd'] >= date('Y-m-d')) {
                $actions->addAction('coverage', __('Request Coverage'))
                    ->setIcon('attendance')
                    ->setURL('/modules/TawasulStaff/coverage_request.php');
            }

            $actions->addAction('view', __('View Details'))
                ->isModal(800, 550)
                ->setURL('/modules/TawasulStaff/absences_view_details.php');

            if ($canManage) {
                $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/TawasulStaff/absences_manage_edit.php');

                $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/TawasulStaff/absences_manage_delete.php');
            }
            
            if (($absence['status'] == 'Approved' || $absence['status'] == 'Pending Approval') && date('Y-m-d') <= $absence['dateEnd']) {
                $actions->addAction('cancel', __('Cancel'))
                    ->setIcon('iconCross')
                    ->setURL('/modules/TawasulStaff/absences_view_cancel.php');
            }
        });

    echo $table->render($absences);
}
