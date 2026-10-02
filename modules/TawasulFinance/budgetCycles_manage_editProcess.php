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
use TawasulOS\Services\Format;
use TawasulOS\Domain\Finance\FinanceBudgetCycleGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulFinanceBudgetCycleID = $_GET['tawasulFinanceBudgetCycleID'] ?? '';
$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address).'/budgetCycles_manage_edit.php&tawasulFinanceBudgetCycleID='.$tawasulFinanceBudgetCycleID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/budgetCycles_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulFinanceBudgetCycleID specified
    if ($tawasulFinanceBudgetCycleID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $result = $container->get(FinanceBudgetCycleGateway::class)->getByID($tawasulFinanceBudgetCycleID);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        if (empty($result)) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
        } else {
            //Validate Inputs
            $name = $_POST['name'] ?? '';
            $status = $_POST['status'] ?? '';
            $sequenceNumber = $_POST['sequenceNumber'] ?? '';
            $dateStart = !empty($_POST['dateStart']) ? Format::dateConvert($_POST['dateStart']) : null;
            $dateEnd = !empty($_POST['dateEnd']) ? Format::dateConvert($_POST['dateEnd']) : null;
            
            if ($name == '' or $status == '' or $sequenceNumber == '' or is_numeric($sequenceNumber) == false or $dateStart == '' or $dateEnd == '') {
                $URL .= '&return=error1';
                header("Location: {$URL}");
            } else {
                //Check unique inputs for uniquness
                try {
                    $data = array('name' => $name, 'sequenceNumber' => $sequenceNumber, 'tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID);
                    $sql = 'SELECT * FROM tawasulFinanceBudgetCycle WHERE (name=:name OR sequenceNumber=:sequenceNumber) AND NOT tawasulFinanceBudgetCycleID=:tawasulFinanceBudgetCycleID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($result->rowCount() > 0) {
                    $URL .= '&return=error7';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    try {
                        $data = array('name' => $name, 'status' => $status, 'sequenceNumber' => $sequenceNumber, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID);
                        $sql = 'UPDATE tawasulFinanceBudgetCycle SET name=:name, status=:status, sequenceNumber=:sequenceNumber, dateStart=:dateStart, dateEnd=:dateEnd WHERE tawasulFinanceBudgetCycleID=:tawasulFinanceBudgetCycleID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    //UPDATE CYCLE ALLOCATION VALUES
                    $partialFail = false;
                    if (isset($_POST['values'])) {
                        $values = $_POST['values'] ?? [];
                        $tawasulFinanceBudgetIDs = $_POST['tawasulFinanceBudgetIDs'] ?? [];
                        $count = 0;
                        foreach ($values as $value) {
                            $failThis = false;

                            try {
                                $dataCheck = array('tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID, 'tawasulFinanceBudgetID' => $tawasulFinanceBudgetIDs[$count]);
                                $sqlCheck = 'SELECT * FROM tawasulFinanceBudgetCycleAllocation WHERE tawasulFinanceBudgetCycleID=:tawasulFinanceBudgetCycleID AND tawasulFinanceBudgetID=:tawasulFinanceBudgetID';
                                $resultCheck = $connection2->prepare($sqlCheck);
                                $resultCheck->execute($dataCheck);
                            } catch (PDOException $e) {
                                $partialFail = true;
                                $failThis = true;
                            }

                            if ($failThis == false) {
                                try {
                                    $data = array('value' => $value, 'tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID, 'tawasulFinanceBudgetID' => $tawasulFinanceBudgetIDs[$count]);
                                    if ($resultCheck->rowCount() == 0) { //INSERT
                                        $sql = 'INSERT INTO tawasulFinanceBudgetCycleAllocation SET value=:value, tawasulFinanceBudgetCycleID=:tawasulFinanceBudgetCycleID, tawasulFinanceBudgetID=:tawasulFinanceBudgetID';
                                    } else { //UPDATE
                                        $sql = 'UPDATE tawasulFinanceBudgetCycleAllocation SET value=:value WHERE tawasulFinanceBudgetCycleID=:tawasulFinanceBudgetCycleID AND tawasulFinanceBudgetID=:tawasulFinanceBudgetID';
                                    }
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                                } catch (PDOException $e) {
                                    $partialFail = true;
                                }
                            }
                            ++$count;
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
