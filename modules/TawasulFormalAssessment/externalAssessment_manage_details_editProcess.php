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
use TawasulOS\Domain\FormalAssessment\ExternalAssessmentStudentGateway;
use TawasulOS\Contracts\Filesystem\FileHandler;
use TawasulOS\Services\Format;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
$tawasulExternalAssessmentStudentID = $_POST['tawasulExternalAssessmentStudentID'] ?? '';
$search = $_GET['search'] ?? '';
$allStudents = $_GET['allStudents'] ?? '';


if ($tawasulPersonID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/externalAssessment_manage_details_edit.php&tawasulPersonID=$tawasulPersonID&tawasulExternalAssessmentStudentID=$tawasulExternalAssessmentStudentID&search=$search&allStudents=$allStudents";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulFormalAssessment/externalAssessment_manage_details_edit.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if tt specified
        if ($tawasulExternalAssessmentStudentID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {                
                $result = $container->get(ExternalAssessmentStudentGateway::class)->getByID($tawasulExternalAssessmentStudentID);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }
            if (empty($result)) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                $row = $result;

                //Validate Inputs
                $count = 0;
                if (is_numeric($_POST['count'])) {
                    $count = $_POST['count'] ?? '';
                }
                $date = !empty($_POST['date']) ? Format::dateConvert($_POST['date']) : null;

                //Move attached image  file, if there is one
                $partialFail = false;
                $fileMetaData = null;
                if (!empty($_FILES['file']['tmp_name'])) {
                    $fileUploader = new TawasulOS\FileUploader($pdo, $session);

                    $file = (isset($_FILES['file']))? $_FILES['file'] : null;

                    // Upload the file, return the /uploads relative path
                    $attachment = $fileUploader->uploadFromPost($file, 'externalAssessmentUpload');

                    if (empty($attachment)) {
                        $partialFail = true;
                    } else {
                        $fileMetaData = $fileUploader->getFileMetaData($attachment);
                    }
                } else {
                    // Remove the attachment if it has been deleted, otherwise retain the original value
                    $attachment = empty($_POST['attachment']) ? null : $row['attachment'];
                }

                if ($date == '') {
                    $URL .= '&return=error1';
                    header("Location: {$URL}");
                } else {
                    //Scan through fields
                    for ($i = 0; $i < $count; ++$i) {
                        $tawasulExternalAssessmentStudentEntryID = @$_POST[$i.'-tawasulExternalAssessmentStudentEntryID'];
                        if (isset($_POST[$i.'-tawasulScaleGradeID']) == false) {
                            $tawasulScaleGradeID = null;
                        } else {
                            if ($_POST[$i.'-tawasulScaleGradeID'] == '') {
                                $tawasulScaleGradeID = null;
                            } else {
                                $tawasulScaleGradeID = $_POST[$i.'-tawasulScaleGradeID'];
                            }
                        }
                        if ($tawasulExternalAssessmentStudentEntryID != '') {
                            try {
                                $data = array('tawasulScaleGradeID' => $tawasulScaleGradeID, 'tawasulExternalAssessmentStudentEntryID' => $tawasulExternalAssessmentStudentEntryID);
                                $sql = 'UPDATE tawasulExternalAssessmentStudentEntry SET tawasulScaleGradeID=:tawasulScaleGradeID WHERE tawasulExternalAssessmentStudentEntryID=:tawasulExternalAssessmentStudentEntryID';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        }
                    }

                    //Write to database
                    try {
                        $data = array('date' => $date, 'attachment' => $attachment, 'tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID);
                        $sql = 'UPDATE tawasulExternalAssessmentStudent SET date=:date, attachment=:attachment WHERE tawasulExternalAssessmentStudentID=:tawasulExternalAssessmentStudentID';
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
                        $deleted = $fileHandler->deleteFile('tawasulExternalAssessmentStudent', $tawasulExternalAssessmentStudentID, 'attachment');
                    }

                    // Record file tracking
                    if (!empty($fileMetaData) && !empty($tawasulExternalAssessmentStudentID)) {
                        $tawasulFileID = $fileHandler->recordFileUpload($fileMetaData, 'tawasulExternalAssessmentStudent', $tawasulExternalAssessmentStudentID, 'attachment');

                        if (empty($tawasulFileID)) {
                            $partialFail = true;
                        }
                    }

                    if ($partialFail == true) {
                        $URL .= '&return=warning1';
                        header("Location: {$URL}");
                    } else {
                        $URL .= "&return=success0";
                        header("Location: {$URL}");
                    }
                }
            }
        }
    }
}
