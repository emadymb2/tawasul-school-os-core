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
use TawasulOS\Comms\NotificationSender;
use TawasulOS\Domain\System\NotificationGateway;
use TawasulOS\Data\Validator;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Domain\Planner\UnitClassBlockGateway;
use TawasulOS\Domain\Planner\UnitBlockGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['description' => 'HTML', 'homeworkDetails' => 'HTML', 'contents*' => 'HTML', 'teachersNotes*' => 'HTML']);

$tawasulPlannerEntryID = $_GET['tawasulPlannerEntryID'] ?? '';
$viewBy = $_GET['viewBy'] ?? '';
$subView = $_GET['subView'] ?? '';
if ($viewBy != 'date' and $viewBy != 'class') {
    $viewBy = 'date';
}
$tawasulCourseClassID = $_POST['tawasulCourseClassID'] ?? '';
$date = !empty($_POST['date']) ? Format::dateConvert($_POST['date']) : null;
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address'])."/planner_edit.php&tawasulPlannerEntryID=$tawasulPlannerEntryID";

//Params to pass back (viewBy + date or classID)
if ($viewBy == 'date') {
    $params = "&viewBy=$viewBy&date=$date";
} else {
    $params = "&viewBy=$viewBy&tawasulCourseClassID=$tawasulCourseClassID&subView=$subView";
}

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_edit.php') == false) {
    $URL .= "&return=error0$params";
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
            //Check if tawasulPlannerEntryID and tawasulCourseClassID specified
            if ($tawasulPlannerEntryID == '' or ($viewBy == 'class' and $tawasulCourseClassID == '')) {
                $URL .= "&return=error1$params";
                header("Location: {$URL}");
            } else {
                try {
                    if ($highestAction == 'Lesson Planner_viewEditAllClasses') {
                        $data = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                        $sql = 'SELECT tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulPlannerEntry.name, summary, tawasulPlannerEntry.fields FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
                    } else {
                        $data = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
                        $sql = "SELECT tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulPlannerEntry.name, summary, role, tawasulPlannerEntry.fields FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND role='Teacher' AND tawasulPlannerEntryID=:tawasulPlannerEntryID";
                    }
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
                    $timeStart = $_POST['timeStart'] ?? '';
                    $timeEnd = $_POST['timeEnd'] ?? '';
                    $tawasulUnitID = !empty($_POST['tawasulUnitID']) ? $_POST['tawasulUnitID'] : null;
                    $name = $_POST['name'] ?? '';
                    $summary = $_POST['summary'] ?? '';
                    if (empty($summary)) {
                        $summary = trim(strip_tags($_POST['description'] ?? '')) ;
                        $summary = mb_substr($summary, 0, 252);
                    } else {
                        $summary = strip_tags($summary);
                    }
                    $summaryBlocks = '';
                    $description = $_POST['description'] ?? '';
                    $teachersNotes = $_POST['teachersNotes'] ?? '';
                    $homeworkSubmissionDateOpen = null;
                    $homeworkSubmissionDrafts = null;
                    $homeworkSubmissionType = null;
                    $homeworkSubmissionRequired = null;
                    $homeworkCrowdAssess = null;
                    $homeworkCrowdAssessOtherTeachersRead = null;
                    $homeworkCrowdAssessClassmatesRead = null;
                    $homeworkCrowdAssessOtherStudentsRead = null;
                    $homeworkCrowdAssessSubmitterParentsRead = null;
                    $homeworkCrowdAssessClassmatesParentsRead = null;
                    $homeworkCrowdAssessOtherParentsRead = null;
                    $homeworkTimeCap = null;
                    $homeworkLocation = null;

                    $homework = $_POST['homework'] ?? '';
                    if ($_POST['homework'] == 'Y') {
                        $homework = 'Y';
                        $homeworkDetails = $_POST['homeworkDetails'] ?? '';
                        $homeworkTimeCap = $_POST['homeworkTimeCap'] ?? null;
                        $homeworkLocation = $_POST['homeworkLocation'] ?? 'Out of Class';
                        if ($_POST['homeworkDueDateTime'] != '') {
                            $homeworkDueDateTime = $_POST['homeworkDueDateTime'].':59';
                        } else {
                            $homeworkDueDateTime = '21:00:00';
                        }
                        if ($_POST['homeworkDueDate'] != '') {
                            $homeworkDueDate = Format::dateConvert($_POST['homeworkDueDate']).' '.$homeworkDueDateTime;
                        }

                        // Check if the homework due date is within this class
                        $homeworkTimestamp = strtotime($homeworkDueDate);
                        if ($homeworkTimestamp >= strtotime($date.' '.$timeStart.':00') && $homeworkTimestamp <= strtotime($date.' '.$timeEnd.':59')) {
                            $homeworkLocation = 'In Class';
                        }

                        if ($_POST['homeworkSubmission'] == 'Y') {
                            $homeworkSubmission = 'Y';
                            if ($_POST['homeworkSubmissionDateOpen'] != '') {
                                $homeworkSubmissionDateOpen = Format::dateConvert($_POST['homeworkSubmissionDateOpen']);
                            } else {
                                $homeworkSubmissionDateOpen = date('Y-m-d');
                            }
                            
                            $homeworkSubmissionDrafts = !empty($_POST['homeworkSubmissionDrafts']) ? $_POST['homeworkSubmissionDrafts'] : null;
                            $homeworkSubmissionType = $_POST['homeworkSubmissionType'] ?? '';
                            $homeworkSubmissionRequired = $_POST['homeworkSubmissionRequired'] ?? '';
                            if (!empty($_POST['homeworkCrowdAssess']) && $_POST['homeworkCrowdAssess'] == 'Y') {
                                $homeworkCrowdAssess = 'Y';
                                if (isset($_POST['homeworkCrowdAssessOtherTeachersRead'])) {
                                    $homeworkCrowdAssessOtherTeachersRead = 'Y';
                                } else {
                                    $homeworkCrowdAssessOtherTeachersRead = 'N';
                                }
                                if (isset($_POST['homeworkCrowdAssessClassmatesRead'])) {
                                    $homeworkCrowdAssessClassmatesRead = 'Y';
                                } else {
                                    $homeworkCrowdAssessClassmatesRead = 'N';
                                }
                                if (isset($_POST['homeworkCrowdAssessOtherStudentsRead'])) {
                                    $homeworkCrowdAssessOtherStudentsRead = 'Y';
                                } else {
                                    $homeworkCrowdAssessOtherStudentsRead = 'N';
                                }
                                if (isset($_POST['homeworkCrowdAssessSubmitterParentsRead'])) {
                                    $homeworkCrowdAssessSubmitterParentsRead = 'Y';
                                } else {
                                    $homeworkCrowdAssessSubmitterParentsRead = 'N';
                                }
                                if (isset($_POST['homeworkCrowdAssessClassmatesParentsRead'])) {
                                    $homeworkCrowdAssessClassmatesParentsRead = 'Y';
                                } else {
                                    $homeworkCrowdAssessClassmatesParentsRead = 'N';
                                }
                                if (isset($_POST['homeworkCrowdAssessOtherParentsRead'])) {
                                    $homeworkCrowdAssessOtherParentsRead = 'Y';
                                } else {
                                    $homeworkCrowdAssessOtherParentsRead = 'N';
                                }
                            }
                            else {
                                $homeworkCrowdAssess = 'N';
                            }
                        } else {
                            $homeworkSubmission = 'N';
                            $homeworkCrowdAssess = 'N';
                        }
                    } else {
                        $homework = 'N';
                        $homeworkDueDate = null;
                        $homeworkDetails = '';
                        $homeworkSubmission = 'N';
                        $homeworkCrowdAssess = 'N';
                    }

                    $tawasulSpaceID = $_POST['tawasulSpaceID'] ?? null;
                    $tawasulTTDayRowClassID = $_POST['tawasulTTDayRowClassID'] ?? null;
                    $viewableParents = $_POST['viewableParents'] ?? '';
                    $viewableStudents = $_POST['viewableStudents'] ?? '';
                    $tawasulPersonIDCreator = $session->get('tawasulPersonID');
                    $tawasulPersonIDLastEdit = $session->get('tawasulPersonID');

                    // CUSTOM FIELDS
                    $customRequireFail = false;
                    $fields = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('Lesson Plan', [], $customRequireFail);

                    if (isset($_POST['videoLink'])) {
                        $fields = !empty($fields) ? json_decode($fields, true) : [];
                        $fields = json_encode(['videoLink'=> $_POST['videoLink'] ?? ''] + $fields);
                    }

                    if ($viewBy == '' or $tawasulCourseClassID == '' or $date == '' or $timeStart == '' or $timeEnd == '' or $name == '' or $homework == '' or $viewableParents == '' or $viewableStudents == '' or ($homework == 'Y' and ($homeworkDetails == '' or $homeworkDueDate == ''))) {
                        $URL .= "&return=error3$params";
                        header("Location: {$URL}");
                    } else {
                        //Scan through guests
                        $guests = $_POST['guests'] ?? [];
                        $role = $_POST['role'] ?? 'Student';

                        if (count($guests) > 0) {
                            foreach ($guests as $t) {
                                //Check to see if person is already registered in this class
                                try {
                                    $dataGuest = array('tawasulPersonID' => $t, 'tawasulCourseClassID' => $tawasulCourseClassID);
                                    $sqlGuest = 'SELECT * FROM tawasulCourseClassPerson WHERE tawasulPersonID=:tawasulPersonID AND tawasulCourseClassID=:tawasulCourseClassID';
                                    $resultGuest = $connection2->prepare($sqlGuest);
                                    $resultGuest->execute($dataGuest);
                                } catch (PDOException $e) {
                                    $partialFail = true;
                                }

                                //Check for an exception for the current user
                                try {
                                    $dataException = array('tawasulPersonID' => $t, 'tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $date);
                                    $sqlException = 'SELECT * FROM tawasulTTDayRowClassException JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulTTDayRowClassID=tawasulTTDayRowClassException.tawasulTTDayRowClassID) JOIN tawasulTTColumnRow ON (tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID) JOIN tawasulTTColumn ON (tawasulTTColumnRow.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID) JOIN tawasulTTDay ON (tawasulTTDayRowClass.tawasulTTDayID=tawasulTTDay.tawasulTTDayID) JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID) WHERE tawasulTTDayRowClass.tawasulCourseClassID=:tawasulCourseClassID AND tawasulTTDayRowClassException.tawasulPersonID=:tawasulPersonID AND tawasulTTDayDate.date=:date';
                                    $resultException = $connection2->prepare($sqlException);
                                    $resultException->execute($dataException);
                                } catch (PDOException $e) {
                                    $partialFail = true;
                                }

                                $exception = $pdo->select($sqlException, $dataException)->fetchAll();


                                if ($resultGuest->rowCount() == 0 || !empty($exception)) {
                                    //Check to see if person is already a guest in this class
                                    try {
                                        $dataGuest2 = array('tawasulPersonID' => $t, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                                        $sqlGuest2 = 'SELECT * FROM tawasulPlannerEntryGuest WHERE tawasulPersonID=:tawasulPersonID AND tawasulPlannerEntryID=:tawasulPlannerEntryID';
                                        $resultGuest2 = $connection2->prepare($sqlGuest2);
                                        $resultGuest2->execute($dataGuest2);
                                    } catch (PDOException $e) {
                                        $partialFail = true;
                                    }
                                    if ($resultGuest2->rowCount() == 0) {
                                        try {
                                            $data = array('tawasulPersonID' => $t, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'role' => $role);
                                            $sql = 'INSERT INTO tawasulPlannerEntryGuest SET tawasulPersonID=:tawasulPersonID, tawasulPlannerEntryID=:tawasulPlannerEntryID, role=:role';
                                            $result = $connection2->prepare($sql);
                                            $result->execute($data);
                                        } catch (PDOException $e) {
                                            $partialFail = true;
                                        }
                                    }
                                }
                            }
                        }

                        //Deal with smart unit
                        $unitClassBlockGateway = $container->get(UnitClassBlockGateway::class);
                        $partialFail = false;
                        $order = $_POST['order'] ?? [];
                        $sequenceNumber = $_POST['minSeq'] ?? 0;
                        $idList = [];

                        if (is_array($order) && !empty($tawasulUnitID)) {
                            foreach ($order as $i) {
                                $tawasulUnitClassBlockID = $_POST["tawasulUnitClassBlockID$i"] ?? '';
                                $title = $_POST["title$i"] ?? '';
                                $summaryBlocks .= $title.', ';
                                $type = $_POST["type$i"] ?? '';
                                $length = $_POST["length$i"] ?? '';
                                $contents = $_POST["contents$i"] ?? '';
                                $teachersNotesBlock = $_POST["teachersNotes$i"] ?? '';
                                $complete = isset($_POST["complete$i"]) && $_POST["complete$i"] == 'on' ? 'Y' : 'N';

                                //Write to database
                                $data = ['title' => $title, 'type' => $type, 'length' => $length, 'contents' => $contents, 'teachersNotes' => $teachersNotesBlock, 'complete' => $complete, 'sequenceNumber' => $sequenceNumber];
                                
                                if (!empty($tawasulUnitClassBlockID)) {
                                    $updated = $unitClassBlockGateway->update($tawasulUnitClassBlockID, $data);
                                    $partialFail &= !$updated;
                                }

                                $idList[] = $tawasulUnitClassBlockID;
                                $sequenceNumber++;
                            }

                            // Remove deleted blocks
                            $unitClassBlockGateway->deletePlannerBlocksNotInList($tawasulPlannerEntryID, $idList);
                        }

                        //Insert outcomes
                        $outcomeIDs = [];
                        if (!empty($_POST['outcomeorder'])) {
             
                            foreach ($_POST['outcomeorder'] as $count => $outcome) {
                                if (empty($_POST["outcometawasulOutcomeID$outcome"])) continue;

                                $tawasulPlannerEntryOutcomeID = $_POST["outcometawasulPlannerEntryOutcomeID$outcome"] ?? '';

                                $dataOutcome = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulOutcomeID' => $_POST["outcometawasulOutcomeID$outcome"] ?? '', 'content' => $_POST["outcomecontents$outcome"] ?? '', 'count' => $count);
                                
                                if (!empty($tawasulPlannerEntryOutcomeID)) {
                                    $sqlOutcome = 'UPDATE tawasulPlannerEntryOutcome SET tawasulPlannerEntryID=:tawasulPlannerEntryID, tawasulOutcomeID=:tawasulOutcomeID, content=:content, sequenceNumber=:count WHERE tawasulPlannerEntryOutcomeID=:tawasulPlannerEntryOutcomeID';
                                    $resultInsert = $pdo->insert($sqlOutcome, $dataOutcome + ['tawasulPlannerEntryOutcomeID' => $tawasulPlannerEntryOutcomeID]);
                                } else {
                                    $sqlOutcome = 'INSERT INTO tawasulPlannerEntryOutcome SET tawasulPlannerEntryID=:tawasulPlannerEntryID, tawasulOutcomeID=:tawasulOutcomeID, content=:content, sequenceNumber=:count';
                                    $resultInsert = $pdo->insert($sqlOutcome, $dataOutcome);
                                }
                                
                                $outcomeIDs[] = $_POST["outcometawasulOutcomeID$outcome"] ?? '';
                            }
                        }
                        

                        // Remove deleted outcomes
                        $dataRemove = ['tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulOutcomeIDList' => implode(',', $outcomeIDs)];
                        $sqlRemove = "DELETE FROM tawasulPlannerEntryOutcome WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID AND NOT FIND_IN_SET(tawasulOutcomeID, :tawasulOutcomeIDList)";
                        $pdo->delete($sqlRemove, $dataRemove);
                        

                        $summaryBlocks = substr($summaryBlocks, 0, -2);
                        if (strlen($summaryBlocks) > 75) {
                            $summaryBlocks = substr($summaryBlocks, 0, 72).'...';
                        }
                        if (empty($summary) && $summaryBlocks) {
                            $summary = strip_tags($summaryBlocks);
                        }

                        //Write to database
                        try {
                            $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $date, 'timeStart' => $timeStart, 'timeEnd' => $timeEnd, 'tawasulUnitID' => $tawasulUnitID, 'tawasulSpaceID' => $tawasulSpaceID, 'tawasulTTDayRowClassID' => $tawasulTTDayRowClassID, 'name' => $name, 'summary' => $summary, 'description' => $description, 'teachersNotes' => $teachersNotes, 'homework' => $homework, 'homeworkDueDate' => $homeworkDueDate, 'homeworkDetails' => $homeworkDetails, 'homeworkTimeCap' => $homeworkTimeCap, 'homeworkLocation' => $homeworkLocation, 'homeworkSubmission' => $homeworkSubmission, 'homeworkSubmissionDateOpen' => $homeworkSubmissionDateOpen, 'homeworkSubmissionDrafts' => $homeworkSubmissionDrafts, 'homeworkSubmissionType' => $homeworkSubmissionType, 'homeworkSubmissionRequired' => $homeworkSubmissionRequired, 'homeworkCrowdAssess' => $homeworkCrowdAssess, 'homeworkCrowdAssessOtherTeachersRead' => $homeworkCrowdAssessOtherTeachersRead, 'homeworkCrowdAssessClassmatesRead' => $homeworkCrowdAssessClassmatesRead, 'homeworkCrowdAssessOtherStudentsRead' => $homeworkCrowdAssessOtherStudentsRead, 'homeworkCrowdAssessSubmitterParentsRead' => $homeworkCrowdAssessSubmitterParentsRead, 'homeworkCrowdAssessClassmatesParentsRead' => $homeworkCrowdAssessClassmatesParentsRead, 'homeworkCrowdAssessOtherParentsRead' => $homeworkCrowdAssessOtherParentsRead, 'viewableParents' => $viewableParents, 'viewableStudents' => $viewableStudents, 'tawasulPersonIDLastEdit' => $tawasulPersonIDLastEdit, 'fields' => $fields, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                            $sql = 'UPDATE tawasulPlannerEntry SET tawasulCourseClassID=:tawasulCourseClassID, date=:date, timeStart=:timeStart, timeEnd=:timeEnd, tawasulUnitID=:tawasulUnitID, tawasulSpaceID=:tawasulSpaceID, tawasulTTDayRowClassID=:tawasulTTDayRowClassID, name=:name, summary=:summary, description=:description, teachersNotes=:teachersNotes, homework=:homework, homeworkDueDateTime=:homeworkDueDate, homeworkDetails=:homeworkDetails, homeworkTimeCap=:homeworkTimeCap, homeworkLocation=:homeworkLocation, homeworkSubmission=:homeworkSubmission, homeworkSubmissionDateOpen=:homeworkSubmissionDateOpen, homeworkSubmissionDrafts=:homeworkSubmissionDrafts, homeworkSubmissionType=:homeworkSubmissionType, homeworkSubmissionRequired=:homeworkSubmissionRequired, homeworkCrowdAssess=:homeworkCrowdAssess, homeworkCrowdAssessOtherTeachersRead=:homeworkCrowdAssessOtherTeachersRead, homeworkCrowdAssessClassmatesRead=:homeworkCrowdAssessClassmatesRead, homeworkCrowdAssessOtherStudentsRead=:homeworkCrowdAssessOtherStudentsRead, homeworkCrowdAssessSubmitterParentsRead=:homeworkCrowdAssessSubmitterParentsRead, homeworkCrowdAssessClassmatesParentsRead=:homeworkCrowdAssessClassmatesParentsRead, homeworkCrowdAssessOtherParentsRead=:homeworkCrowdAssessOtherParentsRead, viewableParents=:viewableParents, viewableStudents=:viewableStudents, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit, fields=:fields WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $URL .= "&return=error2$params";
                            header("Location: {$URL}");
                            exit();
                        }

                        // Manage custom field file uploads
                        if (!empty($fields)) {
                            $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('Lesson Plan', [], $fields, 'tawasulPlannerEntry', $tawasulPlannerEntryID, $row['fields'] ?? null);
                        }

                        if ($partialFail == true) {
                            $URL .= "&return=warning1$params";
                            header("Location: {$URL}");
                        } else {
                            //Jump to Markbook?
                            $markbook = $_POST['markbook'] ?? '';
                            if ($markbook == 'Y') {
                                $URL = $_SESSION[$guid]['absoluteURL']."/index.php?q=/modules/TawasulMarkbook/markbook_edit_add.php&tawasulPlannerEntryID=$tawasulPlannerEntryID&tawasulCourseClassID=$tawasulCourseClassID&tawasulUnitID=$tawasulUnitID&date=$date&viewableParents=$viewableParents&viewableStudents=$viewableStudents&name=$name&summary=$summary&return=success1";
                                header("Location: {$URL}");
                                exit();
                            }
                        }
                        //Notify participants
                        if (isset($_POST['notify'])) {
                            //Create notification for all people in class except me
                            $notificationGateway = $container->get(NotificationGateway::class);
                            $notificationSender = $container->get(NotificationSender::class);

                            try {
                                $dataClassGroup = array('tawasulCourseClassID' => $tawasulCourseClassID);
                                $sqlClassGroup = "SELECT * FROM tawasulCourseClassPerson INNER JOIN tawasulPerson ON tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID WHERE tawasulCourseClassID=:tawasulCourseClassID AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND (NOT role='Student - Left') AND (NOT role='Teacher - Left') ORDER BY role DESC, surname, preferredName";
                                $resultClassGroup = $connection2->prepare($sqlClassGroup);
                                $resultClassGroup->execute($dataClassGroup);
                            } catch (PDOException $e) {
                                $URL .= "&return=warning1$params";
                                header("Location: {$URL}");
                                exit();
                            }

                            while ($rowClassGroup = $resultClassGroup->fetch()) {
                                if ($rowClassGroup['tawasulPersonID'] != $session->get('tawasulPersonID')) {
                                    $notificationSender->addNotification($rowClassGroup['tawasulPersonID'], sprintf(__('Lesson “%1$s” has been updated.'), $name), "Planner", "/index.php?q=/modules/TawasulPlanner/planner_view_full.php&tawasulPlannerEntryID=$tawasulPlannerEntryID&viewBy=class&tawasulCourseClassID=$tawasulCourseClassID");
                                }
                            }
                            $notificationSender->sendNotifications();
                        }

                        $URL .= "&return=success0&editID=".$tawasulPlannerEntryID.$params;
                        header("Location: {$URL}");
                        exit();
                    }
                }
            }
        }
    }
}
