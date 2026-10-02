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

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

include './moduleFunctions.php';

//Search & Filters
$search = $_GET['search'] ?? '';

$filter2 = $_GET['filter2'] ?? '';


$tawasulRubricID = $_GET['tawasulRubricID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/rubrics_edit_editRowsColumns.php&tawasulRubricID=$tawasulRubricID&sidebar=true&search=$search&filter2=$filter2";
$URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/rubrics_edit.php&tawasulRubricID=$tawasulRubricID&sidebar=false&search=$search&filter2=$filter2";

if (isActionAccessible($guid, $connection2, '/modules/TawasulRubrics/rubrics_edit.php') == false) {
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
                    $tawasulScaleID = $row['tawasulScaleID'];
                    $partialFail = false;

                    //DEAL WITH ROWS
                    $rowTitles = $_POST['rowTitle'] ?? [];
                    $rowColors = $_POST['rowColor'] ?? [];
                    $rowOutcomes = $_POST['tawasulOutcomeID'] ?? [];
                    $rowIDs = $_POST['tawasulRubricRowID'] ?? [];
                    $count = 0;
                    foreach ($rowIDs as $tawasulRubricRowID) {
                        $type = isset($_POST["type$count"])? $_POST["type$count"] : 'Standalone';
                        $backgroundColor = !empty($rowColors[$count]) ? preg_replace('/[^a-fA-F0-9\#]/', '', mb_substr($rowColors[$count], 0, 7)) : null;

                        if ($type == 'Standalone' or $rowOutcomes[$count] == '') {
                            try {
                                $data = array('title' => $rowTitles[$count], 'backgroundColor' => $backgroundColor ?? null, 'tawasulRubricRowID' => $tawasulRubricRowID);
                                $sql = 'UPDATE tawasulRubricRow SET title=:title, backgroundColor=:backgroundColor, tawasulOutcomeID=NULL WHERE tawasulRubricRowID=:tawasulRubricRowID';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        } elseif ($type == 'Outcome Based') {
                            try {
                                $data = array('tawasulOutcomeID' => $rowOutcomes[$count], 'backgroundColor' => $backgroundColor ?? null, 'tawasulRubricRowID' => $tawasulRubricRowID);
                                $sql = "UPDATE tawasulRubricRow SET title='', backgroundColor=:backgroundColor, tawasulOutcomeID=:tawasulOutcomeID WHERE tawasulRubricRowID=:tawasulRubricRowID";
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        } else {
                            $partialFail = true;
                        }

                        ++$count;
                    }

                    //DEAL WITH COLUMNS
                    //If no grade scale specified
                    if ($row['tawasulScaleID'] == '') {
                        $columnTitles = $_POST['columnTitle'] ?? [];
                        $columnColors = $_POST['columnColor'] ?? [];
                        $columnIDs = $_POST['tawasulRubricColumnID'] ?? [];
                        $columnVisualises = $_POST['columnVisualise'] ?? [];
                        $count = 0;
                        foreach ($columnIDs as $tawasulRubricColumnID) {
                            $visualise = $columnVisualises[$count] ?? 'N';
                            try {
                                $data = array('title' => $columnTitles[$count], 'backgroundColor' => $columnColors[$count] ?? null, 'visualise' => $visualise, 'tawasulRubricColumnID' => $tawasulRubricColumnID);
                                $sql = 'UPDATE tawasulRubricColumn SET title=:title, backgroundColor=:backgroundColor, tawasulScaleGradeID=NULL, visualise=:visualise WHERE tawasulRubricColumnID=:tawasulRubricColumnID';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                            ++$count;
                        }
                    }
                    //If scale specified
                    else {
                        $columnGrades = $_POST['tawasulScaleGradeID'] ?? [];
                        $columnColors = $_POST['columnColor'] ?? [];
                        $columnIDs = $_POST['tawasulRubricColumnID'] ?? [];
                        $columnVisualises = isset($_POST['columnVisualise'])? $_POST['columnVisualise'] : array();
                        $count = 0;
                        foreach ($columnIDs as $tawasulRubricColumnID) {
                            $visualise = $columnVisualises[$count] ?? 'N';
                            try {
                                $data = array('tawasulScaleGradeID' => $columnGrades[$count], 'backgroundColor' => $columnColors[$count] ?? null, 'visualise' => $visualise, 'tawasulRubricColumnID' => $tawasulRubricColumnID);
                                $sql = "UPDATE tawasulRubricColumn SET title='', backgroundColor=:backgroundColor, tawasulScaleGradeID=:tawasulScaleGradeID, visualise=:visualise WHERE tawasulRubricColumnID=:tawasulRubricColumnID";
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                            ++$count;
                        }
                    }

                    if ($partialFail) {
                        $URL .= '&return=warning1';
                        header("Location: {$URL}");
                    } else {
                        $URL = $URLSuccess.'&return=success0#rubricDesign';
                        header("Location: {$URL}");
                    }
                }
            }
        }
    }
}
