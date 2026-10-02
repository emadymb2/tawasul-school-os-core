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

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/spaceChange_manage_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Proceed!
        //Check if tawasulTTSpaceChangeID and tawasulCourseClassID specified
        $tawasulTTSpaceChangeID = $_GET['tawasulTTSpaceChangeID'] ?? '';
        $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
        if ($tawasulTTSpaceChangeID == '' OR $tawasulCourseClassID == '') {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {
            try {
                if ($highestAction == 'Manage Facility Changes_allClasses') {
                    $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = 'SELECT tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
                } else if ($highestAction == 'Manage Facility Changes_myDepartment') {
                    $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulSchoolYearID2' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID2' => $session->get('tawasulPersonID'), 'tawasulCourseClassID2' => $tawasulCourseClassID);
                    $sql = '(SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID)
                    UNION
                    (SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID2 AND (tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID2 AND role=\'Coordinator\') AND tawasulCourseClassID=:tawasulCourseClassID2)';
                } else {
                    $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = 'SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';
                }
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $page->addError(__('Your request failed due to a database error.'));
            }

            if ($result->rowCount() != 1) {
                $page->addError(__('The specified record cannot be found.'));
            } else {
                $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module')."/spaceChange_manage_deleteProcess.php");
                $form->addHiddenValue('tawasulTTSpaceChangeID', $tawasulTTSpaceChangeID);
                echo $form->getOutput();
            }
        }
    }
}
