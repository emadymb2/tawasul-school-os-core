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

if (isActionAccessible($guid, $connection2, '/modules/TawasulSchoolAdmin/inSettings_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
        ->add(__('Individual Needs Settings'), 'inSettings.php')
        ->add(__('Edit Descriptor'));

    //Check if tawasulINDescriptorID specified
    $tawasulINDescriptorID = $_GET['tawasulINDescriptorID'] ?? '';
    if ($tawasulINDescriptorID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        
            $data = array('tawasulINDescriptorID' => $tawasulINDescriptorID);
            $sql = 'SELECT * FROM tawasulINDescriptor WHERE tawasulINDescriptorID=:tawasulINDescriptorID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            $form = Form::create('inDescriptor', $session->get('absoluteURL').'/modules/'.$session->get('module').'/inSettings_editProcess.php?tawasulINDescriptorID='.$tawasulINDescriptorID);
            $form->setFactory(DatabaseFormFactory::create($pdo));

            $form->addHiddenValue('address', $session->get('address'));

            $row = $form->addRow();
                $row->addLabel('name', __('Name'))->description(__('Must be unique.'));
                $row->addTextField('name')->required()->maxLength(50);

            $row = $form->addRow();
                $row->addLabel('nameShort', __('Short Name'))->description(__('Must be unique.'));
                $row->addTextField('nameShort')->required()->maxLength(5);

            $row = $form->addRow();
                $row->addLabel('sequenceNumber', __('Sequence Number'));
                $row->addSequenceNumber('sequenceNumber', 'tawasulINDescriptor', $values['sequenceNumber'])->required()->maxLength(5);

            $row = $form->addRow();
                $row->addLabel('description', __('Description'));
                $row->addTextArea('description')->setRows(8);

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            $form->loadAllValuesFrom($values);

            echo $form->getOutput();
        }
    }
}
