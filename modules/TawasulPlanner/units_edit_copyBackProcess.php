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
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/units_edit_copyBack.php&tawasulUnitID=$tawasulUnitID&tawasulCourseID=$tawasulCourseID&tawasulCourseClassID=$tawasulCourseClassID&tawasulSchoolYearID=$tawasulSchoolYearID";
$URLCopy = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/units_edit.php&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseID=$tawasulCourseID&tawasulUnitID=$tawasulUnitID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_edit_copyBack.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        //Proceed!
        if ($tawasulSchoolYearID == '' or $tawasulCourseID == '' or $tawasulCourseClassID == '' or $tawasulUnitID == '') {
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
                    //Write to database
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
                        $dataBlocks = array('tawasulUnitID' => $tawasulUnitID, 'tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulCourseClassID' => $tawasulCourseClassID);
                        $sqlBlocks = 'SELECT * FROM tawasulUnitClass JOIN tawasulUnitClassBlock ON (tawasulUnitClassBlock.tawasulUnitClassID=tawasulUnitClass.tawasulUnitClassID) JOIN tawasulPlannerEntry ON (tawasulPlannerEntry.tawasulPlannerEntryID=tawasulUnitClassBlock.tawasulPlannerEntryID) WHERE tawasulUnitClass.tawasulUnitID=:tawasulUnitID AND tawasulUnitClass.tawasulCourseClassID=:tawasulCourseClassID AND tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID ORDER BY sequenceNumber';
                        $resultBlocks = $connection2->prepare($sqlBlocks);
                        $resultBlocks->execute($dataBlocks);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    $partialFail = false;
                    while ($rowBlocks = $resultBlocks->fetch()) {
                        try {
                            $dataBlock = array('tawasulUnitID' => $tawasulUnitID, 'title' => $rowBlocks['title'], 'type' => $rowBlocks['type'], 'length' => $rowBlocks['length'], 'contents' => $rowBlocks['contents'], 'sequenceNumber' => $rowBlocks['sequenceNumber']);
                            $sqlBlock = 'INSERT INTO tawasulUnitBlock SET tawasulUnitID=:tawasulUnitID, title=:title, type=:type, length=:length, contents=:contents, sequenceNumber=:sequenceNumber';
                            $resultBlock = $connection2->prepare($sqlBlock);
                            $resultBlock->execute($dataBlock);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                    }

                    if ($partialFail == true) {
                        $URL .= '&copyReturn=error6';
                        header("Location: {$URL}");
                    } else {
                        $URLCopy = $URLCopy.'&return=success2';
                        header("Location: {$URLCopy}");
                    }
                }
            }
        }
    }
}
