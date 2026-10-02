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
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$tawasulCourseID = $_GET['tawasulCourseID'] ?? '';

if ($tawasulCourseID == '' or $tawasulCourseClassID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/studentEnrolment_manage_edit.php&tawasulCourseClassID=$tawasulCourseClassID&tawasulCourseID=$tawasulCourseID";

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
            //Run through each of the selected participants.
            $update = true;
            $choices = $_POST['Members'] ?? [];
            $role = $_POST['role'] ?? '';

            if (count($choices) < 1 or $role == '') {
                $URL .= '&return=error1';
                header("Location: {$URL}");
            } else {
                foreach ($choices as $t) {
                    //Check to see if student is already registered in this class
                    try {
                        $data = array('tawasulPersonID' => $t, 'tawasulCourseClassID' => $tawasulCourseClassID);
                        $sql = 'SELECT * FROM tawasulCourseClassPerson WHERE tawasulPersonID=:tawasulPersonID AND tawasulCourseClassID=:tawasulCourseClassID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $update = false;
                    }
                    //If student not in course, add them
                    if ($result->rowCount() == 0) {
                        try {
                            $data = array('tawasulPersonID' => $t, 'tawasulCourseClassID' => $tawasulCourseClassID, 'role' => $role, 'dateEnrolled' => date('Y-m-d'));
                            $sql = 'INSERT INTO tawasulCourseClassPerson SET tawasulPersonID=:tawasulPersonID, tawasulCourseClassID=:tawasulCourseClassID, role=:role, dateEnrolled=:dateEnrolled';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $update = false;
                        }
                    } else {
                        $values = $result->fetch();
                        $dateEnrolled = $values['role'] != $role || empty($values['dateEnrolled']) ? date('Y-m-d') : $values['dateEnrolled'];
                        try {
                            $data = array('tawasulPersonID' => $t, 'tawasulCourseClassID' => $tawasulCourseClassID, 'role' => $role, 'dateEnrolled' => $dateEnrolled);
                            $sql = "UPDATE tawasulCourseClassPerson SET role=:role, dateEnrolled=:dateEnrolled, dateUnenrolled=NULL, reportable='Y' WHERE tawasulPersonID=:tawasulPersonID AND tawasulCourseClassID=:tawasulCourseClassID";
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $update = false;
                        }
                    }
                }
                //Write to database
                if ($update == false) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                } else {
                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
        }
    }
}
