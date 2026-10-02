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

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Domain\Planner\PlannerEntryGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$tawasulUnitClassID = $_GET['tawasulUnitClassID'] ?? '';
$tawasulUnitID = $_GET['tawasulUnitID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/units_edit_working.php&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseID=$tawasulCourseID&tawasulUnitID=$tawasulUnitID&tawasulCourseClassID=$tawasulCourseClassID&tawasulUnitClassID=$tawasulUnitClassID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_edit_working_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        $lessonsChecked = $_POST['lessons'] ?? [];

        //Proceed!
        //Validate Inputs
        if ($tawasulSchoolYearID == '' or $tawasulCourseID == '' or $tawasulUnitID == '' or $tawasulCourseClassID == '' or $tawasulUnitClassID == '' or empty($lessonsChecked)) {
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
                    $sql = 'SELECT tawasulCourse.nameShort AS courseName, tawasulUnit.* FROM tawasulUnit JOIN tawasulCourse ON (tawasulUnit.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulUnitID=:tawasulUnitID AND tawasulUnit.tawasulCourseID=:tawasulCourseID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&deployReturn=fail2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($result->rowCount() != 1) {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                } else {
                    $row = $result->fetch();
                    $partialFail = false;

                    $plannerEntryGateway = $container->get(PlannerEntryGateway::class);

                    $lessons = $plannerEntryGateway->selectPlannerEntriesByUnitAndClass($tawasulUnitID, $tawasulCourseClassID)->fetchAll();
                    $lessonCount = count($lessons);

                    foreach ($lessonsChecked as $lesson) {
                        [$tawasulTTDayRowClassID, $tawasulTTDayDateID] = explode('-', $lesson);
                        $values = $plannerEntryGateway->getPlannerTTByIDs($tawasulTTDayRowClassID, $tawasulTTDayDateID);

                        $summary = 'Part of the '.$row['name'].' unit.';
                        $teachersNotes = $container->get(SettingGateway::class)->getSettingByScope('Planner', 'teachersNotesTemplate');

                        $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $values['date'], 'timeStart' => $values['timeStart'], 'timeEnd' => $values['timeEnd'], 'tawasulUnitID' => $tawasulUnitID, 'name' => $row['name'].' '.($lessonCount + 1), 'summary' => $summary, 'teachersNotes' => $teachersNotes, 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'), 'tawasulPersonIDLastEdit' => $session->get('tawasulPersonID'));
                        $sql = "INSERT INTO tawasulPlannerEntry SET tawasulCourseClassID=:tawasulCourseClassID, date=:date, timeStart=:timeStart, timeEnd=:timeEnd, tawasulUnitID=:tawasulUnitID, name=:name, summary=:summary, description='', teachersNotes=:teachersNotes, homework='N', viewableParents='Y', viewableStudents='Y', tawasulPersonIDCreator=:tawasulPersonIDCreator, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit";

                        $inserted = $pdo->insert($sql, $data);
                        $partialFail &= !$inserted;
                        $lessonCount++;
                    }

                    //RETURN
                    if ($partialFail == true) {
                        $URL .= '&return=warning1';
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
