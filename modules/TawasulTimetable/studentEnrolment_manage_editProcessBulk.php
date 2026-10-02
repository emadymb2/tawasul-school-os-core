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

require_once __DIR__ . '/../../tawasul.php';

$tawasulCourseClassID = $_POST['tawasulCourseClassID'] ?? '';
$tawasulCourseID = $_POST['tawasulCourseID'] ?? '';
$action = $_POST['action'] ?? '';

if ($tawasulCourseClassID == '' or $tawasulCourseID == '' or $action == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/studentEnrolment_manage_edit.php&tawasulCourseID=$tawasulCourseID&tawasulCourseClassID=$tawasulCourseClassID";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/studentEnrolment_manage_edit.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Check access to the course
        try {
            $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $session->get('tawasulPersonID'), 'tawasulCourseClassID' => $tawasulCourseClassID);
            $sql = "SELECT tawasulCourseClassID, tawasulCourseClass.name, tawasulCourseClass.nameShort, tawasulCourse.tawasulCourseID, tawasulCourse.name AS courseName, tawasulCourse.nameShort as courseNameShort, tawasulCourse.description AS courseDescription, tawasulCourse.tawasulSchoolYearID, tawasulSchoolYear.name as yearName, tawasulYearGroupIDList FROM tawasulCourse JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) WHERE (role='Coordinator' OR role='Assistant Coordinator') AND tawasulPersonID=:tawasulPersonID AND tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourseClassID=:tawasulCourseClassID";
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
        }
        if ($result->rowCount() != 1) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
        } else {
            $people = isset($_POST['tawasulPersonID']) ? $_POST['tawasulPersonID'] : array();

            //Proceed!
            //Check if person specified
            if (count($people) < 1) {
                $URL .= '&return=error3';
                header("Location: {$URL}");
            } else {
                $partialFail = false;
                if ($action == 'Mark as left') {
                    foreach ($people as $tawasulPersonID) {
                        try {
                            $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonID' => $tawasulPersonID, 'dateUnenrolled' => date('Y-m-d'));
                            $sql = "UPDATE tawasulCourseClassPerson SET role=CONCAT(role, ' - Left'), dateUnenrolled=:dateUnenrolled  WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulPersonID=:tawasulPersonID  AND (role = 'Student' OR role = 'Teacher')";
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $partialFail == true;
                        }
                    }
                }

                if ($partialFail == true) {
                    $URL .= '&return=warning1';
                    header("Location: {$URL}");
                } else {
                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
        }
    }
}
