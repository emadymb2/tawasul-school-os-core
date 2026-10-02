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
use Tos\Module\TawasulStaff\View\StaffCard;
use Tos\Module\TawasulStaff\Tables\CoverageDates;
use TawasulOS\Domain\Staff\StaffAbsenceGateway;
use Tos\Module\TawasulStaff\View\AbsenceView;
use Tos\Module\TawasulStaff\Tables\AbsenceDates;

if (isActionAccessible($guid, $connection2, '/modules/TawasulStaff/absences_view_byPerson.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('View Absences'), 'absences_view_byPerson.php')
        ->add(__('Cancel Absence'));

    $page->return->addReturns([
        'success1' => __('Your request was completed successfully.')
    ]);

    $highestAction = getHighestGroupedAction($guid, '/modules/TawasulStaff/absences_view_byPerson.php', $connection2);
    if (empty($highestAction)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    $tawasulStaffAbsenceID = $_GET['tawasulStaffAbsenceID'] ?? '';
    $tawasulStaffAbsenceID = str_pad($tawasulStaffAbsenceID, 14, 0, STR_PAD_LEFT);

    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);

    if (empty($tawasulStaffAbsenceID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $absence = $staffAbsenceGateway->getAbsenceDetailsByID($tawasulStaffAbsenceID);
    if (empty($absence)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    if ($absence['dateEnd'] < date('Y-m-d')) {
        $page->addError(__('Your request failed because the selected date is not in the future.'));
        return;
    }

    if ($highestAction == 'View Absences_mine' && $absence['tawasulPersonID'] != $session->get('tawasulPersonID')) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    // Staff Card
    $staffCard = $container->get(StaffCard::class);
    $staffCard->setPerson($absence['tawasulPersonID'])->compose($page);

    // Absence Dates
    $table = $container->get(AbsenceDates::class)->create($tawasulStaffAbsenceID, true, false);
    $page->write($table->getOutput());

    // Coverage Dates
    if ($absence['coverageRequired'] == 'Y') {
        $table = $container->get(CoverageDates::class)->createFromAbsence($tawasulStaffAbsenceID, $absence['status']);
        $page->write($table->getOutput());
    }

    // Absence View Composer
    $absenceView = $container->get(AbsenceView::class);
    $absenceView->setAbsence($tawasulStaffAbsenceID, $session->get('tawasulPersonID'))->compose($page);

    // Form
    $form = Form::create('staffAbsence', $session->get('absoluteURL').'/modules/TawasulStaff/absences_view_cancelProcess.php');

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulStaffAbsenceID', $tawasulStaffAbsenceID);

    $form->addRow()->addHeading('Cancel Absence', __('Cancel Absence'));

    $row = $form->addRow();
        $row->addLabel('comment', __('Reply'));
        $row->addTextArea('comment')->setRows(3);

    $row = $form->addRow();
        $row->addSubmit();
    
    echo $form->getOutput();
}
