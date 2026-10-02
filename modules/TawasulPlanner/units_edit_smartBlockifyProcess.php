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
$tawasulCourseID = $_POST['tawasulCourseID'] ?? '';
$tawasulUnitID = $_POST['tawasulUnitID'] ?? '';
$tawasulUnitClassID = $_POST['tawasulUnitClassID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/units_edit_smartBlockify.php&tawasulUnitID=$tawasulUnitID&tawasulCourseID=$tawasulCourseID&tawasulCourseClassID=$tawasulCourseClassID&tawasulSchoolYearID=$tawasulSchoolYearID";
$URLCopy = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/units_edit.php&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseID=$tawasulCourseID&tawasulUnitID=$tawasulUnitID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_edit_smartBlockify.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        //Proceed!
        if ($tawasulSchoolYearID == '' or $tawasulCourseID == '' or $tawasulCourseClassID == '' or $tawasulUnitID == '' or $tawasulUnitClassID == '') {
            $URL .= '&return=error1';
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
                    //Ready to let loose with the real logic
                    //GET ALL LESSONS IN UNIT, IN ORDER
                    try {
                        $data = array('tawasulUnitID' => $tawasulUnitID, 'tawasulCourseClassID' => $tawasulCourseClassID);
                        $sql = 'SELECT tawasulPlannerEntryID, name, description, teachersNotes, timeStart, timeEnd, date FROM tawasulPlannerEntry WHERE tawasulUnitID=:tawasulUnitID AND tawasulCourseClassID=:tawasulCourseClassID ORDER BY date';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    $sequenceNumber = 9999;
                    $partialFail = false;
                    while ($row = $result->fetch()) {
                        $blockFail = false;
                        ++$sequenceNumber;
                        $length = (strtotime($row['date'].' '.$row['timeEnd']) - strtotime($row['date'].' '.$row['timeStart'])) / 60;

                        //MAKE NEW BLOCK
                        try {
                            $dataBlock = array('tawasulUnitID' => $tawasulUnitID, 'title' => $row['name'], 'type' => '', 'length' => $length, 'contents' => $row['description'], 'teachersNotes' => $row['teachersNotes'], 'sequenceNumber' => $sequenceNumber);
                            $sqlBlock = 'INSERT INTO tawasulUnitBlock SET tawasulUnitID=:tawasulUnitID, title=:title, type=:type, length=:length, contents=:contents, teachersNotes=:teachersNotes, sequenceNumber=:sequenceNumber';
                            $resultBlock = $connection2->prepare($sqlBlock);
                            $resultBlock->execute($dataBlock);
                        } catch (PDOException $e) {
                            $partialFail = true;
                            $blockFail = true;
                        }

                        if ($blockFail == false) {
                            //TURN MASTER BLOCK INTO A WORKING BLOCK, ATTACHING IT TO LESSON
                            $tawasulUnitBlockID = $connection2->lastInsertID();
                            $blockFail2 = false;
                            try {
                                $dataBlock2 = array('tawasulUnitClassID' => $tawasulUnitClassID, 'tawasulPlannerEntryID' => $row['tawasulPlannerEntryID'], 'tawasulUnitBlockID' => $tawasulUnitBlockID, 'title' => $row['name'], 'type' => '', 'length' => $length, 'contents' => $row['description'], 'teachersNotes' => $row['teachersNotes'], 'sequenceNumber' => 1);
                                $sqlBlock2 = 'INSERT INTO tawasulUnitClassBlock SET tawasulUnitClassID=:tawasulUnitClassID, tawasulPlannerEntryID=:tawasulPlannerEntryID, tawasulUnitBlockID=:tawasulUnitBlockID, title=:title, type=:type, length=:length, contents=:contents, teachersNotes=:teachersNotes, sequenceNumber=:sequenceNumber';
                                $resultBlock2 = $connection2->prepare($sqlBlock2);
                                $resultBlock2->execute($dataBlock2);
                            } catch (PDOException $e) {
                                $partialFail = true;
                                $blockFail2 = true;
                            }

                            if ($blockFail2 == false) {
                                //REWRITE LESSON TO REMOVE description AND teachersNotes
                                try {
                                    $dataRewrite = array('tawasulPlannerEntryID' => $row['tawasulPlannerEntryID']);
                                    $sqlRewrite = "UPDATE tawasulPlannerEntry SET description='', teachersNotes='' WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID";
                                    $resultRewrite = $connection2->prepare($sqlRewrite);
                                    $resultRewrite->execute($dataRewrite);
                                } catch (PDOException $e) {
                                    $partialFail = true;
                                }
                            }
                        }
                    }

                    if ($partialFail == true) {
                        $URL .= '&copyReturn=error6';
                        header("Location: {$URL}");
                    } else {
                        $URLCopy = $URLCopy.'&copyReturn=success0';
                        header("Location: {$URLCopy}");
                    }
                }
            }
        }
    }
}
