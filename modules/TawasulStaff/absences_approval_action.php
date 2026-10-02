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
use TawasulOS\Domain\Staff\StaffAbsenceGateway;
use Tos\Module\TawasulStaff\View\StaffCard;
use Tos\Module\TawasulStaff\View\AbsenceView;
use Tos\Module\TawasulStaff\Tables\AbsenceDates;
use Tos\Module\TawasulStaff\Tables\CoverageDates;

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/absences_approval_action.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $tawasulStaffAbsenceID = $_GET['tawasulStaffAbsenceID'] ?? '';
    $status = $_GET['status'] ?? '';

    $page->breadcrumbs
        ->add(__('Approve Staff Absences'), 'absences_approval.php')
        ->add(__('Approval'));

    $absence = $container->get(StaffAbsenceGateway::class)->getAbsenceDetailsByID($tawasulStaffAbsenceID);

    if (empty($absence)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    if ($absence['tawasulPersonIDApproval'] != $session->get('tawasulPersonID')) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }
    
    // Staff Card
    $staffCard = $container->get(StaffCard::class);
    $staffCard->setPerson($absence['tawasulPersonID'])->compose($page);

    // Absence Dates
    $table = $container->get(AbsenceDates::class)->create($tawasulStaffAbsenceID, true, false);
    $table->setTitle(__('Absence'));
    $page->write($table->getOutput());

    // Coverage Dates
    if ($absence['coverageRequired'] == 'Y') {
        $table = $container->get(CoverageDates::class)->createFromAbsence($tawasulStaffAbsenceID, $absence['status']);
        $table->setTitle(__('Coverage Request'));
        $page->write($table->getOutput());
    }

    // Absence View Composer
    $absenceView = $container->get(AbsenceView::class);
    $absenceView->setAbsence($tawasulStaffAbsenceID, $session->get('tawasulPersonID'))->compose($page);

    // Approval Form
    $form = Form::create('staffAbsenceApproval', $session->get('absoluteURL').'/modules/TawasulStaff/absences_approval_actionProcess.php');

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulStaffAbsenceID', $tawasulStaffAbsenceID);

    $options = [
        'Approved' => __('Approved'),
        'Declined' => __('Declined'),
    ];
    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')->fromArray($options)->selected($status)->required();

    $row = $form->addRow();
        $row->addLabel('notesApproval', __('Reply'));
        $row->addTextArea('notesApproval')->setRows(3);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
