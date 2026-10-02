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

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulPlannerEntryID = $_GET['tawasulPlannerEntryID'] ?? '';
$viewBy = $_POST['viewBy'] ?? '';
$subView = $_POST['subView'] ?? '';
if ($viewBy != 'date' and $viewBy != 'class') {
    $viewBy = 'date';
}
$tawasulCourseClassID = $_POST['tawasulCourseClassID'] ?? '';
$date = $_POST['date'] ?? '';
$direction = $_POST['direction'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/planner_bump.php&tawasulPlannerEntryID=$tawasulPlannerEntryID";
$URLBump = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/planner.php';

//Params to pass back (viewBy + date or classID)
$params = "&viewBy=$viewBy&tawasulCourseClassID=$tawasulCourseClassID&subView=$subView";

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_bump.php') == false) {
    $URL .= "&return=error0$params";
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        //Proceed!
        if (($direction != 'forward' and $direction != 'backward') or $tawasulPlannerEntryID == '' or $viewBy == 'date' or ($viewBy == 'class' and $tawasulCourseClassID == 'Y')) {
            $URL .= "&return=error1$params";
            header("Location: {$URL}");
        } else {
            try {
                if ($highestAction == 'Lesson Planner_viewEditAllClasses') {
                    $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                    $sql = 'SELECT tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulPlannerEntry.name, date, timeStart, timeEnd FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID AND tawasulPlannerEntryID=:tawasulPlannerEntryID';
                } else {
                    $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
                    $sql = "SELECT tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulPlannerEntry.name, role, date, timeStart, timeEnd FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND role='Teacher' AND tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID AND tawasulPlannerEntryID=:tawasulPlannerEntryID";
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
                $partialFail = false;

                if ($direction == 'forward') { //BUMP FORWARD
                    try {
                        $dataList = array('tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $row['date'], 'timeStart' => $row['timeStart'], 'timeEnd' => $row['timeEnd']);
                        $sqlList = 'SELECT * FROM tawasulPlannerEntry WHERE tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID AND (date>=:date OR (date=:date AND timeStart>=:timeStart)) ORDER BY date DESC, timeStart DESC';
                        $resultList = $connection2->prepare($sqlList);
                        $resultList->execute($dataList);
                    } catch (PDOException $e) {
                        $URL .= "&return=error2$params";
                        header("Location: {$URL}");
                        exit();
                    }
                    while ($rowList = $resultList->fetch()) {
                        //Look for next available slot
                        try {
                            $dataNext = array('tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $rowList['date']);
                            $sqlNext = 'SELECT timeStart, timeEnd, date FROM tawasulTTDayRowClass JOIN tawasulTTColumnRow ON (tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID) JOIN tawasulTTColumn ON (tawasulTTColumnRow.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID) JOIN tawasulTTDay ON (tawasulTTDayRowClass.tawasulTTDayID=tawasulTTDay.tawasulTTDayID) JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID) WHERE tawasulCourseClassID=:tawasulCourseClassID AND date>=:date ORDER BY date, timestart LIMIT 0, 10';
                            $resultNext = $connection2->prepare($sqlNext);
                            $resultNext->execute($dataNext);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                        while ($rowNext = $resultNext->fetch()) {
                            if (isSchoolOpen($guid, $rowNext['date'], $connection2)) {
                                try {
                                    $dataPlanner = array('date' => $rowNext['date'], 'timeStart' => $rowNext['timeStart'], 'timeEnd' => $rowNext['timeEnd'], 'tawasulCourseClassID' => $tawasulCourseClassID);
                                    $sqlPlanner = 'SELECT * FROM tawasulPlannerEntry WHERE date=:date AND timeStart=:timeStart AND timeEnd=:timeEnd AND tawasulCourseClassID=:tawasulCourseClassID';
                                    $resultPlanner = $connection2->prepare($sqlPlanner);
                                    $resultPlanner->execute($dataPlanner);
                                } catch (PDOException $e) {
                                    $partialFail = true;
                                }
                                if ($resultPlanner->rowCount() == 0) {
                                    try {
                                        $dataNext = array('tawasulPlannerEntryID' => $rowList['tawasulPlannerEntryID'], 'date' => $rowNext['date'], 'timeStart' => $rowNext['timeStart'], 'timeEnd' => $rowNext['timeEnd']);
                                        $sqlNext = 'UPDATE tawasulPlannerEntry  set date=:date, timeStart=:timeStart, timeEnd=:timeEnd WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
                                        $resultNext = $connection2->prepare($sqlNext);
                                        $resultNext->execute($dataNext);
                                    } catch (PDOException $e) {
                                        $partialFail = true;
                                    }
                                    break;
                                }
                            }
                        }
                    }
                } else { //BUMP BACKWARD
                    try {
                        $dataList = array('tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $row['date'], 'timeStart' => $row['timeStart'], 'timeEnd' => $row['timeEnd']);
                        $sqlList = 'SELECT * FROM tawasulPlannerEntry WHERE tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID AND (date<=:date OR (date=:date AND timeStart<=:timeStart)) ORDER BY date, timeStart';
                        $resultList = $connection2->prepare($sqlList);
                        $resultList->execute($dataList);
                    } catch (PDOException $e) {
                        $URL .= "&return=error2$params";
                        header("Location: {$URL}");
                        exit();
                    }
                    while ($rowList = $resultList->fetch()) {
                        //Look for last available slot
                        try {
                            $dataNext = array('tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $rowList['date']);
                            $sqlNext = 'SELECT timeStart, timeEnd, date FROM tawasulTTDayRowClass JOIN tawasulTTColumnRow ON (tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID) JOIN tawasulTTColumn ON (tawasulTTColumnRow.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID) JOIN tawasulTTDay ON (tawasulTTDayRowClass.tawasulTTDayID=tawasulTTDay.tawasulTTDayID) JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID) WHERE tawasulCourseClassID=:tawasulCourseClassID AND date<=:date ORDER BY date DESC, timestart DESC LIMIT 0, 10';
                            $resultNext = $connection2->prepare($sqlNext);
                            $resultNext->execute($dataNext);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                        while ($rowNext = $resultNext->fetch()) {
                            if (isSchoolOpen($guid, $rowNext['date'], $connection2)) {
                                try {
                                    $dataPlanner = array('date' => $rowNext['date'], 'timeStart' => $rowNext['timeStart'], 'timeEnd' => $rowNext['timeEnd'], 'tawasulCourseClassID' => $tawasulCourseClassID);
                                    $sqlPlanner = 'SELECT * FROM tawasulPlannerEntry WHERE date=:date AND timeStart=:timeStart AND timeEnd=:timeEnd AND tawasulCourseClassID=:tawasulCourseClassID';
                                    $resultPlanner = $connection2->prepare($sqlPlanner);
                                    $resultPlanner->execute($dataPlanner);
                                } catch (PDOException $e) {
                                    $partialFail = true;
                                }
                                if ($resultPlanner->rowCount() == 0) {
                                    try {
                                        $dataNext = array('tawasulPlannerEntryID' => $rowList['tawasulPlannerEntryID'], 'date' => $rowNext['date'], 'timeStart' => $rowNext['timeStart'], 'timeEnd' => $rowNext['timeEnd']);
                                        $sqlNext = 'UPDATE tawasulPlannerEntry  set date=:date, timeStart=:timeStart, timeEnd=:timeEnd WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
                                        $resultNext = $connection2->prepare($sqlNext);
                                        $resultNext->execute($dataNext);
                                    } catch (PDOException $e) {
                                        $partialFail = true;
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }

                //Write to database
                if ($partialFail == true) {
                    $URL .= "&return=error5$params";
                    header("Location: {$URL}");
                } else {
                    $URL = $URLBump."&return=success1$params";
                    header("Location: {$URL}");
                }
            }
        }
    }
}
