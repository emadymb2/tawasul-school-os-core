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
use TawasulOS\Services\Format;

$tawasulLibraryItemEventID = trim($_GET['tawasulLibraryItemEventID']) ?? '';
$tawasulLibraryItemID = trim($_GET['tawasulLibraryItemID']) ?? '';
$lendingAction = $_REQUEST['lendingAction'] ?? '';

$page->breadcrumbs
    ->add(__('Lending & Activity Log'), 'library_lending.php')
    ->add(__('View Item'), 'library_lending_item.php', ['tawasulLibraryItemID' => $tawasulLibraryItemID])
    ->add(__('Return Item'));

$name = $_GET['name'] ?? '';
$tawasulLibraryTypeID = $_GET['tawasulLibraryTypeID'] ?? '';
$tawasulSpaceID = $_GET['tawasulSpaceID'] ?? '';
$status = $_GET['status'] ?? '';

if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/library_lending_item_return.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Check if tawasulLibraryItemEventID specified
    if (empty($tawasulLibraryItemEventID) or empty($tawasulLibraryItemID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $data = array('tawasulLibraryItemID' => $tawasulLibraryItemID, 'tawasulLibraryItemEventID' => $tawasulLibraryItemEventID);
            $sql = 'SELECT tawasulLibraryItemEvent.*, tawasulLibraryItem.name AS name, tawasulLibraryItem.id FROM tawasulLibraryItem JOIN tawasulLibraryItemEvent ON (tawasulLibraryItem.tawasulLibraryItemID=tawasulLibraryItemEvent.tawasulLibraryItemID) WHERE tawasulLibraryItemEvent.tawasulLibraryItemID=:tawasulLibraryItemID AND tawasulLibraryItemEvent.tawasulLibraryItemEventID=:tawasulLibraryItemEventID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/library_lending_item_returnProcess.php?tawasulLibraryItemEventID=$tawasulLibraryItemEventID&tawasulLibraryItemID=$tawasulLibraryItemID&name=$name&tawasulLibraryTypeID=$tawasulLibraryTypeID&tawasulSpaceID=$tawasulSpaceID&status=$status");
            $form->setFactory(DatabaseFormFactory::create($pdo));

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulPersonIDStudent', $_REQUEST['tawasulPersonIDStudent'] ?? '');
            $form->addHiddenValue('lendingAction', $lendingAction == 'SignOut'? 'Return' : $lendingAction);
            
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

            $actions = array(
                'Reserve' => __('Reserve'),
                'Decommission' => __('Decommission'),
                'Repair' => __('Repair')
            );

            $row = $form->addRow();
                $row->addLabel('returnAction', __('Action'));
                $row->addSelect('returnAction')->fromArray($actions)->selected($values['returnAction'])->placeholder();

            //USER SELECT
            $people = array();

            $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'date' => date('Y-m-d'));
            $sql = "SELECT tawasulPerson.tawasulPersonID, preferredName, surname, username, tawasulFormGroup.name AS formGroupName
                FROM tawasulPerson
                    JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                    JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                WHERE status='Full'
                    AND (dateStart IS NULL OR dateStart<=:date)
                    AND (dateEnd IS NULL  OR dateEnd>=:date)
                    AND tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID
                ORDER BY name, surname, preferredName";
            $result = $pdo->executeQuery($data, $sql);

            if ($result->rowCount() > 0) {
                $people['--'.__('Students By Form Group').'--'] = array_reduce($result->fetchAll(), function ($group, $item) {
                    $group[$item['tawasulPersonID']] = $item['formGroupName'].' - '.Format::name('', htmlPrep($item['preferredName']), htmlPrep($item['surname']), 'Student', true).' ('.$item['username'].')';
                    return $group;
                }, array());
            }

            $sql = "SELECT tawasulPersonID, surname, preferredName, status, username FROM tawasulPerson WHERE status='Full' OR status='Expected' ORDER BY surname, preferredName";
            $result = $pdo->executeQuery(array(), $sql);

            if ($result->rowCount() > 0) {
                $people['--'.__('All Users').'--'] = array_reduce($result->fetchAll(), function($group, $item) {
                    $expected = ($item['status'] == 'Expected')? '('.__('Expected').')' : '';
                    $group[$item['tawasulPersonID']] = Format::name('', htmlPrep($item['preferredName']), htmlPrep($item['surname']), 'Student', true).' ('.$item['username'].')'.$expected;
                    return $group;
                }, array());
            }

            $row = $form->addRow();
                $row->addLabel('tawasulPersonIDReturnAction', __('Responsible User'))->description(__('Who will be responsible for the future status?'));
                $row->addSelectUsers('tawasulPersonIDReturnAction', $session->get('tawasulSchoolYearID'), ['includeStudents' => true, 'includeStaff' => true])->placeholder();

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();
        }
    }
}
