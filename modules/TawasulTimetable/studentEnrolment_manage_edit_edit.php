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

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/studentEnrolment_manage_edit_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Check if tawasulCourseClassID, tawasulCourseID, and tawasulPersonID specified
    $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
    $tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
    if ($tawasulCourseClassID == '' or $tawasulCourseID == '' or $tawasulPersonID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        
            $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonID2' => $tawasulPersonID);
            $sql = "SELECT surname, preferredName, tawasulCourseClass.tawasulCourseClassID, tawasulCourseClassPerson.role, tawasulCourseClassPerson.dateEnrolled, tawasulCourseClassPerson.dateUnenrolled, tawasulCourseClass.name, tawasulCourseClass.nameShort, tawasulCourse.tawasulCourseID, tawasulCourse.name AS courseName, tawasulCourse.nameShort as courseNameShort, tawasulCourse.description AS courseDescription, tawasulCourse.tawasulSchoolYearID, tawasulSchoolYear.name as yearName, tawasulYearGroupIDList FROM tawasulCourse JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) WHERE (tawasulDepartmentStaff.role='Coordinator' OR tawasulDepartmentStaff.role='Assistant Coordinator') AND tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID AND tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID2";
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            $page->breadcrumbs
                ->add(__('Manage Student Enrolment'), 'studentEnrolment_manage.php')
                ->add(__('Edit %1$s.%2$s Enrolment', [
                    '%1$s' => $values['courseNameShort'],
                    '%2$s' => $values['name']
                ]), 'studentEnrolment_manage_edit.php', [
                    'tawasulCourseClassID' => $_GET['tawasulCourseClassID'],
                    'tawasulCourseID' => $_GET['tawasulCourseID'],
                ])
                ->add(__('Edit Participant'));

            $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/studentEnrolment_manage_edit_editProcess.php?tawasulCourseClassID=$tawasulCourseClassID&tawasulCourseID=$tawasulCourseID");
                
            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulPersonID', $tawasulPersonID);

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
            );

            $row = $form->addRow();
                $row->addLabel('role', __('Role'));
                $row->addSelect('role')->fromArray($roles)->required()->selected($values['role']);
            
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
