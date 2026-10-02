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

use TawasulOS\Http\Url;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\School\SchoolYearGateway;
use TawasulOS\Domain\Timetable\CourseSyncGateway;
use TawasulOS\Forms\CustomFieldHandler;

if (isActionAccessible($guid, $connection2, '/modules/TawasulAdmissions/studentEnrolment_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $tawasulStudentEnrolmentID = $_GET['tawasulStudentEnrolmentID'] ?? '';
    $search = $_GET['search'] ?? '';

    $page->breadcrumbs
        ->add(__('Student Enrolment'), 'studentEnrolment_manage.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID])
        ->add(__('Edit Student Enrolment'));

    //Check if tawasulStudentEnrolmentID and tawasulSchoolYearID specified
    if ($tawasulStudentEnrolmentID == '' or $tawasulSchoolYearID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

        $studentGateway = $container->get(StudentGateway::class);
        $enrollment = $studentGateway->getByID($tawasulStudentEnrolmentID);
        if (empty($enrollment)) {
            $page->addError(__('The specified record cannot be found.'));
            return;
        }

        $values = $container->get(StudentGateway::class)->selectActiveStudentByPerson($tawasulSchoolYearID, $enrollment['tawasulPersonID'], false)->fetch();
        if (empty($values)) {
            $page->addError(__('The specified record cannot be found.'));
            return;
        }

        if ($search != '') {
             $params = [
                "search" => $search,
                "tawasulSchoolYearID" => $tawasulSchoolYearID
            ];
            $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulAdmissions', 'studentEnrolment_manage.php')->withQueryParams($params));
        }

        $form = Form::create('studentEnrolmentAdd', $session->get('absoluteURL').'/modules/'.$session->get('module')."/studentEnrolment_manage_editProcess.php?tawasulSchoolYearID=$tawasulSchoolYearID&search=$search");
        $form->setFactory(DatabaseFormFactory::create($pdo));

        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('tawasulStudentEnrolmentID', $tawasulStudentEnrolmentID);
        $form->addHiddenValue('tawasulPersonID', $values['tawasulPersonID']);
        $form->addHiddenValue('tawasulFormGroupIDOriginal', $values['tawasulFormGroupID']);
        $form->addHiddenValue('formGroupOriginalNameShort', $values['formGroup']);

        $schoolYear = $container->get(SchoolYearGateway::class)->getByID($tawasulSchoolYearID, ['name']);
        $schoolYearName = $schoolYear['name'] ?? $session->get('tawasulSchoolYearName');

        $row = $form->addRow()->addHeading('Basic Information', __('Basic Information'));

        $row = $form->addRow();
            $row->addLabel('yearName', __('School Year'));
            $row->addTextField('yearName')->readOnly()->maxLength(20)->setValue($schoolYearName);

        $row = $form->addRow();
            $row->addLabel('studentName', __('Student'));
            $row->addTextField('studentName')->readOnly()->setValue(Format::name('', $values['preferredName'], $values['surname'], 'Student', true));

        $row = $form->addRow();
            $row->addLabel('tawasulYearGroupID', __('Year Group'));
            $row->addSelectYearGroup('tawasulYearGroupID')->required();

        $row = $form->addRow();
            $row->addLabel('tawasulFormGroupID', __('Form Group'));
            $row->addSelectFormGroup('tawasulFormGroupID', $tawasulSchoolYearID)->required();

        $row = $form->addRow();
            $row->addLabel('rollOrder', __('Roll Order'));
            $row->addNumber('rollOrder')->maxLength(2)->setValue($enrollment['rollOrder']);

        // Check to see if any class mappings exists -- otherwise this feature is inactive, hide it
        $classMapCount = $container->get(CourseSyncGateway::class)->countAll();
        if ($classMapCount > 0) {
            $autoEnrolDefault = $container->get(SettingGateway::class)->getSettingByScope('Timetable Admin', 'autoEnrolCourses');
            $row = $form->addRow();
                $row->addLabel('autoEnrolStudent', __('Auto-Enrol Courses?'))
                    ->description(__('Should this student be automatically enrolled in courses for their Form Group?'))
                    ->description(__('This will replace any auto-enrolled courses if the student Form Group has changed.'));
                $row->addYesNo('autoEnrolStudent')->selected($autoEnrolDefault);
        }

        $schoolHistory = '';

        if ($values['dateStart'] != '') {
            $schoolHistory .= '<li><u>'.__('Start Date').'</u>: '.Format::date($values['dateStart']).'</li>';
        }

        $dataSelect = array('tawasulPersonID' => $values['tawasulPersonID']);
        $sqlSelect = 'SELECT tawasulFormGroup.name AS formGroup, tawasulSchoolYear.name AS schoolYear FROM tawasulStudentEnrolment JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) JOIN tawasulSchoolYear ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) WHERE tawasulPersonID=:tawasulPersonID ORDER BY tawasulStudentEnrolment.tawasulSchoolYearID';
        $resultSelect = $pdo->executeQuery($dataSelect, $sqlSelect);

        while ($resultSelect && $rowSelect = $resultSelect->fetch()) {
            $schoolHistory .= '<li><u>'.$rowSelect['schoolYear'].'</u>: '.$rowSelect['formGroup'].'</li>';
        }

        if ($values['dateEnd'] != '') {
            $schoolHistory .= '<li><u>'.__('End Date').'</u>: '.Format::date($values['dateEnd']).'</li>';
        }

        $row = $form->addRow();
            $row->addLabel('schoolHistory', __('School History'));
            $row->addContent('<ul class="list-none w-full sm:max-w-xs text-xs m-0">'.$schoolHistory.'</ul>');

        // Custom Fields
        $container->get(CustomFieldHandler::class)->addCustomFieldsToForm($form, 'Student Enrolment', [], $values['fields']);
        
        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        $form->loadAllValuesFrom($values);

        echo $form->getOutput();

    }
}
