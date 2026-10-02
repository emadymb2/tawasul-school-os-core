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

require_once __DIR__ . '/../../tawasul.php';

$tawasulCourseID = $_POST['tawasulCourseID'] ?? '';
$tawasulCourseIDCopyTo = $_POST['tawasulCourseIDCopyTo'] ?? '';
$tawasulSchoolYearID = $_POST['tawasulSchoolYearID'] ?? '';
$action = $_POST['action'] ?? '';

if ($tawasulCourseID == '' or $tawasulCourseIDCopyTo == '' or $tawasulSchoolYearID == '' or $action == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/units.php&tawasulCourseID=$tawasulCourseID&tawasulSchoolYearID=$tawasulSchoolYearID";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulPlanner/units.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        $units = $_POST['tawasulUnitID'] ?? [];

        //Proceed!
        //Check if person specified
        if (count($units) < 1) {
            $URL .= '&return=error3';
            header("Location: {$URL}");
        } else {
            $partialFail = false;
            if ($action == 'Duplicate') {
                foreach ($units AS $tawasulUnitID) { //For every unit to be copied
                    //Check existence of unit and fetch details
                    try {
                        $data = array('tawasulUnitID' => $tawasulUnitID);
                        $sql = 'SELECT * FROM tawasulUnit WHERE tawasulUnitID=:tawasulUnitID';
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
                        $name = $row['name'];
                        if ($tawasulCourseIDCopyTo == $tawasulCourseID) {
                            $name .= ' (Copy)';
                        }

                        //Write the duplicate to the database
                        try {
                            $data = array('tawasulCourseID' => $tawasulCourseIDCopyTo, 'name' => $name, 'description' => $row['description'], 'map' => $row['map'], 'tags' => $row['tags'], 'ordering' => $row['ordering'], 'attachment' => $row['attachment'], 'details' => $row['details'], 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'), 'tawasulPersonIDLastEdit' => $session->get('tawasulPersonID'));
                            $sql = 'INSERT INTO tawasulUnit SET tawasulCourseID=:tawasulCourseID, name=:name, description=:description, map=:map, tags=:tags, ordering=:ordering, attachment=:attachment, details=:details ,tawasulPersonIDCreator=:tawasulPersonIDCreator, tawasulPersonIDLastEdit=:tawasulPersonIDLastEdit';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $URL .= '&return=error2';
                            header("Location: {$URL}");
                            exit();
                        }

                        //Last insert ID
                        $AI = str_pad($connection2->lastInsertID(), 10, '0', STR_PAD_LEFT);

                        //Copy Outcomes
                        try {
                            $dataOutcomes = array('tawasulUnitID' => $tawasulUnitID);
                            $sqlOutcomes = 'SELECT * FROM tawasulUnitOutcome WHERE tawasulUnitID=:tawasulUnitID';
                            $resultOutcomes = $connection2->prepare($sqlOutcomes);
                            $resultOutcomes->execute($dataOutcomes);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                        if ($resultOutcomes->rowCount() > 0) {
                            while ($rowOutcomes = $resultOutcomes->fetch()) {
                                //Write to database
                                try {
                                    $dataCopy = array('tawasulUnitID' => $AI, 'tawasulOutcomeID' => $rowOutcomes['tawasulOutcomeID'], 'sequenceNumber' => $rowOutcomes['sequenceNumber'], 'content' => $rowOutcomes['content']);
                                    $sqlCopy = 'INSERT INTO tawasulUnitOutcome SET tawasulUnitID=:tawasulUnitID, tawasulOutcomeID=:tawasulOutcomeID, sequenceNumber=:sequenceNumber, content=:content';
                                    $resultCopy = $connection2->prepare($sqlCopy);
                                    $resultCopy->execute($dataCopy);
                                } catch (PDOException $e) {
                                    $partialFail = true;
                                }
                            }
                        }

                        //Copy smart blocks
                        try {
                            $dataBlocks = array('tawasulUnitID' => $tawasulUnitID);
                            $sqlBlocks = 'SELECT * FROM tawasulUnitBlock WHERE tawasulUnitID=:tawasulUnitID ORDER BY sequenceNumber';
                            $resultBlocks = $connection2->prepare($sqlBlocks);
                            $resultBlocks->execute($dataBlocks);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                        while ($rowBlocks = $resultBlocks->fetch()) {
                            try {
                                $dataBlock = array('tawasulUnitID' => $AI, 'title' => $rowBlocks['title'], 'type' => $rowBlocks['type'], 'length' => $rowBlocks['length'], 'contents' => $rowBlocks['contents'], 'teachersNotes' => $rowBlocks['teachersNotes'], 'sequenceNumber' => $rowBlocks['sequenceNumber']);
                                $sqlBlock = 'INSERT INTO tawasulUnitBlock SET tawasulUnitID=:tawasulUnitID, title=:title, type=:type, length=:length, contents=:contents, teachersNotes=:teachersNotes, sequenceNumber=:sequenceNumber';
                                $resultBlock = $connection2->prepare($sqlBlock);
                                $resultBlock->execute($dataBlock);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        }
                    }
                }
            }
            else {
                $URL .= '&return=error3';
                header("Location: {$URL}");
            }

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
