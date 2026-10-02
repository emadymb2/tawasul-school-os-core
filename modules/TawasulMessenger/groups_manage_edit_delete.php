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

use TawasulOS\Forms\Prefab\DeleteForm;
use TawasulOS\Domain\Messenger\GroupGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/groups_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulGroupID = (isset($_GET['tawasulGroupID']))? $_GET['tawasulGroupID'] : null;
    $tawasulPersonID = (isset($_GET['tawasulPersonID']))? $_GET['tawasulPersonID'] : null;

    if ($tawasulGroupID == '' || $tawasulPersonID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        $groupGateway = $container->get(GroupGateway::class);

        $highestAction = getHighestGroupedAction($guid, '/modules/TawasulMessenger/groups_manage.php', $connection2);
        if ($highestAction == 'Manage Groups_all') {
            $result = $groupGateway->selectGroupByID($tawasulGroupID);
        } else {
            $result = $groupGateway->selectGroupByIDAndOwner($tawasulGroupID, $session->get('tawasulPersonID'));
        }

        if ($result->isEmpty()) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $result = $groupGateway->selectGroupPersonByID($tawasulGroupID, $tawasulPersonID);

            if ($result->isEmpty()) {
                $page->addError(__('The specified record cannot be found.'));
            } else {
                $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module')."/groups_manage_edit_deleteProcess.php");
                $form->addHiddenValue('tawasulGroupID', $tawasulGroupID);
                $form->addHiddenValue('tawasulPersonID', $tawasulPersonID);
                echo $form->getOutput();
            }
        }
    }
}
?>
