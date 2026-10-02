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

$tawasulScaleGradeID = $_POST['tawasulScaleGradeID'] ?? '';
$tawasulScaleID = $_POST['tawasulScaleID'] ?? '';

if ($tawasulScaleID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/gradeScales_manage_edit_grade_delete.php&tawasulScaleID=$tawasulScaleID&tawasulScaleGradeID=$tawasulScaleGradeID";
    $URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/gradeScales_manage_edit.php&tawasulScaleID=$tawasulScaleID&tawasulScaleGradeID=$tawasulScaleGradeID";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulSchoolAdmin/gradeScales_manage_edit_grade_delete.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if tawasulScaleGradeID specified
        if ($tawasulScaleGradeID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulScaleGradeID' => $tawasulScaleGradeID);
                $sql = 'SELECT * FROM tawasulScaleGrade WHERE tawasulScaleGradeID=:tawasulScaleGradeID';
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
                    $data = array('tawasulScaleGradeID' => $tawasulScaleGradeID);
                    $sql = 'DELETE FROM tawasulScaleGrade WHERE tawasulScaleGradeID=:tawasulScaleGradeID';
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
