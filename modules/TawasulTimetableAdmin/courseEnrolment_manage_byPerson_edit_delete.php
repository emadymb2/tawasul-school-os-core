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

use TawasulOS\Forms\Prefab\DeleteForm;

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Check if tawasulPersonID, tawasulCourseClassID, and tawasulSchoolYearID specified
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
            $sql = 'SELECT role, tawasulPerson.preferredName, tawasulPerson.surname, tawasulPerson.tawasulPersonID, tawasulCourseClass.tawasulCourseClassID, tawasulCourseClass.name, tawasulCourseClass.nameShort, tawasulCourse.tawasulCourseID, tawasulCourse.name AS courseName, tawasulCourse.nameShort as courseNameShort, tawasulCourse.description AS courseDescription, tawasulCourse.tawasulSchoolYearID, tawasulSchoolYear.name as yearName FROM tawasulPerson, tawasulCourseClass, tawasulCourseClassPerson,tawasulCourse, tawasulSchoolYear WHERE tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID AND tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID AND tawasulPerson.tawasulPersonID=:tawasulPersonID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $row = $result->fetch();
            $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module')."/courseEnrolment_manage_byPerson_edit_deleteProcess.php?type=$type&allUsers=$allUsers&search=$search");
            $form->addHiddenValue('tawasulCourseClassID', $tawasulCourseClassID);
            $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);
            $form->addHiddenValue('tawasulPersonID', $tawasulPersonID);
            echo $form->getOutput();
        }
    }
}
