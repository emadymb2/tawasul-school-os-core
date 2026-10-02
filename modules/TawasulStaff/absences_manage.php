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
use TawasulOS\Tables\DataTable;
use TawasulOS\Services\Format;
use TawasulOS\Domain\Staff\StaffAbsenceGateway;
use TawasulOS\Domain\Staff\StaffAbsenceTypeGateway;
use TawasulOS\Domain\Staff\StaffAbsenceDateGateway;
use Tos\Module\TawasulStaff\Tables\AbsenceFormats;

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/absences_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage Staff Absences'));

    $tawasulSchoolYearID = $session->get('tawasulSchoolYearID');
    $tawasulStaffAbsenceTypeID = $_GET['tawasulStaffAbsenceTypeID'] ?? '';
    $search = $_GET['search'] ?? '';
    $dateStart = $_GET['dateStart'] ?? '';
    $dateEnd = $_GET['dateEnd'] ?? '';

    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceTypeGateway = $container->get(StaffAbsenceTypeGateway::class);
    $staffAbsenceDateGateway = $container->get(StaffAbsenceDateGateway::class);

    $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/TawasulStaff/absences_manage.php');

    $row = $form->addRow();
        $row->addLabel('search', __('Search'));
        $row->addTextField('search')->setValue($search);

    $row = $form->addRow();
        $row->addLabel('dateStart', __('Start Date'));
        $row->addDate('dateStart')->setValue($dateStart);

    $row = $form->addRow();
        $row->addLabel('dateEnd', __('End Date'));
        $row->addDate('dateEnd')->setValue($dateEnd);

    $types = $staffAbsenceTypeGateway->selectAllTypes()->fetchAll();
    $types = array_combine(array_column($types, 'tawasulStaffAbsenceTypeID'), array_column($types, 'name'));
    
    $row = $form->addRow();
        $row->addLabel('tawasulStaffAbsenceTypeID', __('Type'));
        $row->addSelect('tawasulStaffAbsenceTypeID')
            ->fromArray(['' => __('All')])
            ->fromArray($types)
            ->selected($tawasulStaffAbsenceTypeID);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();


    // QUERY
    $criteria = $staffAbsenceGateway->newQueryCriteria(true)
        ->searchBy($staffAbsenceGateway->getSearchableColumns(), $search)
        ->sortBy('date', 'ASC')
        ->filterBy('dateStart', Format::dateConvert($dateStart))
        ->filterBy('dateEnd', Format::dateConvert($dateEnd))
        ->filterBy('type', $tawasulStaffAbsenceTypeID);

    $criteria->filterBy('date', !$criteria->hasFilter() && !$criteria->hasSearchText() ? 'upcoming' : '')
        ->fromPOST();

    $absences = $staffAbsenceGateway->queryAbsencesBySchoolYear($criteria, $tawasulSchoolYearID, true);

    // Join a set of coverage data per absence
    $absenceIDs = $absences->getColumn('tawasulStaffAbsenceID');
    $coverageData = $staffAbsenceDateGateway->selectDatesByAbsenceWithCoverage($absenceIDs)->fetchGrouped();
    $absences->joinColumn('tawasulStaffAbsenceID', 'coverageList', $coverageData);

    // DATA TABLE
    $table = DataTable::createPaginated('staffAbsences', $criteria);
    $table->setTitle(__('View'));

    $table->modifyRows(function ($absence, $row) {
        if ($absence['status'] == 'Pending Approval') $row->addClass('warning');
        if ($absence['status'] == 'Declined') $row->addClass('error');
        if ($absence['status'] == 'Cancelled') $row->addClass('dull');
        return $row;
    });

    if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/report_absences_summary.php')) {
        $table->addHeaderAction('view', __('View'))
            ->setIcon('planner')
            ->setURL('/modules/TawasulStaff/report_absences_summary.php')
            ->displayLabel();
    }

    $table->addHeaderAction('add', __('New Absence'))
        ->setURL('/modules/TawasulStaff/absences_add.php')
        ->addParam('tawasulPersonID', '')
        ->addParam('date', $dateStart)
        ->displayLabel();
    
    $table->addMetaData('filterOptions', [
        'date:upcoming'    => __('Upcoming'),
        'date:today'       => __('Today'),
        'date:past'        => __('Past'),
        
        'status:pending approval' => __('Status').': '.__('Pending Approval'),
        'status:approved'         => __('Status').': '.__('Approved'),
        'status:declined'         => __('Status').': '.__('Declined'),
        'coverage:requested'      => __('Coverage').': '.__('Requested'),
        'coverage:accepted'       => __('Coverage').': '.__('Accepted'),
    ]);

    // COLUMNS
    $table->addColumn('fullName', __('Name'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($absence) {
            $text = Format::name($absence['title'], $absence['preferredName'], $absence['surname'], 'Staff', false, true);
            $url = './index.php?q=/modules/TawasulStaff/absences_view_byPerson.php&tawasulPersonID='.$absence['tawasulPersonID'];

            return Format::link($url, $text);
        });

    $table->addColumn('date', __('Date'))
        ->width('18%')
        ->format([AbsenceFormats::class, 'dateDetails']);

    $table->addColumn('type', __('Type'))
        ->description(__('Reason'))
        ->format([AbsenceFormats::class, 'typeAndReason']);

    $table->addColumn('coverage', __('Coverage'))
        ->sortable('coverageRequired')
        ->format([AbsenceFormats::class, 'coverageList']);

    $table->addColumn('timestampCreator', __('Created'))
        ->format([AbsenceFormats::class, 'createdOn']);

    // ACTIONS
    $table->addActionColumn()
        ->addParam('search', $criteria->getSearchText(true))
        ->addParam('tawasulStaffAbsenceID')
        ->format(function ($absence, $actions) {
            $actions->addAction('view', __('View Details'))
                ->isModal(800, 550)
                ->setURL('/modules/TawasulStaff/absences_view_details.php');

            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/TawasulStaff/absences_manage_edit.php');

            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/TawasulStaff/absences_manage_delete.php');
        });

    echo $table->render($absences);
}
