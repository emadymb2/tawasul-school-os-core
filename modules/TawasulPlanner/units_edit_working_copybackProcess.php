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

$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$tawasulUnitID = $_GET['tawasulUnitID'] ?? '';
$tawasulUnitBlockID = $_GET['tawasulUnitBlockID'] ?? '';
$tawasulUnitClassBlockID = $_GET['tawasulUnitClassBlockID'] ?? '';
$tawasulUnitClassID = $_GET['tawasulUnitClassID'] ?? '';

$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulPlanner/units_edit_working_copyback.php&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseID=$tawasulCourseID&tawasulCourseClassID=$tawasulCourseClassID&tawasulUnitID=$tawasulUnitID&tawasulUnitBlockID=$tawasulUnitBlockID&tawasulUnitClassBlockID=$tawasulUnitClassBlockID&tawasulUnitClassID=$tawasulUnitClassID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_edit_working_copyback.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, '/modules/TawasulPlanner/units_edit_working_copyback.php', $connection2);
    if ($highestAction == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Validate Inputs
        if ($tawasulSchoolYearID == '' or $tawasulCourseID == '' or $tawasulUnitID == '' or $tawasulCourseClassID == '' or $tawasulUnitClassID == '') {
            $URL .= '&copyReturn=error3';
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
                $URL .= '&copyReturn=error4';
                header("Location: {$URL}");
            } else {
                //Check existence of specified unit/class
                try {
                    $data = array('tawasulUnitID' => $tawasulUnitID, 'tawasulCourseID' => $tawasulCourseID, 'tawasulUnitBlockID' => $tawasulUnitBlockID, 'tawasulUnitClassBlockID' => $tawasulUnitClassBlockID);
                    $sql = 'SELECT tawasulUnitClassBlock.* FROM tawasulUnit JOIN tawasulCourse ON (tawasulUnit.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulUnitBlock ON (tawasulUnitBlock.tawasulUnitID=tawasulUnit.tawasulUnitID) JOIN tawasulUnitClassBlock ON (tawasulUnitClassBlock.tawasulUnitBlockID=tawasulUnitBlock.tawasulUnitBlockID) WHERE tawasulUnitClassBlockID=:tawasulUnitClassBlockID AND tawasulUnitBlock.tawasulUnitBlockID=:tawasulUnitBlockID AND tawasulUnit.tawasulUnitID=:tawasulUnitID AND tawasulUnit.tawasulCourseID=:tawasulCourseID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($result->rowCount() != 1) {
                    $URL .= '&copyReturn=error4';
                    header("Location: {$URL}");
                } else {
                    $row = $result->fetch();
                    $partialFail = false;

                    try {
                        $data = array('title' => $row['title'], 'type' => $row['type'], 'length' => $row['length'], 'contents' => $row['contents'], 'teachersNotes' => $row['teachersNotes'], 'tawasulUnitBlockID' => $tawasulUnitBlockID, 'tawasulUnitID' => $tawasulUnitID);
                        $sql = 'UPDATE tawasulUnitBlock SET title=:title, type=:type, length=:length, contents=:contents, teachersNotes=:teachersNotes WHERE tawasulUnitBlockID=:tawasulUnitBlockID AND tawasulUnitID=:tawasulUnitID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }

                    $working = $_POST['working'] ?? '';
                    if ($working == 'Y') {
                        try {
                            $data = array('title' => $row['title'], 'type' => $row['type'], 'length' => $row['length'], 'contents' => $row['contents'], 'teachersNotes' => $row['teachersNotes'], 'tawasulUnitBlockID' => $tawasulUnitBlockID);
                            $sql = 'UPDATE tawasulUnitClassBlock SET title=:title, type=:type, length=:length, contents=:contents, teachersNotes=:teachersNotes WHERE tawasulUnitBlockID=:tawasulUnitBlockID';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                    }

                    //RETURN
                    if ($partialFail == true) {
                        $URL .= '&copyReturn=error6';
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
