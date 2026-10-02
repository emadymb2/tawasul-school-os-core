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

//Module includes
include './moduleFunctions.php';

$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
$tawasulUnitID = $_GET['tawasulUnitID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address'])."/units_duplicate.php&tawasulUnitID=$tawasulUnitID&tawasulCourseID=$tawasulCourseID&tawasulSchoolYearID=$tawasulSchoolYearID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_duplicate.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Validate Inputs
        $tawasulCourseIDTarget = $_POST['tawasulCourseIDTarget'] ?? '';
        $copyLessons = $_POST['copyLessons'] ?? '';

        $courseGateway = $container->get(CourseGateway::class);

        // Check access to specified course
        if ($highestAction == 'Unit Planner_all') {
            $result = $courseGateway->selectCourseDetailsByCourse($tawasulCourseID);
        } elseif ($highestAction == 'Unit Planner_learningAreas') {
            $result = $courseGateway->selectCourseDetailsByCourseAndPerson($tawasulCourseID, $session->get('tawasulPersonID'));
        }

        if ($result->rowCount() == 0) {
            $URL .= '&return=error0';
            header("Location: {$URL}");
            exit;
        }

        if ($tawasulSchoolYearID == '' or $tawasulCourseID == '' or $tawasulUnitID == '' or $tawasulCourseIDTarget == '') {
            $URL .= '&return=error3';
            header("Location: {$URL}");
        } else {
            $partialFail = false;

            //Write to database
            try {
                $data = array('tawasulUnitID' => $tawasulUnitID);
                $sql = 'SELECT * FROM tawasulUnit WHERE tawasulUnitID=:tawasulUnitID';
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
                $row = $result->fetch();
                $name = $row['name'];
                if ($tawasulCourseIDTarget == $tawasulCourseID) {
                    $name .= ' (Copy)';
                }
                try {
                    $data = array('tawasulCourseID' => $tawasulCourseIDTarget, 'name' => $name, 'description' => $row['description'], 'map' => $row['map'], 'tags' => $row['tags'], 'ordering' => $row['ordering'], 'attachment' => $row['attachment'], 'details' => $row['details'], 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'), 'tawasulPersonIDLastEdit' => $session->get('tawasulPersonID'));
                    $sql = 'INSERT INTO tawasulUnit SET tawasulCourseID=:tawasulCourseID, name=:name, description=:description, map=:map, tags=:tags, ordering=:ordering, attachment=:attachment, details=:details ,tawasulPersonIDCreator=:tawasulPersonIDCreator, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                $AI = $connection2->lastInsertID();

                //Copy Outcomes
                try {
                    $dataOutcomes = array('tawasulUnitID' => $tawasulUnitID);
                    $sqlOutcomes = 'SELECT * FROM tawasulUnitOutcome WHERE tawasulUnitID=:tawasulUnitID';
                    $resultOutcomes = $connection2->prepare($sqlOutcomes);
                    $resultOutcomes->execute($dataOutcomes);
                } catch (PDOException $e) {
                    $partialFail = true;
                }

                if ($resultOutcomes->rowCount() > 0) {
                    while ($rowOutcomes = $resultOutcomes->fetch()) {
                        //Write to database
                        try {
                            $dataCopy = array('tawasulUnitID' => $AI, 'tawasulOutcomeID' => $rowOutcomes['tawasulOutcomeID'], 'sequenceNumber' => $rowOutcomes['sequenceNumber'], 'content' => $rowOutcomes['content']);
                            $sqlCopy = 'INSERT INTO tawasulUnitOutcome SET tawasulUnitID=:tawasulUnitID, tawasulOutcomeID=:tawasulOutcomeID, sequenceNumber=:sequenceNumber, content=:content';
                            $resultCopy = $connection2->prepare($sqlCopy);
                            $resultCopy->execute($dataCopy);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                    }
                }

                //Copy Lessons & resources
                if ($copyLessons == 'Y') {
                    $tawasulCourseClassIDSource = $_POST['tawasulCourseClassIDSource'] ?? '';
                    $tawasulCourseClassIDTarget = $_POST['tawasulCourseClassIDTarget'] ?? '';


                    if ($tawasulCourseClassIDSource == '' or count($tawasulCourseClassIDTarget) < 1 or $AI == '') {
                        $URL .= '&return=error1';
                        header("Location: {$URL}");
                    } else {
                        foreach ($tawasulCourseClassIDTarget as $t) {
                            //Turn class on
                            try {
                                $dataOn = array('tawasulUnitID' => $AI, 'tawasulCourseClassID' => $t);
                                $sqlOn = "INSERT INTO tawasulUnitClass SET tawasulUnitID=:tawasulUnitID, tawasulCourseClassID=:tawasulCourseClassID, running='Y'";
                                $resultOn = $connection2->prepare($sqlOn);
                                $resultOn->execute($dataOn);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }

                            $tawasulUnitClassIDNew = $connection2->lastInsertID();

                            //Get lessons
                            try {
                                $dataLessons = array('tawasulCourseClassID' => $tawasulCourseClassIDSource, 'tawasulUnitID' => $tawasulUnitID);
                                $sqlLessons = 'SELECT * FROM tawasulPlannerEntry WHERE tawasulCourseClassID=:tawasulCourseClassID AND tawasulUnitID=:tawasulUnitID';
                                $resultLessons = $connection2->prepare($sqlLessons);
                                $resultLessons->execute($dataLessons);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }

                            if ($resultLessons->rowCount() > 0) {
                                //Copy Lessons
                                while ($rowLesson = $resultLessons->fetch()) {
                                    $copyOK = true;
                                    //Write to database
                                    try {
                                        $dataCopy = array('tawasulCourseClassID' => $t, 'tawasulUnitID' => $AI, 'name' => $rowLesson['name'], 'summary' => $rowLesson['summary'], 'description' => $rowLesson['description'], 'teachersNotes' => $rowLesson['teachersNotes'], 'homework' => $rowLesson['homework'], 'homeworkDetails' => $rowLesson['homeworkDetails'], 'homeworkSubmission' => $rowLesson['homeworkSubmission'], 'homeworkSubmissionDrafts' => $rowLesson['homeworkSubmissionDrafts'], 'homeworkSubmissionType' => $rowLesson['homeworkSubmissionType'], 'viewableStudents' => $rowLesson['viewableStudents'], 'viewableParents' => $rowLesson['viewableParents'], 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'), 'tawasulPersonIDLastEdit' => $session->get('tawasulPersonID'));
                                        $sqlCopy = "INSERT INTO tawasulPlannerEntry SET tawasulCourseClassID=:tawasulCourseClassID, tawasulUnitID=:tawasulUnitID, date=NULL, timeStart=NULL, timeEnd=NULL, name=:name, summary=:summary, description=:description, teachersNotes=:teachersNotes, homework=:homework, homeworkDueDateTime=NULL, homeworkDetails=:homeworkDetails, homeworkSubmission=:homeworkSubmission, homeworkSubmissionDateOpen=NULL, homeworkSubmissionDrafts=:homeworkSubmissionDrafts, homeworkSubmissionType=:homeworkSubmissionType, homeworkCrowdAssess='N', homeworkCrowdAssessOtherTeachersRead='N', homeworkCrowdAssessOtherParentsRead='N', homeworkCrowdAssessClassmatesParentsRead='N', homeworkCrowdAssessSubmitterParentsRead='N', homeworkCrowdAssessOtherStudentsRead='N', homeworkCrowdAssessClassmatesRead='N', viewableStudents=:viewableStudents, viewableParents=:viewableParents, tawasulPersonIDCreator=:tawasulPersonIDCreator, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit";
                                        $resultCopy = $connection2->prepare($sqlCopy);
                                        $resultCopy->execute($dataCopy);
                                    } catch (PDOException $e) {
                                        $partialFail = true;
                                        $copyOK = false;
                                    }
                                    if ($copyOK == true) {
                                        //Copy blocks for this lesson
                                        $tawasulPlannerEntryNew = $connection2->lastInsertID();

                                        try {
                                            $dataBlocks = array('tawasulPlannerEntryID' => $rowLesson['tawasulPlannerEntryID']);
                                            $sqlBlocks = 'SELECT * FROM tawasulUnitClassBlock WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID ORDER BY sequenceNumber';
                                            $resultBlocks = $connection2->prepare($sqlBlocks);
                                            $resultBlocks->execute($dataBlocks);
                                        } catch (PDOException $e) {
                                            $partialFail = true;
                                        }
                                        while ($rowBlocks = $resultBlocks->fetch()) {
                                            try {
                                                $dataBlock = array('tawasulPlannerEntryID' => $tawasulPlannerEntryNew, 'tawasulUnitClassID' => $tawasulUnitClassIDNew, 'tawasulUnitBlockID' => $rowBlocks['tawasulUnitBlockID'], 'title' => $rowBlocks['title'], 'type' => $rowBlocks['type'], 'length' => $rowBlocks['length'], 'contents' => $rowBlocks['contents'], 'teachersNotes' => $rowBlocks['teachersNotes'], 'sequenceNumber' => $rowBlocks['sequenceNumber']);
                                                $sqlBlock = 'INSERT INTO tawasulUnitClassBlock SET tawasulPlannerEntryID=:tawasulPlannerEntryID, tawasulUnitClassID=:tawasulUnitClassID, tawasulUnitBlockID=:tawasulUnitBlockID, title=:title, type=:type, length=:length, contents=:contents, teachersNotes=:teachersNotes, sequenceNumber=:sequenceNumber';
                                                $resultBlock = $connection2->prepare($sqlBlock);
                                                $resultBlock->execute($dataBlock);
                                            } catch (PDOException $e) {
                                                $partialFail = true;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                try {
                    $dataBlocks = array('tawasulUnitID' => $tawasulUnitID);
                    $sqlBlocks = 'SELECT * FROM tawasulUnitBlock WHERE tawasulUnitID=:tawasulUnitID ORDER BY sequenceNumber';
                    $resultBlocks = $connection2->prepare($sqlBlocks);
                    $resultBlocks->execute($dataBlocks);
                } catch (PDOException $e) {
                    $partialFail = true;
                }
                while ($rowBlocks = $resultBlocks->fetch()) {
                    try {
                        $dataBlock = array('tawasulUnitID' => $AI, 'title' => $rowBlocks['title'], 'type' => $rowBlocks['type'], 'length' => $rowBlocks['length'], 'contents' => $rowBlocks['contents'], 'teachersNotes' => $rowBlocks['teachersNotes'], 'sequenceNumber' => $rowBlocks['sequenceNumber']);
                        $sqlBlock = 'INSERT INTO tawasulUnitBlock SET tawasulUnitID=:tawasulUnitID, title=:title, type=:type, length=:length, contents=:contents, teachersNotes=:teachersNotes, sequenceNumber=:sequenceNumber';
                        $resultBlock = $connection2->prepare($sqlBlock);
                        $resultBlock->execute($dataBlock);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }
                }

                if ($partialFail == true) {
                    $URL .= '&return=error6';
                    header("Location: {$URL}");
                } else {
                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
        }
    }
}
