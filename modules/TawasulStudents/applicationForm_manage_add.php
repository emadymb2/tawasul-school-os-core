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

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

//Module includes from User Admin (for custom fields)
include './modules/TawasulUserAdmin/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/applicationForm_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';

    $page->breadcrumbs
        ->add(__('Manage Applications'), 'applicationForm_manage.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID])
        ->add(__('Add Form'));

    $form = Form::create('addApplication', $session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module').'/applicationForm.php');

    $form->addHiddenValue('address', $session->get('address'));

    $types = array(
        'blank' => __('Blank Application'),
        'family' => __('Current').' '.__('Family'),
        'person' => __('Current').' '.__('User'),
    );

    $row = $form->addRow();
        $row->addLabel('applicationType', __('Type'));
        $row->addSelect('applicationType')->fromArray($types)->required();

    $sql = "SELECT tawasulFamily.tawasulFamilyID as value, CONCAT(tawasulFamily.name, ' (', GROUP_CONCAT(DISTINCT CONCAT(tawasulPerson.preferredName, ' ', tawasulPerson.surname) SEPARATOR ', '), ')') as name FROM tawasulFamily
        JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
        JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID)
        GROUP BY tawasulFamily.tawasulFamilyID
        HAVING count(DISTINCT tawasulFamilyAdult.tawasulPersonID) > 0
        ORDER BY name";

    $form->toggleVisibilityByClass('typeFamily')->onSelect('applicationType')->when('family');

    $row = $form->addRow()->addClass('typeFamily');
        $row->addLabel('tawasulFamilyID', __('Family'));
        $row->addSelect('tawasulFamilyID')->fromQuery($pdo, $sql)->required();

    $sql = "SELECT tawasulPersonID as value, CONCAT(tawasulPerson.surname, ', ', tawasulPerson.preferredName, ' (', tawasulRole.category, ': ', tawasulPerson.username, ')') as name
            FROM tawasulPerson
            JOIN tawasulRole ON (tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary)
            WHERE tawasulRole.category <> 'Student'
            AND tawasulPerson.status='Full'
            ORDER BY tawasulPerson.surname, tawasulPerson.preferredname";

    $form->toggleVisibilityByClass('typePerson')->onSelect('applicationType')->when('person');

    $row = $form->addRow()->addClass('typePerson');
        $row->addLabel('tawasulPersonID', __('Person'));
        $row->addSelect('tawasulPersonID')->fromQuery($pdo, $sql)->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
