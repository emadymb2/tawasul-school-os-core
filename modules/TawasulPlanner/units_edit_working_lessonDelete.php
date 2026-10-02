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

require_once __DIR__ . '/../../tawasul.php';

$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$tawasulUnitID = $_GET['tawasulUnitID'] ?? '';
$tawasulUnitClassID = $_GET['tawasulUnitClassID'] ?? '';
$tawasulPlannerEntryID = $_GET['tawasulPlannerEntryID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address'])."/units_edit_working.php&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseID=$tawasulCourseID&tawasulUnitID=$tawasulUnitID&tawasulCourseClassID=$tawasulCourseClassID&tawasulUnitClassID=$tawasulUnitClassID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_edit_working.php') == false) {
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
        if ($tawasulSchoolYearID == '' or $tawasulCourseID == '' or $tawasulUnitID == '' or $tawasulPlannerEntryID == '' or $tawasulUnitClassID == '') {
            $URL .= '&return=error3';
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
                    $URL .= '&deployReturn=error2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($result->rowCount() != 1) {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                } else {
                    //Check existence of specified planner entry in class and unit
                    try {
                        $data = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'tawasulUnitID' => $tawasulUnitID, 'tawasulCourseClassID' => $tawasulCourseClassID);
                        $sql = 'SELECT * FROM tawasulPlannerEntry WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID AND tawasulUnitID=:tawasulUnitID AND tawasulCourseClassID=:tawasulCourseClassID';
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
                        $partialFail = false;

                        //Remove all blocks
                        try {
                            $data = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                            $sql = 'DELETE FROM tawasulUnitClassBlock WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }

                        //Remove lesson plan
                        try {
                            $data = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                            $sql = 'DELETE FROM tawasulPlannerEntry WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }

                        //RETURN
                        if ($partialFail == true) {
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
