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

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['link' => 'URL']);

//Module includes
include './moduleFunctions.php';

$tawasulPlannerEntryID = $_POST['tawasulPlannerEntryID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/planner_view_full.php&tawasulPlannerEntryID=$tawasulPlannerEntryID&search=".$_POST['search'].($_POST['params'] ?? '');

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_view_full_submit_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if planner specified
        if ($tawasulPlannerEntryID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                $sql = 'SELECT * FROM tawasulPlannerEntry WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
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
                if ($_POST['submission'] != 'true' and $_POST['submission'] != 'false') {
                    $URL .= '&return=error1';
                    header("Location: {$URL}");
                } else {
                    if ($_POST['submission'] == 'true') {
                        $submission = true;
                        $tawasulPlannerEntryHomeworkID = $_POST['tawasulPlannerEntryHomeworkID'] ?? '';
                    } else {
                        $submission = false;
                        $tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
                    }

                    $type = $_POST['type'] ?? '';
                    $version = $_POST['version'] ?? '';
                    $link = $_POST['link'] ?? '';
                    $status = $_POST['status'] ?? '';
                    $tawasulPlannerEntryID = $_POST['tawasulPlannerEntryID'] ?? '';
                    $count = $_POST['count'] ?? '';
                    $lesson = $_POST['lesson'] ?? '';


                    if (($submission == true and $tawasulPlannerEntryHomeworkID == '') or ($submission == false and ($tawasulPersonID == '' or $type == '' or $version == '' or ($type == 'File' and $_FILES['file']['name'] == '') or ($type == 'Link' and $link == '') or $status == '' or $lesson == '' or $count == ''))) {
                        $URL .= '&return=error1';
                        header("Location: {$URL}");
                    } else {
                        if ($submission == true) {
                            try {
                                $data = array('status' => $status, 'tawasulPlannerEntryHomeworkID' => $tawasulPlannerEntryHomeworkID);
                                $sql = 'UPDATE tawasulPlannerEntryHomework SET status=:status WHERE tawasulPlannerEntryHomeworkID=:tawasulPlannerEntryHomeworkID';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $URL .= '&return=error2';
                                header("Location: {$URL}");
                                exit();
                            }
                            $URL .= '&return=success0';
                            header("Location: {$URL}");
                        } else {
                            $partialFail = false;
                            $fileMetaData = null;
                            $attachment = null;
                            if ($type == 'Link') {
                                if (substr($link, 0, 7) != 'http://' and substr($link, 0, 8) != 'https://') {
                                    $partialFail = true;
                                } else {
                                    $attachment = $link;
                                }
                            }
                            if ($type == 'File') {
                                $fileUploader = new TawasulOS\FileUploader($pdo, $session);

                                $file = (isset($_FILES['file']))? $_FILES['file'] : null;

                                // Upload the file, return the /uploads relative path
                                $attachment = $fileUploader->uploadFromPost($file, $session->get('username').'_'.$lesson);

                                if (empty($attachment)) {
                                    $partialFail = true;
                                } else {
                                    $fileMetaData = $fileUploader->getFileMetaData($attachment);
                                }
                            }

                            //Deal with partial fail
                            if ($partialFail == true) {
                                $URL .= '&return=error6';
                                header("Location: {$URL}");
                            } else {
                                //Write to database
                                try {
                                    $data = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulPersonID' => $tawasulPersonID, 'type' => $type, 'version' => $version, 'status' => $status, 'location' => $attachment, 'count' => ($count + 1), 'timestamp' => date('Y-m-d H:i:s'));
                                    $sql = 'INSERT INTO tawasulPlannerEntryHomework SET tawasulPlannerEntryID=:tawasulPlannerEntryID, tawasulPersonID=:tawasulPersonID, type=:type, version=:version, status=:status, location=:location, count=:count, timestamp=:timestamp';
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                                } catch (PDOException $e) {
                                    $URL .= '&return=error2';
                                    header("Location: {$URL}");
                                    exit();
                                }

                                $tawasulPlannerEntryHomeworkID = $connection2->lastInsertID();

                                // Record file tracking (only if file uploaded)
                                if (!empty($fileMetaData) && !empty($tawasulPlannerEntryHomeworkID)) {
                                    $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulPlannerEntryHomework', $tawasulPlannerEntryHomeworkID, 'location');

                                    if (empty($tawasulFileID)) {
                                        $partialFail = true;
                                    }
                                }

                                $URL .= '&return=success0';
                                header("Location: {$URL}");
                            }
                        }
                    }
                }
            }
        }
    }
}
