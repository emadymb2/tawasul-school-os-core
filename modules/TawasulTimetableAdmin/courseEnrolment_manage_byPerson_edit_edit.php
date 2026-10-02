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

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Check if tawasulPersonID, tawasulCourseClassID and tawasulSchoolYearID specified
    $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
    $type = $_GET['type'] ?? '';
    $allUsers = $_GET['allUsers'] ?? '';
    $search = $_GET['search'] ?? '';

    if ($tawasulPersonID == '' or $tawasulCourseClassID == '' or $tawasulSchoolYearID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        
            $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonID' => $tawasulPersonID);
            $sql = "SELECT role, tawasulCourseClassPerson.dateEnrolled, tawasulCourseClassPerson.dateUnenrolled, tawasulPerson.preferredName, tawasulPerson.surname, tawasulPerson.tawasulPersonID, tawasulCourseClass.tawasulCourseClassID, tawasulCourseClass.name, tawasulCourseClass.nameShort, tawasulCourse.tawasulCourseID, tawasulCourse.name AS courseName, tawasulCourse.nameShort as courseNameShort, tawasulCourse.description AS courseDescription, tawasulCourse.tawasulSchoolYearID, tawasulSchoolYear.name as yearName, tawasulCourseClassPerson.reportable FROM tawasulPerson, tawasulCourseClass, tawasulCourseClassPerson,tawasulCourse, tawasulSchoolYear WHERE tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID AND tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID AND tawasulPerson.tawasulPersonID=:tawasulPersonID";
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            $urlParams = ['tawasulCourseClassID' => $tawasulCourseClassID, 'type' => $type, 'tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID, 'allUsers' => $allUsers];

            $page->breadcrumbs
                ->add(__('Course Enrolment by Person'), 'courseEnrolment_manage_byPerson.php', $urlParams)
                ->add(Format::name('', $values['preferredName'], $values['surname'], 'Student'), 'courseEnrolment_manage_byPerson_edit.php', $urlParams)
                ->add(__('Edit Participant'));

			$form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/courseEnrolment_manage_byPerson_edit_editProcess.php?tawasulCourseClassID=$tawasulCourseClassID&type=$type&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulPersonID=$tawasulPersonID&allUsers=$allUsers&search=$search");
                
			$form->addHiddenValue('address', $session->get('address'));
			$form->addHiddenValue('tawasulPersonID', $tawasulPersonID);
			
			if ($search != '') {
                $params = [
                    "search" => $search,
                    "tawasulCourseClassID" => $tawasulCourseClassID,
                    "tawasulPersonID" => $tawasulPersonID,
                    "allUsers" => $allUsers,
                    "tawasulSchoolYearID" => $tawasulSchoolYearID,
                    "type" => $type
                ];
                $form->addHeaderAction('back', __('Back'))
                    ->setURL('/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit.php')
                    ->addParams($params);
            }

			$row = $form->addRow();
				$row->addLabel('yearName', __('School Year'));
				$row->addTextField('yearName')->readonly()->setValue($values['yearName']);
			
			$row = $form->addRow();
				$row->addLabel('courseName', __('Course'));
				$row->addTextField('courseName')->readonly()->setValue($values['courseName']);

			$row = $form->addRow();
				$row->addLabel('name', __('Class'));
				$row->addTextField('name')->readonly()->setValue($values['name']);

			$row = $form->addRow();
				$row->addLabel('participant', __('Participant'));
				$row->addTextField('participant')->readonly()->setValue(Format::name('', htmlPrep($values['preferredName']), htmlPrep($values['surname']), 'Student'));

			$roles = array(
                'Student'        => __('Student'),
                'Student - Left' => __('Student - Left'),
                'Teacher'        => __('Teacher'),
                'Teacher - Left' => __('Teacher - Left'),
                'Assistant'      => __('Assistant'),
                'Technician'     => __('Technician'),
                'Parent'         => __('Parent'),
            );

            $row = $form->addRow();
                $row->addLabel('role', __('Role'));
				$row->addSelect('role')->fromArray($roles)->required()->selected($values['role']);
			
			$row = $form->addRow();
				$row->addLabel('reportable', __('Reportable'))->description(__("Students set to non-reportable won't display in reports. Teachers set to non-reportable won't display in lists of class teachers."));
                $row->addYesNo('reportable')->required()->selected($values['reportable']);
                
            if (!empty($values['dateEnrolled'])) {
                $row = $form->addRow();
                    $row->addLabel('dateEnrolled', __('Date Enrolled'));
                    $row->addTextField('dateEnrolled')->readonly()->setValue(Format::date($values['dateEnrolled']));
            }
                
            if (!empty($values['dateUnenrolled']) && stripos($values['role'], 'Left') !== false) {
                $row = $form->addRow();
                    $row->addLabel('dateUnenrolled', __('Date Unenrolled'));
                    $row->addTextField('dateUnenrolled')->readonly()->setValue(Format::date($values['dateUnenrolled']));
            }

			$row = $form->addRow();
				$row->addFooter();
				$row->addSubmit();

			echo $form->getOutput();
        }
    }
}
