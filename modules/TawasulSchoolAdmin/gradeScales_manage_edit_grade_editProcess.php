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

$tawasulScaleGradeID = $_GET['tawasulScaleGradeID'] ?? '';
$tawasulScaleID = $_GET['tawasulScaleID'] ?? '';

if ($tawasulScaleID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/gradeScales_manage_edit_grade_edit.php&tawasulScaleID=$tawasulScaleID&tawasulScaleGradeID=$tawasulScaleGradeID";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulSchoolAdmin/gradeScales_manage_edit_grade_edit.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if tt specified
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
                //Validate Inputs
                $value = $_POST['value'] ?? '';
                $descriptor = $_POST['descriptor'] ?? '';
                $sequenceNumber = $_POST['sequenceNumber'] ?? '';
                $isDefault = $_POST['isDefault'] ?? '';

                if ($value == '' or $descriptor == '' or $sequenceNumber == '' or $isDefault == '') {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                } else {
                    //Check unique inputs for uniquness
                    try {
                        $data = array('value' => $value, 'sequenceNumber' => $sequenceNumber, 'tawasulScaleID' => $tawasulScaleID, 'tawasulScaleGradeID' => $tawasulScaleGradeID);
                        $sql = 'SELECT * FROM tawasulScaleGrade WHERE (value=:value OR sequenceNumber=:sequenceNumber) AND tawasulScaleID=:tawasulScaleID AND NOT tawasulScaleGradeID=:tawasulScaleGradeID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    if ($result->rowCount() > 0) {
                        $URL .= '&return=error3';
                        header("Location: {$URL}");
                    } else {
                        //If isDefault is Y, then set all other grades in scale to N
                        if ($isDefault == 'Y') {
                            try {
                                $data = array('tawasulScaleID' => $tawasulScaleID, 'tawasulScaleGradeID' => $tawasulScaleGradeID);
                                $sql = "UPDATE tawasulScaleGrade SET isDefault='N' WHERE tawasulScaleID=:tawasulScaleID AND NOT tawasulScaleGradeID=:tawasulScaleGradeID";
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $URL .= '&return=error2';
                                header("Location: {$URL}");
                                exit();
                            }
                        }

                        //Write to database
                        try {
                            $data = array('value' => $value, 'descriptor' => $descriptor, 'sequenceNumber' => $sequenceNumber, 'isDefault' => $isDefault, 'tawasulScaleGradeID' => $tawasulScaleGradeID);
                            $sql = 'UPDATE tawasulScaleGrade SET value=:value, descriptor=:descriptor, sequenceNumber=:sequenceNumber, isDefault=:isDefault WHERE tawasulScaleGradeID=:tawasulScaleGradeID';
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
}
