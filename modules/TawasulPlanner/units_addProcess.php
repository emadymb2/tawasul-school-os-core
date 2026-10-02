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

use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Data\Validator;
use TawasulOS\Contracts\Filesystem\FileHandler;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['details' => 'HTML', 'contents*' => 'HTML', 'teachersNotes*' => 'HTML']);

$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
$classCount = $_POST['classCount'] ?? null;
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address'])."/units_add.php&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseID=$tawasulCourseID";
$URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address'])."/units_edit.php&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseID=$tawasulCourseID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        if (empty($_POST)) {
            $URL .= '&return=error6';
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
            $license = $_POST['license'] ?? null;
            $sharedPublic = $_POST['sharedPublic'] ?? '';


            if ($tawasulSchoolYearID == '' or $tawasulCourseID == '' or $name == '' or $description == '' or $active == '' or $map == '' or $ordering == '') {
                $URL .= '&return=error1';
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
                    //Move attached file, if there is one
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
                    } else {
                        $attachment = '';
                    }

                    //Write to database
                    try {
                        $data = array('tawasulCourseID' => $tawasulCourseID, 'name' => $name, 'description' => $description, 'tags' => $tags, 'active' => $active, 'map' => $map, 'ordering' => $ordering, 'license' => $license, 'sharedPublic' => $sharedPublic, 'attachment' => $attachment, 'details' => $details, 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'), 'tawasulPersonIDLastEdit' => $session->get('tawasulPersonID'));
                        $sql = 'INSERT INTO tawasulUnit SET tawasulCourseID=:tawasulCourseID, name=:name, description=:description, tags=:tags, active=:active, map=:map, ordering=:ordering, license=:license, sharedPublic=:sharedPublic, attachment=:attachment, details=:details, tawasulPersonIDCreator=:tawasulPersonIDCreator, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {exit;
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    $AI = $connection2->lastInsertID();

                    $partialFail = false;

                    // Record file tracking (only if file uploaded)
                    if (!empty($fileMetaData) && !empty($AI)) {
                        $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulUnit', $AI, 'attachment');

                        if (empty($tawasulFileID)) {
                            $partialFail = true;
                        }
                    }

                    //ADD CLASS RECORDS
                    if ($classCount > 0) {
                        for ($i = 0;$i < $classCount;++$i) {
                            $running = $_POST['running'.$i];
                            if ($running != 'Y' and $running != 'N') {
                                $running = 'N';
                            }

                            try {
                                $dataClass = array('tawasulUnitID' => $AI, 'tawasulCourseClassID' => $_POST['tawasulCourseClassID'.$i], 'running' => $running);
                                $sqlClass = 'INSERT INTO tawasulUnitClass SET tawasulUnitID=:tawasulUnitID, tawasulCourseClassID=:tawasulCourseClassID, running=:running';
                                $resultClass = $connection2->prepare($sqlClass);
                                $resultClass->execute($dataClass);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        }
                    }

                    //ADD BLOCKS
                    $order = $_POST['order'] ?? [];
                    $sequenceNumber = 0;
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

                            if ($title != '') {
                                try {
                                    $dataBlock = array('tawasulUnitID' => $AI, 'title' => $title, 'type' => $type2, 'length' => $length, 'contents' => $contents, 'teachersNotes' => $teachersNotes, 'sequenceNumber' => $sequenceNumber);
                                    $sqlBlock = 'INSERT INTO tawasulUnitBlock SET tawasulUnitID=:tawasulUnitID, title=:title, type=:type, length=:length, contents=:contents, teachersNotes=:teachersNotes, sequenceNumber=:sequenceNumber';
                                    $resultBlock = $connection2->prepare($sqlBlock);
                                    $resultBlock->execute($dataBlock);
                                } catch (PDOException $e) {
                                    $partialFail = true;
                                }
                                ++$sequenceNumber;
                            }
                        }
                    }

                    //Insert outcomes
                    $count = 0;
                    $outcomeorder = $_POST['outcomeorder'] ?? [];
                    if (count($outcomeorder) > 0) {
                        foreach ($outcomeorder as $outcome) {
                            if ($_POST["outcometawasulOutcomeID$outcome"] != '') {
                                try {
                                    $dataInsert = array('AI' => $AI, 'tawasulOutcomeID' => $_POST["outcometawasulOutcomeID$outcome"], 'content' => $_POST["outcomecontents$outcome"], 'count' => $count);
                                    $sqlInsert = 'INSERT INTO tawasulUnitOutcome SET tawasulUnitID=:AI, tawasulOutcomeID=:tawasulOutcomeID, content=:content, sequenceNumber=:count';
                                    $resultInsert = $connection2->prepare($sqlInsert);
                                    $resultInsert->execute($dataInsert);
                                } catch (PDOException $e) {
                                    $partialFail = true;
                                }
                            }
                            ++$count;
                        }
                    }

                    if ($partialFail == true) {
                        $URL .= '&return=warning1';
                        header("Location: {$URL}");
                    } else {
                        $URLSuccess = $URLSuccess."&return=success3&tawasulUnitID=$AI";
                        header("Location: {$URLSuccess}");
                    }
                }
            }
        }
    }
}
