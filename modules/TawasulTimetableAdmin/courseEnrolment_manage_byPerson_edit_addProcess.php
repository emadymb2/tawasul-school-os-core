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

$tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$type = $_GET['type'] ?? '';
$allUsers = $_GET['allUsers'] ?? '';
$search = $_GET['search'] ?? '';

if ($tawasulSchoolYearID == '' or $tawasulPersonID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/courseEnrolment_manage_byPerson_edit.php&type=$type&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulPersonID=$tawasulPersonID&allUsers=$allUsers&search=$search";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_manage_byPerson_edit.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
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
                    $data = array('tawasulPersonID' => $tawasulPersonID, 'tawasulCourseClassID' => $t);
                    $sql = 'SELECT * FROM tawasulCourseClassPerson WHERE tawasulPersonID=:tawasulPersonID AND tawasulCourseClassID=:tawasulCourseClassID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $update = false;
                }
                //If student not in course, add them
                if ($result->rowCount() == 0) {
                    try {
                        $data = array('tawasulPersonID' => $tawasulPersonID, 'tawasulCourseClassID' => $t, 'role' => $role, 'dateEnrolled' => date('Y-m-d'));
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
                        $data = array('tawasulPersonID' => $tawasulPersonID, 'tawasulCourseClassID' => $t, 'role' => $role, 'dateEnrolled' => $dateEnrolled);
                        $sql = 'UPDATE tawasulCourseClassPerson SET role=:role, dateEnrolled=:dateEnrolled, dateUnenrolled=NULL WHERE tawasulPersonID=:tawasulPersonID AND tawasulCourseClassID=:tawasulCourseClassID';
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
