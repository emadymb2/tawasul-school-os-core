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

$tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? '';
$tawasulCourseID = $_POST['tawasulCourseID'] ?? '';
$tawasulUnitID = $_POST['tawasulUnitID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/units_delete.php&tawasulUnitID=$tawasulUnitID&tawasulCourseID=$tawasulCourseID&tawasulSchoolYearID=$tawasulSchoolYearID";
$URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/units.php&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseID=$tawasulCourseID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        //Proceed!
        if ($tawasulSchoolYearID == '' or $tawasulCourseID == '' or $tawasulUnitID == '') {
            $URL .= '&return=warning2';
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
                $URL .= '&deleteReturn=error4';
                header("Location: {$URL}");
            } else {
                //Check existence of specified unit

                    $data = array('tawasulUnitID' => $tawasulUnitID, 'tawasulCourseID' => $tawasulCourseID);
                    $sql = 'SELECT * FROM tawasulUnit WHERE tawasulUnitID=:tawasulUnitID AND tawasulCourseID=:tawasulCourseID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);

                if ($result->rowCount() != 1) {
                    $URL .= '&deleteReturn=error4';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    try {
                        $data = array('tawasulUnitID' => $tawasulUnitID);
                        $sql = 'DELETE FROM tawasulUnitClass WHERE tawasulUnitID=:tawasulUnitID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    try {
                        $data = array('tawasulUnitID' => $tawasulUnitID);
                        $sql = 'DELETE FROM tawasulUnitBlock WHERE tawasulUnitID=:tawasulUnitID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    try {
                        $data = array('tawasulUnitID' => $tawasulUnitID);
                        $sql = 'DELETE FROM tawasulUnitOutcome WHERE tawasulUnitID=:tawasulUnitID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    try {
                        $data = array('tawasulUnitID' => $tawasulUnitID);
                        $sql = 'DELETE FROM tawasulUnit WHERE tawasulUnitID=:tawasulUnitID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    $URLDelete = $URLDelete.'&return=success0';
                    header("Location: {$URLDelete}");
                }
            }
        }
    }
}
