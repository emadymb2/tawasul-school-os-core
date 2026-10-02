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
$tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? '';
$action = $_POST['action'] ?? '';
$search = $_POST['search'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/courseEnrolment_manage_class_edit.php&tawasulCourseID=$tawasulCourseID&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseClassID=$tawasulCourseClassID&search=$search";

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/courseEnrolment_manage_class_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else if ($tawasulCourseClassID == '' or $tawasulCourseID == '' or $tawasulSchoolYearID == '' or $action == '') {
    $URL .= '&return=error1';
    header("Location: {$URL}");
} else {
    $people = isset($_POST['tawasulCourseClassPersonID']) ? $_POST['tawasulCourseClassPersonID'] : array();

    //Proceed!
    //Check if person specified
    if (count($people) < 1) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
    } else {
        $partialFail = false;
        if ($action == 'Delete') {
            foreach ($people as $tawasulCourseClassPersonID) {
                try {
                    $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulCourseClassPersonID' => $tawasulCourseClassPersonID);
                    $sql = 'DELETE FROM tawasulCourseClassPerson WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulCourseClassPersonID=:tawasulCourseClassPersonID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $partialFail == true;
                }
            }
        }
        else if ($action == 'Copy to class') {
            $tawasulCourseClassIDCopyTo = (isset($_POST['tawasulCourseClassIDCopyTo']))? $_POST['tawasulCourseClassIDCopyTo'] : NULL;
            if (!empty($tawasulCourseClassIDCopyTo)) {

                foreach ($people as $tawasulCourseClassPersonID) {
                    // Check for duplicates
                    try {
                        $dataCheck = array('tawasulCourseClassIDCopyTo' => $tawasulCourseClassIDCopyTo, 'tawasulCourseClassPersonID' => $tawasulCourseClassPersonID);
                        $sqlCheck = 'SELECT tawasulPersonID FROM tawasulCourseClassPerson WHERE tawasulCourseClassID=:tawasulCourseClassIDCopyTo AND tawasulCourseClassPersonID=:tawasulCourseClassPersonID';
                        $resultCheck = $connection2->prepare($sqlCheck);
                        $resultCheck->execute($dataCheck);
                    } catch (PDOException $e) {
                        $partialFail == true;
                    }

                    // Insert new course participants
                    if ($resultCheck->rowCount() == 0) {
                        try {
                            $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulCourseClassPersonID' => $tawasulCourseClassPersonID, 'tawasulCourseClassIDCopyTo' => $tawasulCourseClassIDCopyTo, 'dateEnrolled' => date('Y-m-d'));
                            $sql = 'INSERT INTO tawasulCourseClassPerson (tawasulCourseClassID, tawasulPersonID, role, dateEnrolled, reportable) SELECT :tawasulCourseClassIDCopyTo, tawasulPersonID, role, :dateEnrolled, reportable FROM tawasulCourseClassPerson WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulCourseClassPersonID=:tawasulCourseClassPersonID';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $partialFail == true;
                        }
                    }


                }
            } else {
                $URL .= '&return=error3';
                header("Location: {$URL}");
            }
        } else if ($action == 'Mark as left') {
            foreach ($people as $tawasulCourseClassPersonID) {
                try {
                    $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulCourseClassPersonID' => $tawasulCourseClassPersonID, 'dateUnenrolled' => date('Y-m-d'));
                    $sql = "UPDATE tawasulCourseClassPerson SET role=CONCAT(role, ' - Left '), dateUnenrolled=:dateUnenrolled WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulCourseClassPersonID=:tawasulCourseClassPersonID AND (role = 'Student' OR role = 'Teacher')";
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
