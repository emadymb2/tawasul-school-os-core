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

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? '';
$tawasulCourseClassID = $_POST['tawasulCourseClassID'] ?? '';
$tawasulCourseID = $_POST['tawasulCourseID'] ?? '' ?? '';
$tawasulUnitID = $_POST['tawasulUnitID'] ?? '';
$tawasulSchoolYearIDCopyTo = $_POST['tawasulSchoolYearIDCopyTo'] ?? '';
$tawasulCourseIDTarget = $_POST['tawasulCourseIDTarget'] ?? '';
$nameTarget = $_POST['nameTarget'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/units_edit_copyForward.php&tawasulUnitID=$tawasulUnitID&tawasulCourseID=$tawasulCourseID&tawasulCourseClassID=$tawasulCourseClassID&tawasulSchoolYearID=$tawasulSchoolYearID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_edit_copyForward.php') == false) {
    $URL .= '&copyForwardReturn=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&copyForwardReturn=error0$params";
        header("Location: {$URL}");
    } else {
        //Proceed!
        if ($tawasulSchoolYearID == '' or $tawasulCourseID == '' or $tawasulCourseClassID == '' or $tawasulUnitID == '' or $tawasulSchoolYearIDCopyTo == '' or $tawasulCourseIDTarget == '' or $nameTarget == '') {
            $URL .= '&copyForwardReturn=error3';
            header("Location: {$URL}");
        } else {
            $courseGateway = $container->get(CourseGateway::class);

            // Check access to specified course
            if ($highestAction == 'Unit Planner_all') {
                $result = $courseGateway->selectCourseDetailsByClass($tawasulCourseClassID);
            } elseif ($highestAction == 'Unit Planner_learningAreas') {
                $result = $courseGateway->selectCourseDetailsByClassAndPerson($tawasulCourseClassID, $session->get('tawasulPersonID'));
            }

            if ($result->rowCount() != 1) {
                $URL .= '&copyForwardReturn=error4';
                header("Location: {$URL}");
            } else {
                //Check existence of specified unit
                try {
                    $data = array('tawasulUnitID' => $tawasulUnitID, 'tawasulCourseID' => $tawasulCourseID);
                    $sql = 'SELECT * FROM tawasulUnit WHERE tawasulUnitID=:tawasulUnitID AND tawasulCourseID=:tawasulCourseID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&copyForwardReturn=error2';
                    header("Location: {$URL}");
                    exit();
                }
                if ($result->rowCount() != 1) {
                    $URL .= '&copyForwardReturn=error4';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    $row = $result->fetch();
                    $partialFail = false;

                    //Create new unit
                    try {
                        $data = array('tawasulCourseID' => $tawasulCourseIDTarget, 'name' => $nameTarget, 'description' => $row['description'], 'attachment' => $row['attachment'], 'details' => $row['details'], 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'), 'tawasulPersonIDLastEdit' => $session->get('tawasulPersonID'));
                        $sql = 'INSERT INTO tawasulUnit SET tawasulCourseID=:tawasulCourseID, name=:name, description=:description, attachment=:attachment, details=:details, tawasulPersonIDCreator=:tawasulPersonIDCreator, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&copyForwardReturn=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    //Get new unit ID
                    $gibbinUnitIDNew = $connection2->lastInsertID();

                    if ($gibbinUnitIDNew == '') {
                        $partialFail = true;
                    } else {
                        //Read blocks from old unit
                        try {
                            $dataBlocks = array('tawasulUnitID' => $tawasulUnitID, 'tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulCourseClassID' => $tawasulCourseClassID);
                            $sqlBlocks = 'SELECT * FROM tawasulUnitClass JOIN tawasulUnitClassBlock ON (tawasulUnitClassBlock.tawasulUnitClassID=tawasulUnitClass.tawasulUnitClassID) JOIN tawasulPlannerEntry ON (tawasulPlannerEntry.tawasulPlannerEntryID=tawasulUnitClassBlock.tawasulPlannerEntryID) WHERE tawasulUnitClass.tawasulUnitID=:tawasulUnitID AND tawasulUnitClass.tawasulCourseClassID=:tawasulCourseClassID AND tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID ORDER BY sequenceNumber';
                            $resultBlocks = $connection2->prepare($sqlBlocks);
                            $resultBlocks->execute($dataBlocks);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }

                        //Write blocks to new unit
                        while ($rowBlocks = $resultBlocks->fetch()) {
                            try {
                                $dataBlock = array('tawasulUnitID' => $gibbinUnitIDNew, 'title' => $rowBlocks['title'], 'type' => $rowBlocks['type'], 'length' => $rowBlocks['length'], 'contents' => $rowBlocks['contents'], 'sequenceNumber' => $rowBlocks['sequenceNumber']);
                                $sqlBlock = 'INSERT INTO tawasulUnitBlock SET tawasulUnitID=:tawasulUnitID, title=:title, type=:type, length=:length, contents=:contents, sequenceNumber=:sequenceNumber';
                                $resultBlock = $connection2->prepare($sqlBlock);
                                $resultBlock->execute($dataBlock);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        }

                        //Read outcomes from old unit
                        try {
                            $dataOutcomes = array('tawasulUnitID' => $tawasulUnitID);
                            $sqlOutcomes = 'SELECT * FROM tawasulUnitOutcome WHERE tawasulUnitID=:tawasulUnitID';
                            $resultOutcomes = $connection2->prepare($sqlOutcomes);
                            $resultOutcomes->execute($dataOutcomes);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }

                        //Write outcomes to new unit
                        if ($resultOutcomes->rowCount() > 0) {
                            while ($rowOutcomes = $resultOutcomes->fetch()) {
                                //Write to database
                                try {
                                    $dataCopy = array('tawasulUnitID' => $gibbinUnitIDNew, 'tawasulOutcomeID' => $rowOutcomes['tawasulOutcomeID'], 'sequenceNumber' => $rowOutcomes['sequenceNumber'], 'content' => $rowOutcomes['content']);
                                    $sqlCopy = 'INSERT INTO tawasulUnitOutcome SET tawasulUnitID=:tawasulUnitID, tawasulOutcomeID=:tawasulOutcomeID, sequenceNumber=:sequenceNumber, content=:content';
                                    $resultCopy = $connection2->prepare($sqlCopy);
                                    $resultCopy->execute($dataCopy);
                                } catch (PDOException $e) {
                                    $partialFail = true;
                                }
                            }
                        }
                    }

                    if ($partialFail == true) {
                        $URL .= '&copyForwardReturn=error6';
                        header("Location: {$URL}");
                    } else {
                        $URLCopy = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/units_edit.php&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseID=$tawasulCourseIDTarget&tawasulUnitID=$gibbinUnitIDNew";
                        $URLCopy = $URLCopy.'&return=success0';
                        header("Location: {$URLCopy}");
                    }
                }
            }
        }
    }
}
