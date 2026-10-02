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

if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/family_manage_edit_deleteChild.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    //Check if tawasulPersonID and tawasulFamilyID specified
    $tawasulFamilyID = $_GET['tawasulFamilyID'] ?? '';
    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
    $search = $_GET['search'] ?? '';
    if ($tawasulPersonID == '' or $tawasulFamilyID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        
            $data = array('tawasulFamilyID' => $tawasulFamilyID, 'tawasulPersonID' => $tawasulPersonID);
            $sql = 'SELECT * FROM tawasulPerson, tawasulFamily, tawasulFamilyChild WHERE tawasulFamily.tawasulFamilyID=tawasulFamilyChild.tawasulFamilyID AND tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulFamily.tawasulFamilyID=:tawasulFamilyID AND tawasulFamilyChild.tawasulPersonID=:tawasulPersonID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $row = $result->fetch();
            $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module')."/family_manage_edit_deleteChildProcess.php?tawasulFamilyID=$tawasulFamilyID&tawasulPersonID=$tawasulPersonID&search=$search");
            echo $form->getOutput();
        }
    }
}
?>
