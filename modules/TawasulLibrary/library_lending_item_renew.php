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

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\Form;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Services\Format;

$tawasulLibraryItemEventID = trim($_GET['tawasulLibraryItemEventID']) ?? '';
$tawasulLibraryItemID = trim($_GET['tawasulLibraryItemID']) ?? '';

$page->breadcrumbs
    ->add(__('Lending & Activity Log'), 'library_lending.php')
    ->add(__('View Item'), 'library_lending_item.php', ['tawasulLibraryItemID' => $tawasulLibraryItemID])
    ->add(__('Renew Item'));

$name = $_GET['name'] ?? '';
$tawasulLibraryTypeID = $_GET['tawasulLibraryTypeID'] ?? '';
$tawasulSpaceID = $_GET['tawasulSpaceID'] ?? '';
$status = $_GET['status'] ?? '';

if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/library_lending_item_renew.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // check if school year specified
    if (empty($tawasulLibraryItemEventID) or empty($tawasulLibraryItemID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $data = array('tawasulLibraryItemID' => $tawasulLibraryItemID, 'tawasulLibraryItemEventID' => $tawasulLibraryItemEventID);
            $sql = 'SELECT tawasulLibraryItemEvent.*, tawasulLibraryItem.name AS name, tawasulLibraryItem.id, tawasulPersonID, surname, preferredName
                FROM tawasulLibraryItem
                    JOIN tawasulLibraryItemEvent ON (tawasulLibraryItem.tawasulLibraryItemID=tawasulLibraryItemEvent.tawasulLibraryItemID)
                    JOIN tawasulPerson ON (tawasulLibraryItemEvent.tawasulPersonIDStatusResponsible=tawasulPerson.tawasulPersonID)
                WHERE tawasulLibraryItemEvent.tawasulLibraryItemID=:tawasulLibraryItemID
                    AND tawasulLibraryItemEvent.tawasulLibraryItemEventID=:tawasulLibraryItemEventID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/library_lending_item_renewProcess.php?tawasulLibraryItemEventID=$tawasulLibraryItemEventID&tawasulLibraryItemID=$tawasulLibraryItemID&name=$name&tawasulLibraryTypeID=$tawasulLibraryTypeID&tawasulSpaceID=$tawasulSpaceID&status=$status");
            $form->setFactory(DatabaseFormFactory::create($pdo));

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulPersonIDStudent', $_REQUEST['tawasulPersonIDStudent'] ?? '');
            $form->addHiddenValue('lendingAction', $_REQUEST['lendingAction'] ?? '');
            
            if (!empty($name) or !empty($tawasulLibraryTypeID) or !empty($tawasulSpaceID) or !empty($status)) {
               $params = [
                    "tawasulLibraryItemEventID" => $tawasulLibraryItemEventID,
                    "tawasulLibraryItemID" => $tawasulLibraryItemID,
                    "name" => $name,
                    "tawasulLibraryTypeID" => $tawasulLibraryTypeID,
                    "tawasulSpaceID" => $tawasulSpaceID,
                    "status" => $status
                ];
                $form->addHeaderAction('back', __('Back'))
                    ->setURL('/modules/TawasulLibrary/library_lending_item.php')
                    ->addParams($params);
            }

            $form->addRow()->addHeading('Item Details', __('Item Details'));

            $row = $form->addRow();
                $row->addLabel('id', __('ID'));
                $row->addTextField('id')->setValue($values['id'])->readonly()->required();

            $row = $form->addRow();
                $row->addLabel('name', __('Name'));
                $row->addTextField('name')->setValue($values['name'])->readonly()->required();

            $row = $form->addRow();
                $row->addLabel('statusCurrent', __('Current Status'));
                $row->addTextField('statusCurrent')->setValue(__($values['status']))->readonly()->required();

            $row = $form->addRow()->addHeading('On Return', __('On Return'));
                $row->append(__('The new status will be set to "Returned" unless the fields below are completed:'));

            $form->addHiddenValue('tawasulPersonIDStatusResponsible', $values['tawasulPersonIDStatusResponsible']);
            $row = $form->addRow();
                $row->addLabel('tawasulPersonIDStatusResponsiblename', __('Responsible User'));
                $row->addTextField('tawasulPersonIDStatusResponsiblename')->setValue(Format::name('', htmlPrep($values['preferredName']), htmlPrep($values['surname']), 'Student', true))->readonly()->required();

            $loanLength = $container->get(SettingGateway::class)->getSettingByScope('Library', 'defaultLoanLength');
            $loanLength = (is_numeric($loanLength) == false or $loanLength < 0) ? 7 : $loanLength ;
            $row = $form->addRow();
                $row->addLabel('returnExpected', __('Expected Return Date'))->description(sprintf(__('Default renew length is today plus %1$s day(s)'), $loanLength));
                $row->addDate('returnExpected')->setValue(date($session->get('i18n')['dateFormatPHP'], time() + ($loanLength * 60 * 60 * 24)))->required()->addTodayButton()->addDayOffsetButtons([1, 7]);

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();
        }
    }
}
