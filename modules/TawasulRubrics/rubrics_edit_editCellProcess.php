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

$_POST = $container->get(Validator::class)->sanitize($_POST, ['*' => 'HTML']);

//Search & Filters
$search = $_GET['search'] ?? '';

$filter2 = $_GET['filter2'] ?? '';


$tawasulRubricID = $_GET['tawasulRubricID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/rubrics_edit.php&tawasulRubricID=$tawasulRubricID&sidebar=false&search=$search&filter2=$filter2";

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
                    $URL .= '&columnDeleteReturn=error2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($result->rowCount() != 1) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                } else {
                    $partialFail = false;

                    //Add in all cells
                    $cells = $_POST['cell'] ?? [];
                    for ($i = 0; $i < count($cells); ++$i) {
                        if ($_POST['tawasulRubricColumnID'][$i] == '' or $_POST['tawasulRubricRowID'][$i] == '') {
                            $partialFail = true;
                        } else {
                            //CELL DOES NOT EXIST YET
                            if ($_POST['tawasulRubricCellID'][$i] == '') {
                                try {
                                    $data = ['tawasulRubricID' => $tawasulRubricID, 'tawasulRubricColumnID' => $_POST['tawasulRubricColumnID'][$i], 'tawasulRubricRowID' => $_POST['tawasulRubricRowID'][$i], 'contents' => $_POST['cell'][$i]];
                                    $sql = 'INSERT INTO tawasulRubricCell SET tawasulRubricID=:tawasulRubricID, tawasulRubricColumnID=:tawasulRubricColumnID, tawasulRubricRowID=:tawasulRubricRowID, contents=:contents';
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                                } catch (PDOException $e) {
                                    $partialFail = true;
                                }
                            }
                            //CELL EXISTS
                            else {
                                try {
                                    $data = ['tawasulRubricID' => $tawasulRubricID, 'tawasulRubricColumnID' => $_POST['tawasulRubricColumnID'][$i], 'tawasulRubricRowID' => $_POST['tawasulRubricRowID'][$i], 'contents' => $_POST['cell'][$i], 'tawasulRubricCellID' => $_POST['tawasulRubricCellID'][$i]];
                                    $sql = 'UPDATE tawasulRubricCell SET tawasulRubricID=:tawasulRubricID, tawasulRubricColumnID=:tawasulRubricColumnID, tawasulRubricRowID=:tawasulRubricRowID, contents=:contents WHERE tawasulRubricCellID=:tawasulRubricCellID';
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                                } catch (PDOException $e) {
                                    $partialFail = true;
                                }
                            }
                        }
                    }

                    //Deal with partial fail and success
                    if ($partialFail == true) {
                        $URL .= '&return=error5';
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
