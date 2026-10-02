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
use TawasulOS\Domain\School\GradeScaleGateway;
use TawasulOS\Contracts\Filesystem\FileHandler;
use TawasulOS\Services\Format;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$tawasulInternalAssessmentColumnID = $_GET['tawasulInternalAssessmentColumnID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address'])."/internalAssessment_write_data.php&tawasulInternalAssessmentColumnID=$tawasulInternalAssessmentColumnID&tawasulCourseClassID=$tawasulCourseClassID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulFormalAssessment/internalAssessment_write_data.php') == false) {
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
            try {
                
                $result = $container->get(InternalAssessmentColumnGateway::class)->selectBy(['tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID, 'tawasulCourseClassID' => $tawasulCourseClassID]);

            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            if ($result->rowCount() != 1) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                $row = $result->fetch();

                $name = $row['name'];
                $count = $_POST['count'] ?? '';
                $partialFail = false;
                $attainment = $row['attainment'];
                $tawasulScaleIDAttainment = $row['tawasulScaleIDAttainment'];
                $effort = $row['effort'];
                $tawasulScaleIDEffort = $row['tawasulScaleIDEffort'];
                $comment = $row['comment'];
                $uploadedResponse = $row['uploadedResponse'];

                for ($i = 1;$i <= $count;++$i) {
                    $tawasulPersonIDStudent = $_POST["$i-tawasulPersonID"] ?? '';
                    //Attainment
                    if ($attainment == 'N') {
                        $attainmentValue = null;
                        $attainmentDescriptor = null;
                    } elseif ($tawasulScaleIDAttainment == '') {
                        $attainmentValue = '';
                        $attainmentDescriptor = '';
                    } else {
                        $attainmentValue = $_POST["$i-attainmentValue"] ?? '';
                    }
                    //Effort
                    if ($effort == 'N') {
                        $effortValue = null;
                        $effortDescriptor = null;
                    } elseif ($tawasulScaleIDEffort == '') {
                        $effortValue = '';
                        $effortDescriptor = '';
                    } else {
                        $effortValue = $_POST["$i-effortValue"] ?? '';
                    }
                    //Comment
                    if ($comment != 'Y') {
                        $commentValue = null;
                    } else {
                        $commentValue = $_POST["comment$i"] ?? '';
                    }
                    $tawasulPersonIDLastEdit = $session->get('tawasulPersonID');

                    //SET AND CALCULATE FOR ATTAINMENT
                    if ($attainment == 'Y' and $tawasulScaleIDAttainment != '') {
                        //Without personal warnings
                        $attainmentDescriptor = '';
                        if ($attainmentValue != '') {
                            $lowestAcceptableAttainment = $_POST['lowestAcceptableAttainment'] ?? '';
                            $scaleAttainment = $_POST['scaleAttainment'] ?? '';
                            try {
                                $resultScale = $container->get(GradeScaleGateway::class)->getScaleGradeByScaleAttainmentAndValue($attainmentValue, $scaleAttainment);

                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                            if (empty($resultScale)) {
                                $partialFail = true;
                            } else {
                                $rowScale = $resultScale;
                                $sequence = $rowScale['sequenceNumber'];
                                $attainmentDescriptor = $rowScale['descriptor'];
                            }
                        }
                    }

                    //SET AND CALCULATE FOR EFFORT
                    if ($effort == 'Y' and $tawasulScaleIDEffort != '') {
                        $effortDescriptor = '';
                        if ($effortValue != '') {
                            $lowestAcceptableEffort = $_POST['lowestAcceptableEffort'] ?? '';
                            $scaleEffort = $_POST['scaleEffort'] ?? '';
                            try {
                                $resultScale = $container->get(GradeScaleGateway::class)->getScaleGradeByScaleEffortAndValue($effortValue, $scaleEffort);

                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                            if (empty($resultScale)) {
                                $partialFail = true;
                            } else {
                                $rowScale = $resultScale;
                                $sequence = $rowScale['sequenceNumber'];
                                $effortDescriptor = $rowScale['descriptor'];
                            }
                        }
                    }

                    $time = time();

                    $selectFail = false;
                    $result = $container->get(InternalAssessmentColumnGateway::class)->selectInternalAssessmentEntry($tawasulInternalAssessmentColumnID, $tawasulPersonIDStudent);
                    if (!($selectFail)) {
                        $entry = $result->rowCount() > 0 ? $result->fetch() : [];

                        $attachment = $entry['response'] ?? null;
                        $fileMetaDataResponse = null;

                        //Move attached file, if there is one
                        if ($uploadedResponse == 'Y') {
                            if (!empty($_FILES["response$i"]['tmp_name'])) {
                                $fileUploader = new TawasulOS\FileUploader($pdo, $session);

                                $file = (isset($_FILES["response$i"]))? $_FILES["response$i"] : null;

                                // Upload the file, return the /uploads relative path
                                $attachment = $fileUploader->uploadFromPost($file, $name.'_Uploaded Response');

                                if (empty($attachment)) {
                                    $partialFail = true;
                                } else {
                                    $fileMetaDataResponse = $fileUploader->getFileMetaData($attachment);
                                }
                            } else {
                                // Remove the attachment if it has been deleted, otherwise retain the original value
                                $attachment = empty($_POST["attachment$i"]) ? null : $attachment;
                            }
                        }

                        if (empty($entry)) {
                            try {
                                $data = array('tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID, 'tawasulPersonIDStudent' => $tawasulPersonIDStudent, 'attainmentValue' => $attainmentValue, 'attainmentDescriptor' => $attainmentDescriptor, 'effortValue' => $effortValue, 'effortDescriptor' => $effortDescriptor, 'comment' => $commentValue, 'attachment' => $attachment, 'tawasulPersonIDLastEdit' => $tawasulPersonIDLastEdit);
                                $sql = 'INSERT INTO tawasulInternalAssessmentEntry SET tawasulInternalAssessmentColumnID=:tawasulInternalAssessmentColumnID, tawasulPersonIDStudent=:tawasulPersonIDStudent, attainmentValue=:attainmentValue, attainmentDescriptor=:attainmentDescriptor, effortValue=:effortValue, effortDescriptor=:effortDescriptor, comment=:comment, response=:attachment, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);

                                $tawasulInternalAssessmentEntryID = $connection2->lastInsertID();
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        } else {
                            //Update
                            try {
                                $tawasulInternalAssessmentEntryID = $entry['tawasulInternalAssessmentEntryID'];
                                $data = array('tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID, 'tawasulPersonIDStudent' => $tawasulPersonIDStudent, 'attainmentValue' => $attainmentValue, 'attainmentDescriptor' => $attainmentDescriptor, 'comment' => $commentValue, 'attachment' => $attachment, 'effortValue' => $effortValue, 'effortDescriptor' => $effortDescriptor, 'tawasulPersonIDLastEdit' => $tawasulPersonIDLastEdit, 'tawasulInternalAssessmentEntryID' => $tawasulInternalAssessmentEntryID);
                                $sql = 'UPDATE tawasulInternalAssessmentEntry SET tawasulInternalAssessmentColumnID=:tawasulInternalAssessmentColumnID, tawasulPersonIDStudent=:tawasulPersonIDStudent, attainmentValue=:attainmentValue, attainmentDescriptor=:attainmentDescriptor, effortValue=:effortValue, effortDescriptor=:effortDescriptor, comment=:comment, response=:attachment, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit WHERE tawasulInternalAssessmentEntryID=:tawasulInternalAssessmentEntryID';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        }

                        // Record file tracking for Upload Case
                        if (!empty($fileMetaDataResponse) && !empty($tawasulInternalAssessmentEntryID)) {
                            $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaDataResponse, 'tawasulInternalAssessmentEntry', $tawasulInternalAssessmentEntryID, 'response');
                            if (empty($tawasulFileID)) {
                                $partialFail = true;
                            }
                        }

                        // Handle file deletion when user removes attachment
                        if (empty($attachment) && !empty($entry['response'])) {
                            $deleted = $container->get(FileHandler::class)->deleteFile('tawasulInternalAssessmentEntry', $tawasulInternalAssessmentEntryID, 'response');
                        }
                    }
                }

                //Update column
                $description = $_POST['description'] ?? '';
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
                    $attachment = empty($_POST['attachment']) ? null : $row['attachment'];
                }

                $completeDate = $_POST['completeDate'] ?? '';
                if ($completeDate == '') {
                    $completeDate = null;
                    $complete = 'N';
                } else {
                    $completeDate = Format::dateConvert($completeDate);
                    $complete = 'Y';
                }
                try {
                    $data = array('attachment' => $attachment, 'description' => $description, 'completeDate' => $completeDate, 'complete' => $complete, 'tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID);
                    $sql = 'UPDATE tawasulInternalAssessmentColumn SET attachment=:attachment, description=:description, completeDate=:completeDate, complete=:complete WHERE tawasulInternalAssessmentColumnID=:tawasulInternalAssessmentColumnID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $partialFail = true;
                }

                // Handle file deletion when user removes attachment
                if (empty($attachment) && !empty($row['attachment'])) {
                    $deleted = $container->get(FileHandler::class)->deleteFile('tawasulInternalAssessmentColumn', $tawasulInternalAssessmentColumnID, 'attachment');
                }

                // Record file tracking for column attachment UPDATE
                if (!empty($fileMetaData) && !empty($tawasulInternalAssessmentColumnID)) {
                   $tawasulFileID =  $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulInternalAssessmentColumn', $tawasulInternalAssessmentColumnID, 'attachment');

                   if (empty($tawasulFileID)) {
                       $partialFail = true;
                   }
                }

                //Return!
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
