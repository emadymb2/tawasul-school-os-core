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

if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/userSettings.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
        ->add(__('User Settings'), 'userSettings.php')
        ->add(__('Edit Username Format'));

    $tawasulUsernameFormatID = isset($_GET['tawasulUsernameFormatID'])? $_GET['tawasulUsernameFormatID'] : '';

    if (empty($tawasulUsernameFormatID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $data = array('tawasulUsernameFormatID' => $tawasulUsernameFormatID);
    $sql = "SELECT * FROM tawasulUsernameFormat WHERE tawasulUsernameFormatID=:tawasulUsernameFormatID";
    $result = $pdo->executeQuery($data, $sql);

    if ($result->rowCount() == 0) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $values = $result->fetch();
    $values['tawasulRoleIDList'] = explode(',', $values['tawasulRoleIDList']);
    $values['numericValue'] = str_pad($values['numericValue'], $values['numericSize'], '0', STR_PAD_LEFT);

    $form = Form::create('usernameFormat', $session->get('absoluteURL').'/modules/'.$session->get('module').'/userSettings_usernameFormat_editProcess.php');

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulUsernameFormatID', $tawasulUsernameFormatID);

    $data = array('tawasulUsernameFormatID' => $tawasulUsernameFormatID);
    $sql = "SELECT tawasulRole.tawasulRoleID as value, tawasulRole.name FROM tawasulRole LEFT JOIN tawasulUsernameFormat ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulUsernameFormat.tawasulRoleIDList) AND tawasulUsernameFormatID<>:tawasulUsernameFormatID) WHERE tawasulUsernameFormatID IS NULL ORDER BY tawasulRole.name";
    $roles = $pdo->select($sql, $data)->fetchKeyPair();

    $row = $form->addRow();
        $row->addLabel('format', __('Username Format'))->description(__('How should usernames be formated? Choose from [preferredName], [firstName], [surname].').'<br>'.__('Use a colon to limit the number of letters, for example [preferredName:1] will use the first initial.'));
        $row->addTextField('format')->required();

    $row = $form->addRow();
        $row->addLabel('tawasulRoleIDList', __('Roles'));
        $row->addSelect('tawasulRoleIDList')
            ->required()
            ->selectMultiple()
            ->setSize(4)
            ->fromArray($roles)
            ->readOnly(isset($roles['003']));

    $row = $form->addRow();
        $row->addLabel('isDefault', __('Is Default?'));
        $row->addYesNo('isDefault');

    $row = $form->addRow();
        $row->addLabel('isNumeric', __('Numeric?'))->description(__('Enables the format [number] to insert a numeric value into your username.'));
        $row->addYesNo('isNumeric');

    $form->toggleVisibilityByClass('numericValueSettings')->onSelect('isNumeric')->when('Y');

    $row = $form->addRow()->addClass('numericValueSettings');
        $row->addLabel('numericValue', __('Starting Value'))->description(__('Each time a username is generated this value will increase by the increment defined below.'));
        $row->addTextField('numericValue')->required()->maxLength(12);

    $row = $form->addRow()->addClass('numericValueSettings');
        $row->addLabel('numericSize', __('Number of Digits'));
        $row->addNumber('numericSize')->required()->minimum(0)->maximum(12);

    $row = $form->addRow()->addClass('numericValueSettings');
        $row->addLabel('numericIncrement', __('Increment By'));
        $row->addNumber('numericIncrement')->required()->minimum(0)->maximum(100);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    $form->loadAllValuesFrom($values);

    echo $form->getOutput();
}
