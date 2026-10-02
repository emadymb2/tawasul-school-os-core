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
use TawasulOS\Domain\Timetable\CourseGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['details' => 'HTML', 'contents*' => 'HTML', 'teachersNotes*' => 'HTML']);

$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
$tawasulUnitID = $_GET['tawasulUnitID'] ?? '';
$classCount = $_POST['classCount'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address'])."/units_edit.php&tawasulUnitID=$tawasulUnitID&tawasulCourseID=$tawasulCourseID&tawasulSchoolYearID=$tawasulSchoolYearID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        if (empty($_POST)) {
            $URL .= '&return=warning1';
            header("Location: {$URL}");
        } else {
            //Proceed!

            //Validate Inputs
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $tags = $_POST['tags'] ?? '';
            $active = $_POST['active'] ?? '';
            $map = $_POST['map'] ?? '';
            $ordering = $_POST['ordering'] ?? '';
            $details = $_POST['details'] ?? '';
            $license = $_POST['license'] ?? '';
            $sharedPublic = $_POST['sharedPublic'] ?? '';


            if ($tawasulSchoolYearID == '' or $tawasulCourseID == '' or $tawasulUnitID == '' or $name == '' or $description == '' or $active == '' or $map == '' or $ordering == '') {
                $URL .= '&return=error3';
                header("Location: {$URL}");
            } else {
                $courseGateway = $container->get(CourseGateway::class);

                // Check access to specified course
                if ($highestAction == 'Unit Planner_all') {
                    $result = $courseGateway->selectCourseDetailsByCourse($tawasulCourseID);
                } elseif ($highestAction == 'Unit Planner_learningAreas') {
                    $result = $courseGateway->selectCourseDetailsByCourseAndPerson($tawasulCourseID, $session->get('tawasulPersonID'));
                }

                if ($result->rowCount() != 1) {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                } else {
                    //Check existence of specified unit
                    try {
                        $data = array('tawasulUnitID' => $tawasulUnitID, 'tawasulCourseID' => $tawasulCourseID);
                        $sql = 'SELECT * FROM tawasulUnit WHERE tawasulUnitID=:tawasulUnitID AND tawasulCourseID=:tawasulCourseID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    if ($result->rowCount() != 1) {
                        $URL .= '&return=error3';
                        header("Location: {$URL}");
                    } else {
                        $row = $result->fetch();
                        $fileHandler = $container->get(FileHandler::class);
                        $partialFail = false;
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
                                $content = $attachment;
                                $fileMetaData = $fileUploader->getFileMetaData($attachment);
                            }
                        } else {
                            // Remove the attachment if it has been deleted, otherwise retain the original value
                            $attachment = empty($_POST['attachment']) ? null : $row['attachment'];
                        }

                        //Update classes
                        if ($classCount > 0) {
                            for ($i = 0;$i < $classCount;++$i) {
                                $running = $_POST['running'.$i];
                                if ($running != 'Y' and $running != 'N') {
                                    $running = 'N';
                                }

                                //Check to see if entry exists
                                try {
                                    $dataUnitClass = array('tawasulUnitID' => $tawasulUnitID, 'tawasulCourseClassID' => $_POST['tawasulCourseClassID'.$i]);
                                    $sqlUnitClass = 'SELECT * FROM tawasulUnitClass WHERE tawasulUnitID=:tawasulUnitID AND tawasulCourseClassID=:tawasulCourseClassID';
                                    $resultUnitClass = $connection2->prepare($sqlUnitClass);
                                    $resultUnitClass->execute($dataUnitClass);
                                } catch (PDOException $e) {
                                    $partialFail = true;
                                }

                                if ($resultUnitClass->rowCount() > 0) {
                                    try {
                                        $dataClass = array('running' => $running, 'tawasulUnitID' => $tawasulUnitID, 'tawasulCourseClassID' => $_POST['tawasulCourseClassID'.$i]);
                                        $sqlClass = 'UPDATE tawasulUnitClass SET running=:running WHERE tawasulUnitID=:tawasulUnitID AND tawasulCourseClassID=:tawasulCourseClassID';
                                        $resultClass = $connection2->prepare($sqlClass);
                                        $resultClass->execute($dataClass);
                                    } catch (PDOException $e) {
                                        $partialFail = true;
                                    }
                                } else {
                                    try {
                                        $dataClass = array('running' => $running, 'tawasulUnitID' => $tawasulUnitID, 'tawasulCourseClassID' => $_POST['tawasulCourseClassID'.$i]);
                                        $sqlClass = 'INSERT INTO tawasulUnitClass SET tawasulUnitID=:tawasulUnitID, tawasulCourseClassID=:tawasulCourseClassID, running=:running';
                                        $resultClass = $connection2->prepare($sqlClass);
                                        $resultClass->execute($dataClass);
                                    } catch (PDOException $e) {
                                        $partialFail = true;
                                    }
                                }
                            }
                        }

                        //Update blocks
                        $order = $_POST['order'] ?? [];
                        $sequenceNumber = 0;
                        $dataRemove = array();
                        $whereRemove = '';

                        if (!empty($order) && is_array($order)) {
                            foreach ($order as $i) {
                                $title = '';
                                if ($_POST["title$i"] != "Block $i") {
                                    $title = $_POST["title$i"] ?? '';
                                }
                                $type2 = '';
                                if ($_POST["type$i"] != 'type (e.g. discussion, outcome)') {
                                    $type2 = $_POST["type$i"] ?? '';
                                }
                                $length = '';
                                if ($_POST["length$i"] != 'length (min)') {
                                    $length = $_POST["length$i"] ?? '';
                                }
                                $contents = $_POST["contents$i"] ?? '';
                                $teachersNotes = $_POST["teachersNotes$i"] ?? '';
                                $tawasulUnitBlockID = $_POST["tawasulUnitBlockID$i"] ?? '';

                                if ($tawasulUnitBlockID != '') {
                                    try {
                                        $dataBlock = array('tawasulUnitID' => $tawasulUnitID, 'title' => $title, 'type' => $type2, 'length' => $length, 'contents' => $contents, 'teachersNotes' => $teachersNotes, 'sequenceNumber' => $sequenceNumber, 'tawasulUnitBlockID' => $tawasulUnitBlockID);
                                        $sqlBlock = 'UPDATE tawasulUnitBlock SET tawasulUnitID=:tawasulUnitID, title=:title, type=:type, length=:length, contents=:contents, teachersNotes=:teachersNotes, sequenceNumber=:sequenceNumber WHERE tawasulUnitBlockID=:tawasulUnitBlockID';
                                        $resultBlock = $connection2->prepare($sqlBlock);
                                        $resultBlock->execute($dataBlock);
                                    } catch (PDOException $e) {
                                        $partialFail = true;
                                    }
                                    $dataRemove["tawasulUnitBlockID$sequenceNumber"] = $tawasulUnitBlockID;
                                    $whereRemove .= "AND NOT tawasulUnitBlockID=:tawasulUnitBlockID$sequenceNumber ";
                                } else {
                                    try {
                                        $dataBlock = array('tawasulUnitID' => $tawasulUnitID, 'title' => $title, 'type' => $type2, 'length' => $length, 'contents' => $contents, 'teachersNotes' => $teachersNotes, 'sequenceNumber' => $sequenceNumber);
                                        $sqlBlock = 'INSERT INTO tawasulUnitBlock SET tawasulUnitID=:tawasulUnitID, title=:title, type=:type, length=:length, contents=:contents, teachersNotes=:teachersNotes, sequenceNumber=:sequenceNumber';
                                        $resultBlock = $connection2->prepare($sqlBlock);
                                        $resultBlock->execute($dataBlock);
                                    } catch (PDOException $e) {
                                        $partialFail = true;
                                    }
                                    $dataRemove["tawasulUnitBlockID$sequenceNumber"] = $connection2->lastInsertId();
                                    $whereRemove .= "AND NOT tawasulUnitBlockID=:tawasulUnitBlockID$sequenceNumber ";
                                }

                                ++$sequenceNumber;
                            }
                        }
                        

                        //Remove orphaned blocks
                        if ($whereRemove != '(') {
                            try {
                                $dataRemove['tawasulUnitID'] = $tawasulUnitID;
                                $sqlRemove = "DELETE FROM tawasulUnitBlock WHERE tawasulUnitID=:tawasulUnitID $whereRemove";
                                $resultRemove = $connection2->prepare($sqlRemove);
                                $resultRemove->execute($dataRemove);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        }

                        //Delete all outcomes
                        try {
                            $dataDelete = array('tawasulUnitID' => $tawasulUnitID);
                            $sqlDelete = 'DELETE FROM tawasulUnitOutcome WHERE tawasulUnitID=:tawasulUnitID';
                            $resultDelete = $connection2->prepare($sqlDelete);
                            $resultDelete->execute($dataDelete);
                        } catch (PDOException $e) {
                            $URL .= '&return=error2';
                            header("Location: {$URL}");
                            exit();
                        }
                        //Insert outcomes
                        $count = 0;
                        $outcomeorder = $_POST['outcomeorder'] ?? [];
                        if (is_array($outcomeorder) && count($outcomeorder) > 0) {
                            foreach ($outcomeorder as $outcome) {
                                if ($_POST["outcometawasulOutcomeID$outcome"] != '') {
                                    try {
                                        $dataInsert = array('tawasulUnitID' => $tawasulUnitID, 'tawasulOutcomeID' => $_POST["outcometawasulOutcomeID$outcome"], 'content' => $_POST["outcomecontents$outcome"], 'count' => $count);
                                        $sqlInsert = 'INSERT INTO tawasulUnitOutcome SET tawasulUnitID=:tawasulUnitID, tawasulOutcomeID=:tawasulOutcomeID, content=:content, sequenceNumber=:count';
                                        $resultInsert = $connection2->prepare($sqlInsert);
                                        $resultInsert->execute($dataInsert);
                                    } catch (PDOException $e) {
                                        echo $e;
                                        $partialFail = true;
                                    }
                                }
                                ++$count;
                            }
                        }

                        //Write to database
                        try {
                            $data = array('name' => $name, 'attachment' => $attachment, 'description' => $description, 'tags' => $tags, 'active' => $active, 'map' => $map, 'ordering' => $ordering, 'details' => $details, 'license' => $license, 'sharedPublic' => $sharedPublic, 'tawasulPersonIDLastEdit' => $session->get('tawasulPersonID'), 'tawasulUnitID' => $tawasulUnitID);
                            $sql = 'UPDATE tawasulUnit SET name=:name, attachment=:attachment, description=:description, tags=:tags, active=:active, map=:map, ordering=:ordering, details=:details, license=:license, sharedPublic=:sharedPublic, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit WHERE tawasulUnitID=:tawasulUnitID';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $URL .= '&return=error2';
                            header("Location: {$URL}");
                            exit();
                        }

                        // Handle file deletion when user removes attachment
                        if (empty($attachment) && !empty($row['attachment'])) {
                            $deleted = $fileHandler->deleteFile('tawasulUnit', $tawasulUnitID, 'attachment');
                        }

                        // Record file tracking
                        if (!empty($fileMetaData) && !empty($tawasulUnitID)) {
                            $tawasulFileID = $fileHandler->recordFileUpload($fileMetaData, 'tawasulUnit', $tawasulUnitID, 'attachment');

                            if (empty($tawasulFileID)) {
                                $partialFail = true;
                            }
                        }

                        if ($partialFail) {
                            $URL .= '&updateReturn=error6';
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
}
