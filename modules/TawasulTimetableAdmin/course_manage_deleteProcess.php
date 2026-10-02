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

$tawasulCourseID = $_POST['tawasulCourseID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/course_manage_delete.php&tawasulCourseID='.$tawasulCourseID.'&tawasulSchoolYearID='.$_POST['tawasulSchoolYearID'].'&search='.($_POST['search'] ?? '');
$URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/course_manage.php&tawasulSchoolYearID='.$_POST['tawasulSchoolYearID'].'&search='.($_POST['search'] ?? '');

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/course_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    // Proceed!
    // Check if school year specified
    if ($tawasulCourseID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulCourseID' => $tawasulCourseID);
            $sql = 'SELECT * FROM tawasulCourse WHERE tawasulCourseID=:tawasulCourseID';
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
            // Try to delete entries in tawasulTTDayRowClass
            $dataSelect = array('tawasulCourseID' => $tawasulCourseID);
            $sqlSelect = 'SELECT tawasulTTDayRowClassID FROM tawasulTTDayRowClass JOIN tawasulCourseClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulCourseID=:tawasulCourseID';
            $resultSelect = $connection2->prepare($sqlSelect);
            $resultSelect->execute($dataSelect);
            if ($resultSelect->rowCount() > 0) {
                while ($rowSelect = $resultSelect->fetch()) {
                    $dataDelete = array('tawasulTTDayRowClassID' => $rowSelect['tawasulTTDayRowClassID']);
                    $sqlDelete = 'DELETE FROM tawasulTTDayRowClassException WHERE tawasulTTDayRowClassID=:tawasulTTDayRowClassID';
                    $resultDelete = $connection2->prepare($sqlDelete);
                    $resultDelete->execute($dataDelete);
                }
            }

            $dataSelect = array('tawasulCourseID' => $tawasulCourseID);
            $sqlSelect = 'SELECT tawasulCourseClassID FROM tawasulCourseClass WHERE tawasulCourseID=:tawasulCourseID';
            $resultSelect = $connection2->prepare($sqlSelect);
            $resultSelect->execute($dataSelect);
            if ($resultSelect->rowCount() > 0) {
                while ($rowSelect = $resultSelect->fetch()) {
                    $dataDelete = array('tawasulCourseClassID' => $rowSelect['tawasulCourseClassID']);
                    $sqlDelete = 'DELETE FROM tawasulTTDayRowClass WHERE tawasulCourseClassID=:tawasulCourseClassID';
                    $resultDelete = $connection2->prepare($sqlDelete);
                    $resultDelete->execute($dataDelete);
                }
            }

            // Delete students
            $dataStudent = array('tawasulCourseID' => $tawasulCourseID);
            $sqlStudent = 'SELECT * FROM tawasulCourseClass WHERE tawasulCourseID=:tawasulCourseID';
            $resultStudent = $connection2->prepare($sqlStudent);
            $resultStudent->execute($dataStudent);
            while ($rowStudent = $resultStudent->fetch()) {
                $dataDelete = array('tawasulCourseClassID' => $rowStudent['tawasulCourseClassID']);
                $sqlDelete = 'DELETE FROM tawasulCourseClassPerson WHERE tawasulCourseClassID=:tawasulCourseClassID';
                $resultDelete = $connection2->prepare($sqlDelete);
                $resultDelete->execute($dataDelete);
            }

            // Delete classes
            try {
                $dataDelete = array('tawasulCourseID' => $tawasulCourseID);
                $sqlDelete = 'DELETE FROM tawasulCourseClass WHERE tawasulCourseID=:tawasulCourseID';
                $resultDelete = $connection2->prepare($sqlDelete);
                $resultDelete->execute($dataDelete);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            // Delete Course
            try {
                $dataDelete = array('tawasulCourseID' => $tawasulCourseID);
                $sqlDelete = 'DELETE FROM tawasulCourse WHERE tawasulCourseID=:tawasulCourseID';
                $resultDelete = $connection2->prepare($sqlDelete);
                $resultDelete->execute($dataDelete);
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
