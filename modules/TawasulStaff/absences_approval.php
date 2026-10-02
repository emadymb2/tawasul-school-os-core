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

use TawasulOS\Tables\DataTable;
use TawasulOS\Services\Format;
use TawasulOS\Domain\Staff\StaffAbsenceGateway;
use Tos\Module\TawasulStaff\Tables\AbsenceFormats;

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/absences_approval.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Approve Staff Absences'));

    $tawasulSchoolYearID = $session->get('tawasulSchoolYearID');
    $tawasulStaffAbsenceTypeID = $_GET['tawasulStaffAbsenceTypeID'] ?? '';
    $search = $_GET['search'] ?? '';
    $dateStart = $_GET['dateStart'] ?? '';
    $dateEnd = $_GET['dateEnd'] ?? '';

    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);

    // QUERY
    $criteria = $staffAbsenceGateway->newQueryCriteria(true)
        ->searchBy($staffAbsenceGateway->getSearchableColumns(), $search)
        ->sortBy('status', 'ASC')
        ->fromPOST();

    $absences = $staffAbsenceGateway->queryAbsencesByApprover($criteria, $session->get('tawasulPersonID'));

    // DATA TABLE
    $table = DataTable::createPaginated('staffAbsences', $criteria);
    $table->setTitle(__('View'));

    $table->modifyRows(function ($absence, $row) {
        if ($absence['status'] == 'Approved') $row->addClass('current');
        if ($absence['status'] == 'Declined') $row->addClass('error');
        return $row;
    });
    
    $table->addMetaData('filterOptions', [
        'date:upcoming'           => __('Upcoming'),
        'date:today'              => __('Today'),
        'date:past'               => __('Past'),
        'status:pending approval' => __('Status').': '.__('Pending Approval'),
        'status:approved'         => __('Status').': '.__('Approved'),
        'status:declined'         => __('Status').': '.__('Declined'),
    ]);

    // COLUMNS
    $table->addColumn('fullName', __('Name'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($absence) use ($session) {
            $text = Format::name($absence['title'], $absence['preferredName'], $absence['surname'], 'Staff', false, true);
            $url = $session->get('absoluteURL').'/index.php?q=/modules/TawasulStaff/absences_view_byPerson.php&tawasulPersonID='.$absence['tawasulPersonID'];

            return Format::link($url, $text);
        });

    $table->addColumn('date', __('Date'))
        ->width('18%')
        ->format([AbsenceFormats::class, 'dateDetails']);

    $table->addColumn('type', __('Type'))
        ->description(__('Reason'))
        ->format([AbsenceFormats::class, 'typeAndReason']);

    $table->addColumn('coverageRequired', __('Cover Required'))
        ->width('12%')
        ->format(Format::using('yesNo', 'coverageRequired'));

    $table->addColumn('timestampCreator', __('Created'))
        ->format([AbsenceFormats::class, 'createdOn']);

    // ACTIONS
    $table->addActionColumn()
        ->addParam('tawasulStaffAbsenceID')
        ->format(function ($absence, $actions) {
            $actions->addAction('view', __('View Details'))
                ->isModal(800, 550)
                ->setURL('/modules/TawasulStaff/absences_view_details.php');

            if ($absence['status'] == 'Pending Approval') {
                $actions->addAction('approve', __('Approve'))
                    ->setIcon('iconTick')
                    ->addParam('status', 'Approved')
                    ->setURL('/modules/TawasulStaff/absences_approval_action.php');

                $actions->addAction('decline', __('Decline'))
                    ->setIcon('iconCross')
                    ->addParam('status', 'Declined')
                    ->setURL('/modules/TawasulStaff/absences_approval_action.php');
            }
        });

    echo $table->render($absences);
}
