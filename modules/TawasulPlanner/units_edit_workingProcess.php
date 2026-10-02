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
use TawasulOS\Domain\Timetable\CourseGateway;
use TawasulOS\Domain\Planner\PlannerEntryGateway;
use TawasulOS\Domain\Planner\UnitClassBlockGateway;
use TawasulOS\Domain\Planner\UnitGateway;
use TawasulOS\Domain\Planner\UnitBlockGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['contents*' => 'HTML', 'teachersNotes*' => 'HTML']);

$tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
$tawasulCourseID = $_GET['tawasulCourseID'] ?? '';
$tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
$tawasulUnitID = $_GET['tawasulUnitID'] ?? '';
$tawasulUnitClassID = $_GET['tawasulUnitClassID'] ?? '';
$unitBlockCount = $_POST['unitBlockCount'] ?? 0;

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/units_edit_working.php&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulCourseID=$tawasulCourseID&tawasulUnitID=$tawasulUnitID&tawasulCourseClassID=$tawasulCourseClassID&tawasulUnitClassID=$tawasulUnitClassID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units_edit_working.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0";
        header("Location: {$URL}");
        exit;
    } 

    // Validate Inputs
    if (empty($tawasulSchoolYearID) || empty($tawasulCourseID) || empty($tawasulUnitID) ) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
        exit;
    }

    $validator = $container->get(Validator::class);
    $courseGateway = $container->get(CourseGateway::class);
    $unitGateway = $container->get(UnitGateway::class);
    $plannerGateway = $container->get(PlannerEntryGateway::class);
    $unitBlockGateway = $container->get(UnitBlockGateway::class);
    $unitClassBlockGateway = $container->get(UnitClassBlockGateway::class);

    // Check access to specified course
    if ($highestAction == 'Unit Planner_all') {
        $result = $courseGateway->selectCourseDetailsByClass($tawasulCourseClassID);
    } elseif ($highestAction == 'Unit Planner_learningAreas') {
        $result = $courseGateway->selectCourseDetailsByClassAndPerson($tawasulCourseClassID, $session->get('tawasulPersonID'));
    }

    if ($result->rowCount() != 1) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
        exit;
    } 

    // Check existence of specified unit
    if (!$unitGateway->exists($tawasulUnitID) || !$courseGateway->exists($tawasulCourseID)) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
        exit;
    } 

    $partialFail = false;
    
    $blockIDs = [];
    $blocks = $_POST['blocks'] ?? [];
    $lessons = $_POST['lessons'] ?? [];
    $lessonDetails = [];

    $tawasulPlannerEntryID = 0;
    $sequenceNumber = 0;

    foreach ($blocks as $blockIndex => $block) {

        if (substr($blockIndex, 0, 6) == 'lesson') {
            $tawasulPlannerEntryID = $block;
            if (!empty($lessons[$tawasulPlannerEntryID])) {
                $lessonDetails[$tawasulPlannerEntryID]['name'] = $lessons[$tawasulPlannerEntryID];
            }
            continue;
        }

        $tawasulUnitClassBlockID = $block['tawasulUnitClassBlockID'] ?? null;

        $blockData = [
            'tawasulUnitClassID'    => $tawasulUnitClassID,
            'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
            'complete'             => $block['complete'] ?? 'N',
        ];

        $data = [
            'tawasulUnitBlockID'    => $block['tawasulUnitBlockID'] ?? '',
            'title'                => $block['title'] ?? '',
            'type'                 => $block['type'] ?? '',
            'length'               => $block['length'] ?? '',
            'contents'             => $block['contents'] ?? '',
            'teachersNotes'        => $block['teachersNotes'] ?? '',
            'sequenceNumber'       => $sequenceNumber,
        ];

        // Add new unit blocks
        if (empty($data['tawasulUnitBlockID'])) {
            $data['tawasulUnitBlockID'] = $unitBlockGateway->insert([
                'tawasulUnitID'   => $tawasulUnitID,
                'sequenceNumber' => $unitBlockCount + 1,
            ] + $data);
        }

        if (!empty($tawasulUnitClassBlockID) && $existingBlock = $unitClassBlockGateway->getByID($tawasulUnitClassBlockID)) {
            $unitClassBlockGateway->update($tawasulUnitClassBlockID, $blockData + $data);
        } else {
            $tawasulUnitClassBlockID = $unitClassBlockGateway->insert($blockData + $data);
        }

        // Update lesson details based on the first block
        if (empty($lessonDetails[$tawasulPlannerEntryID])) {
            $contents = strip_tags($data['contents']);
            $lessonDetails[$tawasulPlannerEntryID]['summary'] = strlen($contents) > 72 ? substr($contents, 0, 72) : $contents;
        }

        if (!empty($tawasulUnitClassBlockID)) {
            $tawasulUnitClassBlockID = str_pad($tawasulUnitClassBlockID, 14, '0', STR_PAD_LEFT);
            $blockIDs[] = $tawasulUnitClassBlockID;
        } else {
            $partialFail = true;
        }

        ++$sequenceNumber;
    }

    // Remove deleted blocks
    $unitClassBlockGateway->deleteBlocksNotInList($tawasulUnitClassID, $blockIDs);

    // Update lesson details
    foreach ($lessonDetails as $tawasulPlannerEntryID => $details) {
        $plannerGateway->update($tawasulPlannerEntryID, $details);
    }

    //RETURN
    if ($partialFail == true) {
        $URL .= '&updateReturn=error6';
        header("Location: {$URL}");
        exit;
    } else {
        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit;
    }
}
