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

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Contracts\Filesystem\FileHandler;
use TawasulOS\Services\Format;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$settingGateway = $container->get(SettingGateway::class);
$enableEffort = $settingGateway->getSettingByScope('Markbook', 'enableEffort');
$enableRubrics = $settingGateway->getSettingByScope('Markbook', 'enableRubrics');

$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$address = $_GET['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/markbook_edit_add.php&tawasulCourseClassID=$tawasulCourseClassID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulMarkbook/markbook_edit_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    if (empty($_POST)) {
        $URL .= '&return=warning1';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Validate Inputs
        $tawasulUnitID = $_POST['tawasulUnitID'] ?? '';
        $tawasulPlannerEntryID = !empty($_POST['tawasulPlannerEntryID']) ? $_POST['tawasulPlannerEntryID'] : null;
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $columnColor = preg_replace('/[^a-zA-Z0-9\#]/', '', $_POST['columnColor'] ?? '');
        $type = $_POST['type'] ?? '';
        $date = (!empty($_POST['date']))? Format::dateConvert($_POST['date']) : date('Y-m-d');
        $tawasulSchoolYearTermID = $_POST['tawasulSchoolYearTermID'] ?? null;

        // Grab the appropriate term ID if the date is provided and the term ID is not
        if (empty($tawasulSchoolYearTermID) && !empty($date)) {
            try {
                $dataTerm = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'date' => $date);
                $sqlTerm = "SELECT tawasulSchoolYearTermID FROM tawasulSchoolYearTerm WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND :date BETWEEN firstDay AND lastDay";
                $resultTerm = $connection2->prepare($sqlTerm);
                $resultTerm->execute($dataTerm);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }
            if ($resultTerm->rowCount() > 0) {
                $tawasulSchoolYearTermID = $resultTerm->fetchColumn(0);
            }
        }

        //Sort out attainment
        $attainment = $_POST['attainment'] ?? '';
        $attainmentWeighting = 1;
        $attainmentRaw = 'N';
        $attainmentRawMax = null;
        if ($attainment == 'N') {
            $tawasulScaleIDAttainment = null;
            $tawasulRubricIDAttainment = null;
        } else {
            if ($_POST['tawasulScaleIDAttainment'] == '') {
                $tawasulScaleIDAttainment = null;
            } else {
                $tawasulScaleIDAttainment = $_POST['tawasulScaleIDAttainment'] ?? '';
                if (isset($_POST['attainmentWeighting'])) {
                    if (is_numeric($_POST['attainmentWeighting']) && $_POST['attainmentWeighting'] > 0) {
                        $attainmentWeighting = $_POST['attainmentWeighting'] ?? '';
                    }
                }
                if (isset($_POST['attainmentRawMax'])) {
                    if (is_numeric($_POST['attainmentRawMax']) && $_POST['attainmentRawMax'] > 0) {
                        $attainmentRawMax = $_POST['attainmentRawMax'] ?? '';
                        $attainmentRaw = 'Y';
                    }
                }
            }
            if ($enableRubrics != 'Y') {
                $tawasulRubricIDAttainment = null;
            }
            else {
                if ($_POST['tawasulRubricIDAttainment'] == '') {
                    $tawasulRubricIDAttainment = null;
                } else {
                    $tawasulRubricIDAttainment = $_POST['tawasulRubricIDAttainment'] ?? '';
                }
            }
        }
        //Sort out effort
        if ($enableEffort != 'Y') {
            $effort = 'N';
        }
        else {
            $effort = $_POST['effort'] ?? '';
        }
        if ($effort == 'N') {
            $tawasulScaleIDEffort = null;
            $tawasulRubricIDEffort = null;
        } else {
            if ($_POST['tawasulScaleIDEffort'] == '') {
                $tawasulScaleIDEffort = null;
            } else {
                $tawasulScaleIDEffort = $_POST['tawasulScaleIDEffort'] ?? '';
            }
            if ($enableRubrics != 'Y') {
                $tawasulRubricIDEffort = null;
            }
            else {
                if ($_POST['tawasulRubricIDEffort'] == '') {
                    $tawasulRubricIDEffort = null;
                } else {
                    $tawasulRubricIDEffort = $_POST['tawasulRubricIDEffort'] ?? '';
                }
            }
        }
        $comment = $_POST['comment'] ?? '';
        $uploadedResponse = $_POST['uploadedResponse'] ?? '';
        $completeDate = $_POST['completeDate'] ?? '';
        if ($completeDate == '') {
            $completeDate = null;
            $complete = 'N';
        } else {
            $completeDate = Format::dateConvert($completeDate);
            $complete = 'Y';
        }
        $viewableStudents = $_POST['viewableStudents'] ?? '';
        $viewableParents = $_POST['viewableParents'] ?? '';
        $attachment = '';
        $tawasulPersonIDCreator = $session->get('tawasulPersonID');
        $tawasulPersonIDLastEdit = $session->get('tawasulPersonID');

        $sequenceNumber = null;

        // Build the initial column counts for this class
        try {
            $dataSequence = array('tawasulCourseClassID' => $tawasulCourseClassID);
            $sqlSequence = 'SELECT max(sequenceNumber) as max FROM tawasulMarkbookColumn WHERE tawasulCourseClassID=:tawasulCourseClassID';
            $resultSequence = $connection2->prepare($sqlSequence);
            $resultSequence->execute($dataSequence);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        if ($resultSequence && $resultSequence->rowCount() > 0) {
            $sequenceNumber = $resultSequence->fetchColumn() + 1;
        }

        $partialFail = false;

        //Move attached image  file, if there is one
        $fileMetaData = null;
        if (!empty($_FILES['file']['tmp_name'])) {
            $fileUploader = new TawasulOS\FileUploader($pdo, $session);

            $file = (isset($_FILES['file']))? $_FILES['file'] : null;

            // Upload the file, return the /uploads relative path
            $attachment = $fileUploader->uploadFromPost($file, $name);

            if (empty($attachment)) {
                $partialFail = true;
            } else {
                $fileMetaData = $fileUploader->getFileMetaData($attachment);
            }
        }

        if ($name == '' or $description == '' or $type == '' or $date == '' or $viewableStudents == '' or $viewableParents == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            //Write to database
            try {
                $data = array('tawasulUnitID' => $tawasulUnitID, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulCourseClassID' => $tawasulCourseClassID, 'name' => $name, 'description' => $description, 'columnColor' => $columnColor, 'type' => $type, 'date' => $date, 'sequenceNumber' => $sequenceNumber, 'attainment' => $attainment, 'tawasulScaleIDAttainment' => $tawasulScaleIDAttainment, 'attainmentWeighting' => $attainmentWeighting, 'attainmentRaw' => $attainmentRaw, 'attainmentRawMax' => $attainmentRawMax, 'effort' => $effort, 'tawasulScaleIDEffort' => $tawasulScaleIDEffort, 'tawasulRubricIDAttainment' => $tawasulRubricIDAttainment, 'tawasulRubricIDEffort' => $tawasulRubricIDEffort, 'comment' => $comment, 'uploadedResponse' => $uploadedResponse, 'completeDate' => $completeDate, 'complete' => $complete, 'viewableStudents' => $viewableStudents, 'viewableParents' => $viewableParents, 'attachment' => $attachment, 'tawasulPersonIDCreator' => $tawasulPersonIDCreator, 'tawasulPersonIDLastEdit' => $tawasulPersonIDLastEdit, 'tawasulSchoolYearTermID' => $tawasulSchoolYearTermID);
                $sql = 'INSERT INTO tawasulMarkbookColumn SET tawasulUnitID=:tawasulUnitID, tawasulPlannerEntryID=:tawasulPlannerEntryID, tawasulCourseClassID=:tawasulCourseClassID, name=:name, description=:description, columnColor=:columnColor, type=:type, date=:date, sequenceNumber=:sequenceNumber, attainment=:attainment, tawasulScaleIDAttainment=:tawasulScaleIDAttainment, attainmentWeighting=:attainmentWeighting, attainmentRaw=:attainmentRaw, attainmentRawMax=:attainmentRawMax, effort=:effort, tawasulScaleIDEffort=:tawasulScaleIDEffort, tawasulRubricIDAttainment=:tawasulRubricIDAttainment, tawasulRubricIDEffort=:tawasulRubricIDEffort, comment=:comment, uploadedResponse=:uploadedResponse, completeDate=:completeDate, complete=:complete, viewableStudents=:viewableStudents, viewableParents=:viewableParents, attachment=:attachment, tawasulPersonIDCreator=:tawasulPersonIDCreator, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit, tawasulSchoolYearTermID=:tawasulSchoolYearTermID';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            //Last insert ID
            $AI = str_pad($connection2->lastInsertID(), 10, '0', STR_PAD_LEFT);

            // Record file tracking
            if (!empty($fileMetaData)) {
                $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulMarkbookColumn', $AI, 'attachment');

                if (empty($tawasulFileID)) {
                    $partialFail = true;
                }
            }

            //Unlock module table

                $sql = 'UNLOCK TABLES';
                $result = $connection2->query($sql);

            if ($partialFail == true) {
                $URL .= '&return=warning1';
                header("Location: {$URL}");
            } else {
                $URL .= "&return=success0&editID=$AI";
                header("Location: {$URL}");
            }
        }
    }
}
