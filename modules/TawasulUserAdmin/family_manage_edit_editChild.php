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
use TawasulOS\Services\Format;

if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/family_manage_edit_editChild.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $urlParams = ['tawasulFamilyID' => $_GET['tawasulFamilyID']];
    
    $page->breadcrumbs
        ->add(__('Manage Families'), 'family_manage.php')
        ->add(__('Edit Family'), 'family_manage_edit.php', $urlParams)
        ->add(__('Edit Child'));  

    //Check if tawasulPersonID and tawasulFamilyID specified
    $tawasulFamilyID = $_GET['tawasulFamilyID'] ?? '';
    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
    $search = $_GET['search'] ?? '';
    if ($tawasulPersonID == '' or $tawasulFamilyID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        
            $data = array('tawasulFamilyID' => $tawasulFamilyID, 'tawasulPersonID' => $tawasulPersonID);
            $sql = "SELECT * FROM tawasulPerson, tawasulFamily, tawasulFamilyChild WHERE tawasulFamily.tawasulFamilyID=tawasulFamilyChild.tawasulFamilyID AND tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulFamily.tawasulFamilyID=:tawasulFamilyID AND tawasulFamilyChild.tawasulPersonID=:tawasulPersonID AND (tawasulPerson.status='Full' OR tawasulPerson.status='Expected')";
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/family_manage_edit_editChildProcess.php?tawasulPersonID=$tawasulPersonID&tawasulFamilyID=$tawasulFamilyID&search=$search");

            $form->addHiddenValue('address', $session->get('address'));
            
            if ($search != '') {
                $params = [
                    "search" => $search,
                    "tawasulFamilyID" => $tawasulFamilyID
                ];
                $form->addHeaderAction('back', __('Back'))
                    ->setURL('/modules/TawasulUserAdmin/family_manage_edit.php')
                    ->addParams($params);
            }

            $form->addRow()->addHeading('Edit Child', __('Edit Child'));

            $row = $form->addRow();
                $row->addLabel('child', __('Childs\'s Name'));
                $row->addTextField('child')->setValue(Format::name(htmlPrep($values['title']), htmlPrep($values['preferredName']), htmlPrep($values['surname']), 'Parent'))->required()->readonly();

            $row = $form->addRow();
                $row->addLabel('comment', __('Comment'))->description(__('Data displayed in full Student Profile'));
                $row->addTextArea('comment')->setRows(8);

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            $form->loadAllValuesFrom($values);

            echo $form->getOutput();
        }
    }
}
?>
