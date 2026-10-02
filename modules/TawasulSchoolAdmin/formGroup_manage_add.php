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
use TawasulOS\Domain\School\SchoolYearGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulSchoolAdmin/formGroup_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? '';

    $page->breadcrumbs
        ->add(__('Manage Form Groups'), 'formGroup_manage.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID])
        ->add(__('Add Form Group'));

    $editLink = '';
    if (isset($_GET['editID'])) {
        $editLink = $session->get('absoluteURL').'/index.php?q=/modules/TawasulSchoolAdmin/formGroup_manage_edit.php&tawasulFormGroupID='.$_GET['editID'].'&tawasulSchoolYearID='.$_GET['tawasulSchoolYearID'];
    }
    $page->return->setEditLink($editLink);

    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    if ($tawasulSchoolYearID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        
            $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
            $sql = 'SELECT name as schoolYearName FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record does not exist.'));
        } else {
            $values = $result->fetch();

            $form = Form::create('formGroupAdd', $session->get('absoluteURL').'/modules/'.$session->get('module').'/formGroup_manage_addProcess.php');
            $form->setFactory(DatabaseFormFactory::create($pdo));

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

            $row = $form->addRow();
                $row->addLabel('schoolYearName', __('School Year'));
                $row->addTextField('schoolYearName')->readonly()->setValue($values['schoolYearName']);

            $row = $form->addRow();
                $row->addLabel('name', __('Name'))->description(__('Needs to be unique in school year.'));
                $row->addTextField('name')->required()->maxLength(20);

            $row = $form->addRow();
                $row->addLabel('nameShort', __('Short Name'))->description(__('Needs to be unique in school year.'));
                $row->addTextField('nameShort')->required()->maxLength(8);

            $row = $form->addRow();
                $row->addLabel('tutors', __('Tutors'))->description(__('Up to 3 per form group. The first-listed will be marked as "Main Tutor".'));
                $column = $row->addColumn()->addClass('stacked');
                $column->addSelectStaff('tawasulPersonIDTutor')->placeholder()->photo(false);
                $column->addSelectStaff('tawasulPersonIDTutor2')->placeholder()->photo(false);
                $column->addSelectStaff('tawasulPersonIDTutor3')->placeholder()->photo(false);

            $row = $form->addRow();
                $row->addLabel('EAs', __('Educational Assistant'))->description(__('Up to 3 per form group.'));
                $column = $row->addColumn()->addClass('stacked');
                $column->addSelectStaff('tawasulPersonIDEA')->placeholder()->photo(false);
                $column->addSelectStaff('tawasulPersonIDEA2')->placeholder()->photo(false);
                $column->addSelectStaff('tawasulPersonIDEA3')->placeholder()->photo(false);

            $row = $form->addRow();
                $row->addLabel('tawasulSpaceID', __('Location'));
                $row->addSelectSpace('tawasulSpaceID');

            $nextYear = $container->get(SchoolYearGateway::class)->getNextSchoolYearByID($tawasulSchoolYearID);
            $row = $form->addRow();
                $row->addLabel('tawasulFormGroupIDNext', __('Next Form Group'))->description(__('Sets student progression on rollover.'));
                if (empty($nextYear)) {
                    $row->addAlert(__('The next school year cannot be determined, so this value cannot be set.'));
                } else {
                    $row->addSelectFormGroup('tawasulFormGroupIDNext', $nextYear['tawasulSchoolYearID']);
                }

            $row = $form->addRow();
                $row->addLabel('attendance', __('Track Attendance?'))->description(__('Should this class allow attendance to be taken?'));
                $row->addYesNo('attendance');

            $row = $form->addRow();
                $row->addLabel('website', __('Website'))->description(__('Include http://'));
                $row->addURL('website')->maxLength(255);

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();
        }
    }
}

