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
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\School\SchoolYearGateway;
use TawasulOS\Domain\Timetable\CourseSyncGateway;
use TawasulOS\Forms\CustomFieldHandler;

if (isActionAccessible($guid, $connection2, '/modules/TawasulAdmissions/studentEnrolment_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $search = $_GET['search'] ?? '';

    $page->breadcrumbs
        ->add(__('Student Enrolment'), 'studentEnrolment_manage.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID])
        ->add(__('Add Student Enrolment'));

    $editLink = '';
    if (isset($_GET['editID'])) {
        $editLink = $session->get('absoluteURL').'/index.php?q=/modules/TawasulAdmissions/studentEnrolment_manage_edit.php&tawasulStudentEnrolmentID='.$_GET['editID'].'&search='.$_GET['search'].'&tawasulSchoolYearID='.$_GET['tawasulSchoolYearID'];
    }
    $page->return->setEditLink($editLink);

    //Check if school year specified
    if ($tawasulSchoolYearID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        if ($search != '') {
            $params = [
                "search" => $search,
                "tawasulSchoolYearID" => $tawasulSchoolYearID
            ];
            $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulAdmissions', 'studentEnrolment_manage.php')->withQueryParams($params));
        }

        $form = Form::create('studentEnrolmentAdd', $session->get('absoluteURL').'/modules/'.$session->get('module')."/studentEnrolment_manage_addProcess.php?tawasulSchoolYearID=$tawasulSchoolYearID&search=$search");
        $form->setFactory(DatabaseFormFactory::create($pdo));

        $form->addHiddenValue('address', $session->get('address'));

        $schoolYear = $container->get(SchoolYearGateway::class)->getByID($tawasulSchoolYearID, ['name']);
        $schoolYearName = $schoolYear['name'] ?? $session->get('tawasulSchoolYearName');

        $row = $form->addRow()->addHeading('Basic Information', __('Basic Information'));

        $row = $form->addRow();
            $row->addLabel('yearName', __('School Year'));
            $row->addTextField('yearName')->readOnly()->maxLength(20)->setValue($schoolYearName);

        $students = $container->get(StudentGateway::class)->selectUnenrolledStudentsBySchoolYear($tawasulSchoolYearID);
        $row = $form->addRow();
           $row->addLabel('tawasulPersonID', __('Student'))->description(__('Only includes students not enrolled in specified year.'));
           $row->addSearchSelect('tawasulPersonID')->fromResults($students)->required()->placeholder();

        $row = $form->addRow();
            $row->addLabel('tawasulYearGroupID', __('Year Group'));
            $row->addSelectYearGroup('tawasulYearGroupID')->required();

        $row = $form->addRow();
            $row->addLabel('tawasulFormGroupID', __('Form Group'));
            $row->addSelectFormGroup('tawasulFormGroupID', $tawasulSchoolYearID)->required();

        $row = $form->addRow();
            $row->addLabel('rollOrder', __('Roll Order'));
            $row->addNumber('rollOrder')->maxLength(2);

        // Check to see if any class mappings exists -- otherwise this feature is inactive, hide it
        $classMapCount = $container->get(CourseSyncGateway::class)->countAll();
        if ($classMapCount > 0) {
            $autoEnrolDefault = $container->get(SettingGateway::class)->getSettingByScope('Timetable Admin', 'autoEnrolCourses');
            $row = $form->addRow();
                $row->addLabel('autoEnrolStudent', __('Auto-Enrol Courses?'))
                    ->description(__('Should this student be automatically enrolled in courses for their Form Group?'));
                $row->addYesNo('autoEnrolStudent')->selected($autoEnrolDefault);
        }

        // Custom Fields
        $container->get(CustomFieldHandler::class)->addCustomFieldsToForm($form, 'Student Enrolment', []);

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        echo $form->getOutput();
    }
}
