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
use TawasulOS\Domain\Rubrics\RubricGateway;
use TawasulOS\Domain\Rubrics\RubricRowGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

include './moduleFunctions.php';

//Search & Filters
$search = $_GET['search'] ?? '';

$filter2 = $_GET['filter2'] ?? '';


$tawasulRubricID = $_GET['tawasulRubricID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/rubrics_duplicate.php&tawasulRubricID=$tawasulRubricID&search=$search&filter2=$filter2";

if (isActionAccessible($guid, $connection2, '/modules/TawasulRubrics/rubrics_duplicate.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
    } else {
        if ($highestAction != 'Manage Rubrics_viewEditAll' and $highestAction != 'Manage Rubrics_viewAllEditLearningArea') {
            $URL .= '&return=error0';
            header("Location: {$URL}");
        } else {
            //Proceed!
            //Check if tawasulRubricID specified
            if ($tawasulRubricID == '') {
                $URL .= '&return=error1';
                header("Location: {$URL}");
            } else {
                try {
                    if ($highestAction == 'Manage Rubrics_viewEditAll') {
                        $result = $container->get(RubricGateway::class)->selectBy(['tawasulRubricID' => $tawasulRubricID]);
                    } elseif ($highestAction == 'Manage Rubrics_viewAllEditLearningArea') {
                        $result = $container->get(RubricGateway::class)->selectLARubricsByStaffAndDepartment($tawasulRubricID, $session->get('tawasulPersonID'));
                    }
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
                    //Proceed!
                    $scope = $_POST['scope'] ?? '';
                    $tawasulDepartmentID = null;
                    if ($scope == 'Learning Area') {
                        $tawasulDepartmentID = !empty($_POST['tawasulDepartmentID'])? $_POST['tawasulDepartmentID'] : $row['tawasulDepartmentID'];
                    }
                    $name = $_POST['name'] ?? '';

                    if ($scope == '' or ($scope == 'Learning Area' and $tawasulDepartmentID == null) or $name == '') {
                        $URL .= '&return=error3';
                        header("Location: {$URL}");
                    } else {
                        //Write to database
                        try {
                            $data = array('scope' => $scope, 'tawasulDepartmentID' => $tawasulDepartmentID, 'name' => $name, 'active' => $row['active'], 'category' => $row['category'], 'description' => $row['description'], 'tawasulYearGroupIDList' => $row['tawasulYearGroupIDList'], 'tawasulScaleID' => $row['tawasulScaleID'], 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'));
                            $sql = 'INSERT INTO tawasulRubric SET scope=:scope, tawasulDepartmentID=:tawasulDepartmentID, name=:name, active=:active, category=:category, description=:description, tawasulYearGroupIDList=:tawasulYearGroupIDList, tawasulScaleID=:tawasulScaleID, tawasulPersonIDCreator=:tawasulPersonIDCreator';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $URL .= '&return=error2';
                            header("Location: {$URL}");
                            exit();
                        }

                        //Get last insert ID
                        $AI = str_pad($connection2->lastInsertID(), 8, '0', STR_PAD_LEFT);

                        $partialFail = false;

                        //INSERT ROWS
                        $rows = array();
                        try {

                            $resultFetch = $container->get(RubricGateway::class)->selectRowsByRubricInSequence($tawasulRubricID);

                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                        while ($rowFetch = $resultFetch->fetch()) {
                            try {
                                $dataInsert = array('tawasulRubricID' => $AI, 'title' => $rowFetch['title'], 'sequenceNumber' => $rowFetch['sequenceNumber'], 'tawasulOutcomeID' => $rowFetch['tawasulOutcomeID'], 'backgroundColor' => $rowFetch['backgroundColor']);
                                $sqlInsert = 'INSERT INTO tawasulRubricRow SET tawasulRubricID=:tawasulRubricID, title=:title, sequenceNumber=:sequenceNumber, tawasulOutcomeID=:tawasulOutcomeID, backgroundColor=:backgroundColor';
                                $resultInsert = $connection2->prepare($sqlInsert);
                                $resultInsert->execute($dataInsert);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                            $rows[$rowFetch['tawasulRubricRowID']] = str_pad($connection2->lastInsertID(), 9, '0', STR_PAD_LEFT);
                        }

                        //INSERT COLUMNS
                        $columns = array();
                        try {
                            $resultFetch = $container->get(RubricGateway::class)->selectColumnsByRubric($tawasulRubricID);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                        while ($rowFetch = $resultFetch->fetch()) {
                            try {
                                $dataInsert = array('tawasulRubricID' => $AI, 'title' => $rowFetch['title'], 'sequenceNumber' => $rowFetch['sequenceNumber'], 'tawasulScaleGradeID' => $rowFetch['tawasulScaleGradeID'], 'backgroundColor' => $rowFetch['backgroundColor']);
                                $sqlInsert = 'INSERT INTO tawasulRubricColumn SET tawasulRubricID=:tawasulRubricID, title=:title, sequenceNumber=:sequenceNumber, tawasulScaleGradeID=:tawasulScaleGradeID, backgroundColor=:backgroundColor';
                                $resultInsert = $connection2->prepare($sqlInsert);
                                $resultInsert->execute($dataInsert);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                            $columns[$rowFetch['tawasulRubricColumnID']] = str_pad($connection2->lastInsertID(), 9, '0', STR_PAD_LEFT);
                        }

                        //INSERT CELLS
                        try {
                            $resultFetch = $container->get(RubricGateway::class)->selectCellsByRubric($tawasulRubricID);
                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                        while ($rowFetch = $resultFetch->fetch()) {
                            try {
                                $dataInsert = array('tawasulRubricID' => $AI, 'tawasulRubricColumnID' => $columns[$rowFetch['tawasulRubricColumnID']], 'tawasulRubricRowID' => $rows[$rowFetch['tawasulRubricRowID']], 'contents' => $rowFetch['contents']);
                                $sqlInsert = 'INSERT INTO tawasulRubricCell SET tawasulRubricID=:tawasulRubricID, tawasulRubricColumnID=:tawasulRubricColumnID, tawasulRubricRowID=:tawasulRubricRowID, contents=:contents';
                                $resultInsert = $connection2->prepare($sqlInsert);
                                $resultInsert->execute($dataInsert);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
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
        }
    }
}
