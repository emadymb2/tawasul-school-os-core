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
use TawasulOS\Contracts\Filesystem\FileHandler;
use TawasulOS\Services\Format;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$address = $_GET['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/internalAssessment_manage_add.php&tawasulCourseClassID=$tawasulCourseClassID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulFormalAssessment/internalAssessment_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    if (empty($_POST)) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Validate Inputs
        $tawasulCourseClassIDMulti = $_POST['tawasulCourseClassIDMulti'] ?? [];

        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $type = $_POST['type'] ?? '';
        //Sort out attainment
        $attainment = $_POST['attainment'] ?? '';
        if ($attainment == 'N') {
            $tawasulScaleIDAttainment = null;
        } else {
            if ($_POST['tawasulScaleIDAttainment'] == '') {
                $tawasulScaleIDAttainment = null;
            } else {
                $tawasulScaleIDAttainment = $_POST['tawasulScaleIDAttainment'] ?? '';
            }
        }
        //Sort out effort
        $effort = $_POST['effort'] ?? '';
        if ($effort == 'N') {
            $tawasulScaleIDEffort = null;
        } else {
            if ($_POST['tawasulScaleIDEffort'] == '') {
                $tawasulScaleIDEffort = null;
            } else {
                $tawasulScaleIDEffort = $_POST['tawasulScaleIDEffort'] ?? '';
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
        $tawasulPersonIDCreator = $session->get('tawasulPersonID');
        $tawasulPersonIDLastEdit = $session->get('tawasulPersonID');

        $fileUploader = new TawasulOS\FileUploader($pdo, $session);
        $fileUploader->getFileExtensions();

        //Lock markbook column table
        try {
            $sqlLock = 'LOCK TABLES tawasulInternalAssessmentColumn WRITE';
            $resultLock = $connection2->query($sqlLock);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        //Get next groupingID
        try {
            $sqlGrouping = 'SELECT DISTINCT groupingID FROM tawasulInternalAssessmentColumn WHERE NOT groupingID IS NULL ORDER BY groupingID DESC';
            $resultGrouping = $connection2->query($sqlGrouping);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        $rowGrouping = $resultGrouping->fetch();
        if (is_null($rowGrouping['groupingID'])) {
            $groupingID = 1;
        } else {
            $groupingID = ($rowGrouping['groupingID'] + 1);
        }

        $time = time();
        $fileMetaData = null;
        //Move attached file, if there is one
        if (!empty($_FILES['file']['tmp_name'])) {
            $file = (isset($_FILES['file']))? $_FILES['file'] : null;

            // Upload the file, return the /uploads relative path
            $attachment = $fileUploader->uploadFromPost($file, $name);

            if (empty($attachment)) {
                $partialFail = true;
            } else {
                $fileMetaData = $fileUploader->getFileMetaData($attachment);
            }
        } else {
            $attachment = '';
        }

        if (is_array($tawasulCourseClassIDMulti) == false or is_numeric($groupingID) == false or $groupingID < 1 or $name == '' or $description == '' or $type == '' or $viewableStudents == '' or $viewableParents == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            $partialFail = false;

            // Prevent duplicate classes selected if courses span multiple year groups
            $tawasulCourseClassIDMulti = array_unique($tawasulCourseClassIDMulti);

            foreach ($tawasulCourseClassIDMulti as $tawasulCourseClassIDSingle) {
                //Write to database
                try {
                    $data = array('groupingID' => $groupingID, 'tawasulCourseClassID' => $tawasulCourseClassIDSingle, 'name' => $name, 'description' => $description, 'type' => $type, 'attainment' => $attainment, 'tawasulScaleIDAttainment' => $tawasulScaleIDAttainment, 'effort' => $effort, 'tawasulScaleIDEffort' => $tawasulScaleIDEffort, 'comment' => $comment, 'uploadedResponse' => $uploadedResponse, 'completeDate' => $completeDate, 'complete' => $complete, 'viewableStudents' => $viewableStudents, 'viewableParents' => $viewableParents, 'attachment' => $attachment, 'tawasulPersonIDCreator' => $tawasulPersonIDCreator, 'tawasulPersonIDLastEdit' => $tawasulPersonIDLastEdit);
                    $sql = 'INSERT INTO tawasulInternalAssessmentColumn SET groupingID=:groupingID, tawasulCourseClassID=:tawasulCourseClassID, name=:name, description=:description, type=:type, attainment=:attainment, tawasulScaleIDAttainment=:tawasulScaleIDAttainment, effort=:effort, tawasulScaleIDEffort=:tawasulScaleIDEffort, comment=:comment, uploadedResponse=:uploadedResponse, completeDate=:completeDate, complete=:complete, viewableStudents=:viewableStudents, viewableParents=:viewableParents, attachment=:attachment, tawasulPersonIDCreator=:tawasulPersonIDCreator, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);

                    $tawasulInternalAssessmentColumnID = $connection2->lastInsertID();

                    // Record file tracking (capture lastInsertID for each iteration)
                    if (!empty($fileMetaData) && !empty($tawasulInternalAssessmentColumnID)) {
                        $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulInternalAssessmentColumn', $tawasulInternalAssessmentColumnID, 'attachment');
                        
                        if (empty($tawasulFileID)) {
                            $partialFail = true;
                        }
                    }
                } catch (PDOException $e) {
                    exit();
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
                $URL .= '&return=success0';
                header("Location: {$URL}");
            }
        }
    }
}
