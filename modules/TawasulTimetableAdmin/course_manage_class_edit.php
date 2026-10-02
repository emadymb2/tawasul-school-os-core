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
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Http\Url;

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/course_manage_class_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
    $tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $search = $_GET['search'] ?? '';
    $urlParams = compact('tawasulSchoolYearID', 'search');

    $page->breadcrumbs
        ->add(__('Manage Courses & Classes'), 'course_manage.php',$urlParams)
        ->add(__('Edit Course & Classes'), 'course_manage_edit.php', $urlParams + ['tawasulCourseID' => $tawasulCourseID])
        ->add(__('Edit Class'));

    if (!empty($search)) {
        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulTimetableAdmin', 'course_manage.php')->withQueryParams($urlParams));
    }

    //Check if tawasulCourseClassID, tawasulCourseID, and tawasulSchoolYearID specified
    if ($tawasulCourseClassID == '' or $tawasulCourseID == '' or $tawasulSchoolYearID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $data = array('tawasulCourseID' => $tawasulCourseID, 'tawasulCourseClassID' => $tawasulCourseClassID);
            $sql = 'SELECT tawasulCourseClassID, tawasulCourseClass.name, tawasulCourseClass.nameShort, tawasulCourse.tawasulCourseID, tawasulCourse.name AS courseName, tawasulCourse.nameShort as courseNameShort, tawasulCourse.description AS courseDescription, tawasulCourse.tawasulSchoolYearID, tawasulSchoolYear.name as yearName, reportable, attendance, enrolmentMin, enrolmentMax, tawasulCourseClass.fields FROM tawasulCourseClass, tawasulCourse, tawasulSchoolYear WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID AND tawasulCourse.tawasulCourseID=:tawasulCourseID AND tawasulCourseClassID=:tawasulCourseClassID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
			$values = $result->fetch();
            $courseClassName = $values['courseNameShort'].$values['nameShort'];
            $length= mb_strlen($courseClassName);
            if ($length> 16) {
                $page->addWarning(__('The combined short name for this class, {courseClassName}, is longer than the recommended length of 16 characters. Please note this may cause visual issues on the timetable and in dropdown menus.', ['courseClassName' => $courseClassName]));
            }
            
			$form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module').'/course_manage_class_editProcess.php');

			$form->addHiddenValue('address', $session->get('address'));
			$form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);
			$form->addHiddenValue('tawasulCourseClassID', $tawasulCourseClassID);
			$form->addHiddenValue('tawasulCourseID', $tawasulCourseID);

            $row = $form->addRow()->addHeading('Basic Details', __('Basic Details'));

			$row = $form->addRow();
				$row->addLabel('schoolYearName', __('School Year'));
				$row->addTextField('schoolYearName')->required()->readonly()->setValue($values['yearName']);

			$row = $form->addRow();
				$row->addLabel('courseName', __('Course'));
				$row->addTextField('courseName')->required()->readonly()->setValue($values['courseName']);

			$row = $form->addRow();
				$row->addLabel('name', __('Name'))->description(__('Must be unique for this course.'));
				$row->addTextField('name')->required()->maxLength(30);

			$row = $form->addRow();
				$row->addLabel('nameShort', __('Short Name'))->description(__('Must be unique for this course.'));
				$row->addTextField('nameShort')->required()->maxLength(16);

			$row = $form->addRow();
				$row->addLabel('reportable', __('Reportable?'))->description(__('Should this class show in reports?'));
				$row->addYesNo('reportable');

			if (isActionAccessible($guid, $connection2, "/modules/TawasulAttendance/attendance_take_byCourseClass.php")) {
				$row = $form->addRow();
				$row->addLabel('attendance', __('Track Attendance?'))->description(__('Should this class allow attendance to be taken?'));
				$row->addYesNo('attendance');
			}

            $row = $form->addRow()->addHeading('Advanced Options', __('Advanced Options'));

            $row = $form->addRow();
				$row->addLabel('enrolmentMin', __('Minimum Enrolment'))->description(__('Class should not run below this number of students.'));
				$row->addNumber('enrolmentMin')->onlyInteger(true)->minimum(1)->maximum(9999)->maxLength(4);

            $row = $form->addRow();
				$row->addLabel('enrolmentMax', __('Maximum Enrolment'))->description(__('Enrolment should not exceed this number of students.'));
				$row->addNumber('enrolmentMax')->onlyInteger(true)->minimum(1)->maximum(9999)->maxLength(4);

            // Custom Fields
            $container->get(CustomFieldHandler::class)->addCustomFieldsToForm($form, 'Class', [], $values['fields']);

			$row = $form->addRow();
				$row->addFooter();
				$row->addSubmit();

			$form->loadAllValuesFrom($values);

			echo $form->getOutput();
        }
    }
}
