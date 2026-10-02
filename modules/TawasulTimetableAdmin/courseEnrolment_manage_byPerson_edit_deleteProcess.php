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
$tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? '';
$tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
$type = $_GET['type'] ?? '';
$allUsers = $_GET['allUsers'] ?? '';
$search = $_GET['search'] ?? '';

if ($tawasulPersonID == '' or $tawasulCourseClassID == '' or $tawasulSchoolYearID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/courseEnrolment_manage_byPerson_edit_edit.php&type=$type&tawasulPersonID=$tawasulPersonID&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseClassID=$tawasulCourseClassID&allUsers=$allUsers&search=$search";
    $URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/courseEnrolment_manage_byPerson_edit.php&type=$type&tawasulPersonID=$tawasulPersonID&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseClassID=$tawasulCourseClassID&allUsers=$allUsers&search=$search";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit_delete.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if tawasulPersonID specified
        if ($tawasulPersonID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonID' => $tawasulPersonID);
                $sql = 'SELECT role, tawasulPerson.preferredName, tawasulPerson.surname, tawasulPerson.tawasulPersonID, tawasulCourseClass.tawasulCourseClassID, tawasulCourseClass.name, tawasulCourseClass.nameShort, tawasulCourse.tawasulCourseID, tawasulCourse.name AS courseName, tawasulCourse.nameShort as courseNameShort, tawasulCourse.description AS courseDescription, tawasulCourse.tawasulSchoolYearID, tawasulSchoolYear.name as yearName FROM tawasulPerson, tawasulCourseClass, tawasulCourseClassPerson,tawasulCourse, tawasulSchoolYear WHERE tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID AND tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID AND tawasulPerson.tawasulPersonID=:tawasulPersonID';
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
                //Write to database
                try {
                    $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonID' => $tawasulPersonID);
                    $sql = 'DELETE FROM tawasulCourseClassPerson WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulPersonID=:tawasulPersonID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                $URLDelete = $URLDelete.'&return=success0';
                header("Location: {$URLDelete}");
            }
        }
    }
}
