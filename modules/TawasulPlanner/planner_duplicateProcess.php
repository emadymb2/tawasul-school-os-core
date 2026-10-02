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

use TawasulOS\Services\Format;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulPlannerEntryID = $_GET['tawasulPlannerEntryID'] ?? '';
$viewBy = $_POST['viewBy'] ?? '';
$subView = $_POST['subView'] ?? '';
if ($viewBy != 'date' and $viewBy != 'class') {
    $viewBy = 'date';
}
$tawasulCourseClassID = $_POST['tawasulCourseClassID'] ?? '';
$tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? '';
$tawasulPlannerEntryID_org = $_POST['tawasulPlannerEntryID_org'] ?? '';
$date = !empty($_POST['date']) ? Format::dateConvert($_POST['date']) : null;
$duplicateReturnYear = 'current';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/planner_duplicate.php&tawasulPlannerEntryID=$tawasulPlannerEntryID_org";

//Params to pass back (viewBy + date or classID)
if ($viewBy == 'date') {
    $params = "&viewBy=$viewBy&date=$date";
} else {
    $params = "&viewBy=$viewBy&tawasulCourseClassID=$tawasulCourseClassID&subView=$subView";
}

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_duplicate.php') == false) {
    $URL .= "&return=error0$params";
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if legitimate year/class selected
        if ($tawasulPlannerEntryID == '' or $tawasulSchoolYearID == '' or $tawasulCourseClassID == '' or ($viewBy == 'class' and $tawasulCourseClassID == 'Y')) {
            $URL .= "&return=error1$params";
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPlannerEntryID' => $tawasulPlannerEntryID_org);
                $sql = 'SELECT *, tawasulPlannerEntry.description AS description FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID AND tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= "&return=error2$params";
                header("Location: {$URL}");
                exit();
            }

            if ($result->rowCount() != 1) {
                $URL .= "&return=error2$params";
                header("Location: {$URL}");
            } else {
                $row = $result->fetch();

                //Validate Inputs
                $name = $_POST['name'] ?? '';
                $timeStart = $_POST['timeStart'] ?? '';
                $timeEnd = $_POST['timeEnd'] ?? '';
                $summary = $row['summary'];
                $description = $row['description'];
                //Add to smart blocks to description if copying to another year
                if ($tawasulSchoolYearID != $session->get('tawasulSchoolYearID') or @$_POST['keepUnit'] != 'Y') {
                    try {
                        $dataBlocks = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                        $sqlBlocks = 'SELECT * FROM tawasulUnitClassBlock WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID AND tawasulPlannerEntryID IS NOT NULL';
                        $resultBlocks = $connection2->prepare($sqlBlocks);
                        $resultBlocks->execute($dataBlocks);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }
                    while ($rowBlocks = $resultBlocks->fetch()) {
                        $description .= '<h2>'.$rowBlocks['title'].'</h2>';
                        $description .= $rowBlocks['contents'];
                    }


                        $dataPlannerUpdate = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'description' => $description);
                        $sqlPlannerUpdate = 'UPDATE tawasulPlannerEntry SET description=:description WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
                        $resultPlannerUpdate = $connection2->prepare($sqlPlannerUpdate);
                        $resultPlannerUpdate->execute($dataPlannerUpdate);
                }

                $tawasulUnitClassID = null;
                $keepUnit = $_POST['keepUnit'] ?? null;

                if ($keepUnit == 'Y') {
                    $tawasulUnitClassID = $_POST['tawasulUnitClassID'] ?? null;
                    $tawasulUnitID = !empty($row['tawasulUnitID']) ? $row['tawasulUnitID'] : null;
                } else {
                    $tawasulUnitID = null;
                }
                $teachersNotes = $row['teachersNotes'];
                $homework = $row['homework'];
                $homeworkDetails = $row['homeworkDetails'];
                $homeworkDueDateTime = $row['homeworkDueDateTime'];
                if (!empty($_POST['homeworkDueDate']) && !empty($_POST['homeworkDueDateTime'])) {
                    $homeworkDueDateTime = Format::dateConvert($_POST['homeworkDueDate']).' '.$_POST['homeworkDueDateTime'];
                }
                $homeworkTimeCap = $row['homeworkTimeCap'];
                $homeworkSubmission = $row['homeworkSubmission'];
                $homeworkSubmissionDateOpen = $row['homeworkSubmissionDateOpen'];
                if (!empty($_POST['homeworkSubmissionDateOpen'])) {
                    $homeworkSubmissionDateOpen = Format::dateConvert($_POST['homeworkSubmissionDateOpen']);
                }
                $homeworkSubmissionDrafts = $row['homeworkSubmissionDrafts'];
                $homeworkSubmissionType = $row['homeworkSubmissionType'];
                $homeworkSubmissionRequired = $row['homeworkSubmissionRequired'];
                $homeworkCrowdAssess = $row['homeworkCrowdAssess'];
                $homeworkCrowdAssessOtherTeachersRead = $row['homeworkCrowdAssessOtherTeachersRead'];
                $homeworkCrowdAssessClassmatesRead = $row['homeworkCrowdAssessClassmatesRead'];
                $homeworkCrowdAssessOtherStudentsRead = $row['homeworkCrowdAssessOtherStudentsRead'];
                $homeworkCrowdAssessSubmitterParentsRead = $row['homeworkCrowdAssessSubmitterParentsRead'];
                $homeworkCrowdAssessClassmatesParentsRead = $row['homeworkCrowdAssessClassmatesParentsRead'];
                $homeworkCrowdAssessOtherParentsRead = $row['homeworkCrowdAssessOtherParentsRead'];
                $viewableParents = $row['viewableParents'];
                $viewableStudents = $row['viewableStudents'];
                $tawasulPersonIDCreator = $session->get('tawasulPersonID');
                $tawasulPersonIDLastEdit = $session->get('tawasulPersonID');

                if ($viewBy == '' or $tawasulCourseClassID == '' or $date == '' or $timeStart == '' or $timeEnd == '' or $name == '' or $homework == '' or $viewableParents == '' or $viewableStudents == '' or ($homework == 'Y' and ($homeworkDetails == '' or $homeworkDueDateTime == ''))) {
                    $URL .= "&return=error3$params";
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    try {
                        $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $date, 'timeStart' => $timeStart, 'timeEnd' => $timeEnd, 'tawasulUnitID' => $tawasulUnitID, 'name' => $name, 'summary' => $summary, 'description' => $description, 'teachersNotes' => $teachersNotes, 'homework' => $homework, 'homeworkDueDateTime' => $homeworkDueDateTime, 'homeworkDetails' => $homeworkDetails, 'homeworkSubmission' => $homeworkSubmission, 'homeworkTimeCap' => $homeworkTimeCap, 'homeworkSubmissionDateOpen' => $homeworkSubmissionDateOpen, 'homeworkSubmissionDrafts' => $homeworkSubmissionDrafts, 'homeworkSubmissionType' => $homeworkSubmissionType, 'homeworkSubmissionRequired' => $homeworkSubmissionRequired, 'homeworkCrowdAssess' => $homeworkCrowdAssess, 'homeworkCrowdAssessOtherTeachersRead' => $homeworkCrowdAssessOtherTeachersRead, 'homeworkCrowdAssessClassmatesRead' => $homeworkCrowdAssessClassmatesRead, 'homeworkCrowdAssessOtherStudentsRead' => $homeworkCrowdAssessOtherStudentsRead, 'homeworkCrowdAssessSubmitterParentsRead' => $homeworkCrowdAssessSubmitterParentsRead, 'homeworkCrowdAssessClassmatesParentsRead' => $homeworkCrowdAssessClassmatesParentsRead, 'homeworkCrowdAssessOtherParentsRead' => $homeworkCrowdAssessOtherParentsRead, 'viewableParents' => $viewableParents, 'viewableStudents' => $viewableStudents, 'tawasulPersonIDCreator' => $tawasulPersonIDCreator, 'tawasulPersonIDLastEdit' => $tawasulPersonIDLastEdit);
                        $sql = 'INSERT INTO tawasulPlannerEntry SET tawasulCourseClassID=:tawasulCourseClassID, date=:date, timeStart=:timeStart, timeEnd=:timeEnd, tawasulUnitID=:tawasulUnitID, name=:name, summary=:summary, description=:description, teachersNotes=:teachersNotes, homework=:homework, homeworkDueDateTime=:homeworkDueDateTime, homeworkDetails=:homeworkDetails, homeworkSubmission=:homeworkSubmission, homeworkTimeCap=:homeworkTimeCap, homeworkSubmissionDateOpen=:homeworkSubmissionDateOpen, homeworkSubmissionDrafts=:homeworkSubmissionDrafts, homeworkSubmissionType=:homeworkSubmissionType, homeworkSubmissionRequired=:homeworkSubmissionRequired, homeworkCrowdAssess=:homeworkCrowdAssess, homeworkCrowdAssessOtherTeachersRead=:homeworkCrowdAssessOtherTeachersRead, homeworkCrowdAssessClassmatesRead=:homeworkCrowdAssessClassmatesRead, homeworkCrowdAssessOtherStudentsRead=:homeworkCrowdAssessOtherStudentsRead, homeworkCrowdAssessSubmitterParentsRead=:homeworkCrowdAssessSubmitterParentsRead, homeworkCrowdAssessClassmatesParentsRead=:homeworkCrowdAssessClassmatesParentsRead, homeworkCrowdAssessOtherParentsRead=:homeworkCrowdAssessOtherParentsRead, viewableParents=:viewableParents, viewableStudents=:viewableStudents, tawasulPersonIDCreator=:tawasulPersonIDCreator, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= "&return=error2$params";
                        header("Location: {$URL}");
                        exit();
                    }

                   $AI = $connection2->lastInsertID();

                    $partialFail = false;

                    //Try to duplicate MB columns
                    $duplicate = $_POST['duplicate'] ?? '';
                    if ($duplicate == 'Y') {
                        try {
                            $dataMarkbook = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                            $sqlMarkbook = 'SELECT * FROM tawasulMarkbookColumn WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
                            $resultMarkbook = $connection2->prepare($sqlMarkbook);
                            $resultMarkbook->execute($dataMarkbook);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                        while ($rowMarkbook = $resultMarkbook->fetch()) {
                            try {
                                $dataMarkbookInsert = array('tawasulUnitID' => $tawasulUnitID, 'tawasulPlannerEntryID' => $AI, 'tawasulCourseClassID' => $tawasulCourseClassID, 'name' => $rowMarkbook['name'], 'description' => $rowMarkbook['description'], 'type' => $rowMarkbook['type'], 'attainment' => $rowMarkbook['attainment'], 'tawasulScaleIDAttainment' => $rowMarkbook['tawasulScaleIDAttainment'], 'effort' => $rowMarkbook['effort'], 'tawasulScaleIDEffort' => $rowMarkbook['tawasulScaleIDEffort'], 'comment' => $rowMarkbook['comment'], 'viewableStudents' => $rowMarkbook['viewableStudents'], 'viewableParents' => $rowMarkbook['viewableParents'], 'attachment' => $rowMarkbook['attachment'], 'tawasulPersonID1' => $session->get('tawasulPersonID'), 'tawasulPersonID2' => $session->get('tawasulPersonID'));
                                $sqlMarkbookInsert = "INSERT INTO tawasulMarkbookColumn SET tawasulUnitID=:tawasulUnitID, tawasulPlannerEntryID=:tawasulPlannerEntryID, tawasulCourseClassID=:tawasulCourseClassID, name=:name, description=:description, type=:type, attainment=:attainment, tawasulScaleIDAttainment=:tawasulScaleIDAttainment, effort=:effort, tawasulScaleIDEffort=:tawasulScaleIDEffort, comment=:comment, completeDate=NULL, complete='N' ,viewableStudents=:viewableStudents, viewableParents=:viewableParents ,attachment=:attachment, tawasulPersonIDCreator=:tawasulPersonID1, tawasulPersonIDLastEdit=:tawasulPersonID2";
                                $resultMarkbookInsert = $connection2->prepare($sqlMarkbookInsert);
                                $resultMarkbookInsert->execute($dataMarkbookInsert);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        }
                    }

                    //DUPLICATE SMART BLOCKS
                    if ($tawasulUnitClassID != null) {
                        try {
                            $dataBlocks = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                            $sqlBlocks = 'SELECT * FROM tawasulUnitClassBlock WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
                            $resultBlocks = $connection2->prepare($sqlBlocks);
                            $resultBlocks->execute($dataBlocks);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                        while ($rowBlocks = $resultBlocks->fetch()) {
                            try {
                                $dataBlocksInsert = array('tawasulUnitClassID' => $tawasulUnitClassID, 'tawasulPlannerEntryID' => $AI, 'tawasulUnitBlockID' => $rowBlocks['tawasulUnitBlockID'], 'title' => $rowBlocks['title'], 'type' => $rowBlocks['type'], 'length' => $rowBlocks['length'], 'contents' => $rowBlocks['contents'], 'teachersNotes' => $rowBlocks['teachersNotes'], 'sequenceNumber' => $rowBlocks['sequenceNumber']);
                                $sqlBlocksInsert = "INSERT INTO tawasulUnitClassBlock SET tawasulUnitClassID=:tawasulUnitClassID, tawasulPlannerEntryID=:tawasulPlannerEntryID, tawasulUnitBlockID=:tawasulUnitBlockID, title=:title, type=:type, length=:length, contents=:contents, teachersNotes=:teachersNotes, sequenceNumber=:sequenceNumber, complete='N'";
                                $resultBlocksInsert = $connection2->prepare($sqlBlocksInsert);
                                $resultBlocksInsert->execute($dataBlocksInsert);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        }
                    }

                    //DUPLICATE OUTCOMES
                    try {
                        $dataBlocks = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                        $sqlBlocks = 'SELECT * FROM tawasulPlannerEntryOutcome WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
                        $resultBlocks = $connection2->prepare($sqlBlocks);
                        $resultBlocks->execute($dataBlocks);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }
                    while ($rowBlocks = $resultBlocks->fetch()) {
                        try {
                            $dataBlocksInsert = array('tawasulPlannerEntryID' => $AI, 'tawasulOutcomeID' => $rowBlocks['tawasulOutcomeID'], 'sequenceNumber' => $rowBlocks['sequenceNumber'], 'content' => $rowBlocks['content']);
                            $sqlBlocksInsert = 'INSERT INTO tawasulPlannerEntryOutcome SET tawasulPlannerEntryID=:tawasulPlannerEntryID, tawasulOutcomeID=:tawasulOutcomeID, sequenceNumber=:sequenceNumber, content=:content';
                            $resultBlocksInsert = $connection2->prepare($sqlBlocksInsert);
                            $resultBlocksInsert->execute($dataBlocksInsert);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                    }

                    if ($partialFail == true) {
                        $URL .= "&return=warning1$params";
                        header("Location: {$URL}");
                    } else {
                        if ($tawasulSchoolYearID == $session->get('tawasulSchoolYearID')) {
                            $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/planner_edit.php&tawasulPlannerEntryID=$AI";
                            $URL .= "&return=success1$params";
                        } else {
                            $URL .= "&return=success0$params";
                        }
                        header("Location: {$URL}");
                    }
                }
            }
        }
    }
}
