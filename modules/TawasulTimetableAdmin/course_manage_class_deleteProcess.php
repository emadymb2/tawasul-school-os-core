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

if ($tawasulCourseID == '' or $tawasulSchoolYearID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/course_manage_class_delete.php&tawasulCourseID=$tawasulCourseID&tawasulCourseClassID=$tawasulCourseClassID&tawasulSchoolYearID=$tawasulSchoolYearID";
    $URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/course_manage_edit.php&tawasulCourseID=$tawasulCourseID&tawasulCourseClassID=$tawasulCourseClassID&tawasulSchoolYearID=$tawasulSchoolYearID";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/course_manage_class_delete.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if tawasulCourseClassID specified
        if ($tawasulCourseClassID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
                $sql = 'SELECT * FROM tawasulCourseClass WHERE tawasulCourseClassID=:tawasulCourseClassID';
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
                //Try to delete entries in tawasulTTDayRowClass

                    $dataSelect = array('tawasulCourseClassID' => $tawasulCourseClassID);
                    $sqlSelect = 'SELECT * FROM tawasulTTDayRowClass WHERE tawasulCourseClassID=:tawasulCourseClassID';
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


                    $dataDelete = array('tawasulCourseClassID' => $tawasulCourseClassID);
                    $sqlDelete = 'DELETE FROM tawasulTTDayRowClass WHERE tawasulCourseClassID=:tawasulCourseClassID';
                    $resultDelete = $connection2->prepare($sqlDelete);
                    $resultDelete->execute($dataDelete);

                //Delete students and other participants

                    $dataDelete = array('tawasulCourseClassID' => $tawasulCourseClassID);
                    $sqlDelete = 'DELETE FROM tawasulCourseClassPerson WHERE tawasulCourseClassID=:tawasulCourseClassID';
                    $resultDelete = $connection2->prepare($sqlDelete);
                    $resultDelete->execute($dataDelete);

                //Write to database
                try {
                    $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
                    $sql = 'DELETE FROM tawasulCourseClass WHERE tawasulCourseClassID=:tawasulCourseClassID';
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
