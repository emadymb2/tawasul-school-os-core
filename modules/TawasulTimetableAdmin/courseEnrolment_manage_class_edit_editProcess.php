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
$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulCourseClassPersonID = $_POST['tawasulCourseClassPersonID'] ?? '';

if ($tawasulCourseClassID == '' or $tawasulCourseID == '' or $tawasulSchoolYearID == '' or $tawasulCourseClassPersonID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/courseEnrolment_manage_class_edit_edit.php&tawasulCourseID=$tawasulCourseID&tawasulCourseClassPersonID=$tawasulCourseClassPersonID&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseClassID=$tawasulCourseClassID";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_manage_class_edit_edit.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if person specified
        if ($tawasulCourseClassPersonID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulCourseID' => $tawasulCourseID, 'tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulCourseClassPersonID' => $tawasulCourseClassPersonID);
                $sql = "SELECT tawasulCourseClassPerson.role, tawasulCourseClassPerson.dateEnrolled, tawasulCourseClassPerson.dateUnenrolled,  tawasulPerson.preferredName, tawasulPerson.surname, tawasulPerson.tawasulPersonID, tawasulCourseClass.tawasulCourseClassID, tawasulCourseClass.name, tawasulCourseClass.nameShort, tawasulCourse.tawasulCourseID, tawasulCourse.name AS courseName, tawasulCourse.nameShort as courseNameShort, tawasulCourse.description AS courseDescription, tawasulCourse.tawasulSchoolYearID, tawasulSchoolYear.name as yearName FROM tawasulPerson, tawasulCourseClass, tawasulCourseClassPerson,tawasulCourse, tawasulSchoolYear WHERE tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID AND tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID AND tawasulCourse.tawasulCourseID=:tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID AND tawasulCourseClassPerson.tawasulCourseClassPersonID=:tawasulCourseClassPersonID AND (tawasulPerson.status='Full' OR tawasulPerson.status='Expected')";
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            if ($result->rowCount() != 1) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                //Validate Inputs
                $values = $result->fetch();
                $role = $_POST['role'] ?? '';
                $reportable = $_POST['reportable'] ?? '';

                if ($role == '') {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    $dateEnrolled = $role != $values['role'] && stripos($role, 'Left') === false ? date('Y-m-d') : $values['dateEnrolled'];
                    $dateUnenrolled = $role != $values['role'] && stripos($role, 'Left') !== false ? date('Y-m-d') : $values['dateUnenrolled'];
                    try {
                        $data = array('role' => $role, 'reportable' => $reportable, 'tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulCourseClassPersonID' => $tawasulCourseClassPersonID, 'dateEnrolled' => $dateEnrolled, 'dateUnenrolled' => $dateUnenrolled);
                        $sql = 'UPDATE tawasulCourseClassPerson SET role=:role, dateEnrolled=:dateEnrolled, dateUnenrolled=:dateUnenrolled, reportable=:reportable WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulCourseClassPersonID=:tawasulCourseClassPersonID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
        }
    }
}
