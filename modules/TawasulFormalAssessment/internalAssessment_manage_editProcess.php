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
use TawasulOS\Domain\FormalAssessment\InternalAssessmentColumnGateway;
use TawasulOS\Contracts\Filesystem\FileHandler;
use TawasulOS\Services\Format;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$tawasulInternalAssessmentColumnID = $_GET['tawasulInternalAssessmentColumnID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address'])."/internalAssessment_manage_edit.php&tawasulInternalAssessmentColumnID=$tawasulInternalAssessmentColumnID&tawasulCourseClassID=$tawasulCourseClassID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulFormalAssessment/internalAssessment_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    if (empty($_POST)) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if tawasulInternalAssessmentColumnID and tawasulCourseClassID specified
        if ($tawasulInternalAssessmentColumnID == '' or $tawasulCourseClassID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            $result = $container->get(InternalAssessmentColumnGateway::class)->selectBy(['tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID, 'tawasulCourseClassID' => $tawasulCourseClassID]);

            if ($result->rowCount() != 1) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                $row = $result->fetch();
                $partialFail = false;

                //Validate Inputs
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
                $tawasulPersonIDLastEdit = $session->get('tawasulPersonID');

                $time = time();
                $fileMetaData = null;
                //Move attached file, if there is one
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
                } else {
                    // Remove the attachment if it has been deleted, otherwise retain the original value
                    $attachment = empty($_POST['attachment']) ? null : $row['attachment'];
                }

                if ($name == '' or $description == '' or $type == '' or $viewableStudents == '' or $viewableParents == '') {
                    $URL .= '&return=error1';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    try {
                        $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'name' => $name, 'description' => $description, 'type' => $type, 'attainment' => $attainment, 'tawasulScaleIDAttainment' => $tawasulScaleIDAttainment, 'effort' => $effort, 'tawasulScaleIDEffort' => $tawasulScaleIDEffort, 'comment' => $comment, 'uploadedResponse' => $uploadedResponse, 'completeDate' => $completeDate, 'complete' => $complete, 'viewableStudents' => $viewableStudents, 'viewableParents' => $viewableParents, 'attachment' => $attachment, 'tawasulPersonIDLastEdit' => $tawasulPersonIDLastEdit, 'tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID);
                        $sql = 'UPDATE tawasulInternalAssessmentColumn SET tawasulCourseClassID=:tawasulCourseClassID, name=:name, description=:description, type=:type, attainment=:attainment, tawasulScaleIDAttainment=:tawasulScaleIDAttainment, effort=:effort, tawasulScaleIDEffort=:tawasulScaleIDEffort, comment=:comment, uploadedResponse=:uploadedResponse, completeDate=:completeDate, complete=:complete, viewableStudents=:viewableStudents, viewableParents=:viewableParents, attachment=:attachment, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit WHERE tawasulInternalAssessmentColumnID=:tawasulInternalAssessmentColumnID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    $fileHandler = $container->get(FileHandler::class);
                    // Handle file deletion when user removes attachment
                    if (empty($attachment) && !empty($row['attachment'])) {
                        $deleted = $fileHandler->deleteFile('tawasulInternalAssessmentColumn', $tawasulInternalAssessmentColumnID, 'attachment');
                    }

                    // Record file tracking
                    if (!empty($fileMetaData) && !empty($tawasulInternalAssessmentColumnID)) {
                        $tawasulFileID = $fileHandler->recordFileUpload($fileMetaData, 'tawasulInternalAssessmentColumn', $tawasulInternalAssessmentColumnID, 'attachment');

                        if (empty($tawasulFileID)) {
                            $partialFail = true;
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
        }
    }
}
