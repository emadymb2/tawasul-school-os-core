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
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/markbook_edit_addMulti.php&tawasulCourseClassID=$tawasulCourseClassID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulMarkbook/markbook_edit_addMulti.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    if (empty($_POST)) {
        $URL .= '&return=warning1';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Validate Inputs
        $tawasulCourseClassIDMulti = $_POST['tawasulCourseClassIDMulti'] ?? '';
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $columnColor = preg_replace('/[^a-zA-Z0-9\#]/', '', $_POST['columnColor'] ?? '');
        $type = $_POST['type'] ?? '';
        $date = (!empty($_POST['date']))? Format::dateConvert($_POST['date']) : date('Y-m-d');
        $tawasulSchoolYearTermID = (!empty($_POST['tawasulSchoolYearTermID']))? $_POST['tawasulSchoolYearTermID'] : null;
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

        //Lock markbook column table
        try {
            $sqlLock = 'LOCK TABLES tawasulMarkbookColumn WRITE, tawasulFileExtension READ';
            $resultLock = $connection2->query($sqlLock);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        //Get next groupingID
        try {
            $sqlGrouping = 'SELECT DISTINCT groupingID FROM tawasulMarkbookColumn WHERE NOT groupingID IS NULL ORDER BY groupingID DESC';
            $resultGrouping = $connection2->query($sqlGrouping);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        $rowGrouping = $resultGrouping->fetch();
        if (empty($rowGrouping['groupingID'])) {
            $groupingID = 1;
        } else {
            $groupingID = ($rowGrouping['groupingID'] + 1);
        }

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

        if (count($tawasulCourseClassIDMulti) < 1 or is_numeric($groupingID) == false or $groupingID < 1 or $name == '' or $description == '' or $type == '' or $date == '' or $viewableStudents == '' or $viewableParents == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            $partialFail = false;

            foreach ($tawasulCourseClassIDMulti as $tawasulCourseClassIDSingle) {

                // Get the next sequenceNumber for this column, in each class
                try {
                    $dataSequence = array('tawasulCourseClassID' => $tawasulCourseClassIDSingle);
                    $sqlSequence = 'SELECT max(sequenceNumber) as max FROM tawasulMarkbookColumn WHERE tawasulCourseClassID=:tawasulCourseClassID';
                    $resultSequence = $connection2->prepare($sqlSequence);
                    $resultSequence->execute($dataSequence);
                } catch (PDOException $e) {
                    $partialFail = true;
                }

                if ($resultSequence && $resultSequence->rowCount() > 0) {
                    $sequenceNumber = $resultSequence->fetchColumn() + 1;
                } else {
                    $sequenceNumber = 1;
                }

                //Write to database
                $tawasulMarkbookColumnID = null;
                try {
                    $data = array('groupingID' => $groupingID, 'tawasulCourseClassID' => $tawasulCourseClassIDSingle, 'name' => $name, 'description' => $description, 'columnColor' => $columnColor, 'type' => $type, 'date' => $date, 'sequenceNumber' => $sequenceNumber, 'attainment' => $attainment, 'tawasulScaleIDAttainment' => $tawasulScaleIDAttainment, 'attainmentWeighting' => $attainmentWeighting, 'attainmentRaw' => $attainmentRaw, 'attainmentRawMax' => $attainmentRawMax, 'effort' => $effort, 'tawasulScaleIDEffort' => $tawasulScaleIDEffort, 'tawasulRubricIDAttainment' => $tawasulRubricIDAttainment, 'tawasulRubricIDEffort' => $tawasulRubricIDEffort, 'comment' => $comment, 'uploadedResponse' => $uploadedResponse, 'completeDate' => $completeDate, 'complete' => $complete, 'viewableStudents' => $viewableStudents, 'viewableParents' => $viewableParents, 'attachment' => $attachment, 'tawasulPersonIDCreator' => $tawasulPersonIDCreator, 'tawasulPersonIDLastEdit' => $tawasulPersonIDLastEdit, 'tawasulSchoolYearTermID' => $tawasulSchoolYearTermID);
                    $sql = 'INSERT INTO tawasulMarkbookColumn SET groupingID=:groupingID, tawasulCourseClassID=:tawasulCourseClassID, name=:name, description=:description, columnColor=:columnColor, type=:type, date=:date, sequenceNumber=:sequenceNumber, attainment=:attainment, tawasulScaleIDAttainment=:tawasulScaleIDAttainment, attainmentWeighting=:attainmentWeighting, attainmentRaw=:attainmentRaw, attainmentRawMax=:attainmentRawMax, effort=:effort, tawasulScaleIDEffort=:tawasulScaleIDEffort, tawasulRubricIDAttainment=:tawasulRubricIDAttainment, tawasulRubricIDEffort=:tawasulRubricIDEffort, comment=:comment, uploadedResponse=:uploadedResponse, completeDate=:completeDate, complete=:complete, viewableStudents=:viewableStudents, viewableParents=:viewableParents, attachment=:attachment, tawasulPersonIDCreator=:tawasulPersonIDCreator, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit, tawasulSchoolYearTermID=:tawasulSchoolYearTermID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                    $tawasulMarkbookColumnID = $connection2->lastInsertID();
                } catch (PDOException $e) {
                    $partialFail = true;
                }
                
                // Record file tracking for each column
                if (!empty($fileMetaData) && !empty($tawasulMarkbookColumnID)) {
                    $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulMarkbookColumn', $tawasulMarkbookColumnID, 'attachment');

                    if (empty($tawasulFileID)) {
                        $partialFail = true;
                    }
                }
            }

            //Unlock module table
            $sql = 'UNLOCK TABLES';
            $result = $connection2->query($sql);

            if ($partialFail != false) {
                $URL .= '&return=warning1';
                header("Location: {$URL}");
            } else {
                $URL .= '&return=success0';
                header("Location: {$URL}");
            }
        }
    }
}
