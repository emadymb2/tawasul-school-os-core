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

$_POST = $container->get(Validator::class)->sanitize($_POST, ['contents*' => 'HTML', 'teachersNotes*' => 'HTML']);

//Module includes
include './moduleFunctions.php';

$tawasulPlannerEntryID = $_POST['tawasulPlannerEntryID'] ?? '';
$mode = $_POST['mode'] ?? '';
$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulPlanner/planner_view_full.php&tawasulPlannerEntryID=$tawasulPlannerEntryID".($_POST['params'] ?? '');

if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/planner_view_full.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if planner specified
        if ($tawasulPlannerEntryID == '' or $mode == '' or ($mode != 'view' and $mode != 'edit')) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                $sql = 'SELECT * FROM tawasulPlannerEntry WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
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

                $partialFail = false;
                if ($mode == 'view') {
                    $ids = $_POST['tawasulUnitClassBlockID'] ?? '';
                    for ($i = 0; $i < count($ids); ++$i) {
                        if ($ids[$i] == '') {
                            $partialFail = true;
                        } else {
                            $complete = 'N';
                            if (isset($_POST["complete$i"])) {
                                if ($_POST["complete$i"] == 'on') {
                                    $complete = 'Y';
                                }
                            }
                            //Write to database
                            try {
                                $data = array('complete' => $complete, 'tawasulUnitClassBlockID' => $ids[$i]);
                                $sql = 'UPDATE tawasulUnitClassBlock SET complete=:complete WHERE tawasulUnitClassBlockID=:tawasulUnitClassBlockID';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                echo 'Here';
                                $partialFail = true;
                            }
                        }
                    }
                } else {
                    $order = $_POST['order'] ?? [];
                    $seq = $_POST['minSeq'] ?? '';

                    $summaryBlocks = '';
                    foreach ($order as $i) {
                        $id = $_POST["tawasulUnitClassBlockID$i"] ?? '';
                        $title = $_POST["title$i"] ?? '';
                        $summaryBlocks .= $title.', ';
                        $type = $_POST["type$i"] ?? '';
                        $length = $_POST["length$i"] ?? '';
                        $contents = $_POST["contents$i"] ?? '';
                        $teachersNotes = $_POST["teachersNotes$i"] ?? '';
                        $complete = 'N';
                        if (isset($_POST["complete$i"])) {
                            if ($_POST["complete$i"] == 'on') {
                                $complete = 'Y';
                            }
                        }

                        //Write to database
                        try {
                            $data = array('title' => $title, 'type' => $type, 'length' => $length, 'contents' => $contents, 'teachersNotes' => $teachersNotes, 'complete' => $complete, 'sequenceNumber' => $seq, 'tawasulUnitClassBlockID' => $id);
                            $sql = 'UPDATE tawasulUnitClassBlock SET title=:title, type=:type, length=:length, contents=:contents, teachersNotes=:teachersNotes, complete=:complete, sequenceNumber=:sequenceNumber WHERE tawasulUnitClassBlockID=:tawasulUnitClassBlockID';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                        ++$seq;
                    }

                    $summaryBlocks = substr($summaryBlocks, 0, -2);
                    if (strlen($summaryBlocks) > 75) {
                        $summaryBlocks = substr($summaryBlocks, 0, 72).'...';
                    }
                    if ($summaryBlocks) {
                        $summary = $summaryBlocks;
                    }

                    //Write to database
                    try {
                        $data = array('summary' => $summary, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID);
                        $sql = 'UPDATE tawasulPlannerEntry SET summary=:summary WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }
                }

                //Return final verdict
                if ($partialFail == true) {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                } else {
                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
        }
    }
}
