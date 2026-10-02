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

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulFinanceExpenseApproverID = $_GET['tawasulFinanceExpenseApproverID'] ?? '';
$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address).'/expenseApprovers_manage_edit.php&tawasulFinanceExpenseApproverID='.$tawasulFinanceExpenseApproverID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/expenseApprovers_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulFinanceExpenseApproverID specified
    if ($tawasulFinanceExpenseApproverID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulFinanceExpenseApproverID' => $tawasulFinanceExpenseApproverID);
            $sql = 'SELECT * FROM tawasulFinanceExpenseApprover WHERE tawasulFinanceExpenseApproverID=:tawasulFinanceExpenseApproverID';
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
            //Validate Inputs
            $tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
            $expenseApprovalType = $container->get(SettingGateway::class)->getSettingByScope('Finance', 'expenseApprovalType');
            $sequenceNumber = null;
            if ($expenseApprovalType == 'Chain Of All') {
                $sequenceNumber = abs($_POST['sequenceNumber']);
            }

            if ($tawasulPersonID == '' or ($expenseApprovalType == 'Y' and $sequenceNumber == '')) {
                $URL .= '&return=error1';
                header("Location: {$URL}");
            } else {
                //Check unique inputs for uniquness
                try {
                    if ($expenseApprovalType == 'Chain Of All') {
                        $data = array('tawasulPersonID' => $tawasulPersonID, 'sequenceNumber' => $sequenceNumber, 'tawasulFinanceExpenseApproverID' => $tawasulFinanceExpenseApproverID);
                        $sql = 'SELECT * FROM tawasulFinanceExpenseApprover WHERE (tawasulPersonID=:tawasulPersonID OR sequenceNumber=:sequenceNumber) AND NOT tawasulFinanceExpenseApproverID=:tawasulFinanceExpenseApproverID';
                    } else {
                        $data = array('tawasulPersonID' => $tawasulPersonID, 'tawasulFinanceExpenseApproverID' => $tawasulFinanceExpenseApproverID);
                        $sql = 'SELECT * FROM tawasulFinanceExpenseApprover WHERE tawasulPersonID=:tawasulPersonID AND NOT tawasulFinanceExpenseApproverID=:tawasulFinanceExpenseApproverID';
                    }
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
                        $data = array('tawasulPersonID' => $tawasulPersonID, 'sequenceNumber' => $sequenceNumber, 'tawasulPersonIDUpdate' => $session->get('tawasulPersonID'), 'timestampUpdate' => date('Y-m-d H:i:s', time()), 'tawasulFinanceExpenseApproverID' => $tawasulFinanceExpenseApproverID);
                        $sql = 'UPDATE tawasulFinanceExpenseApprover SET tawasulPersonID=:tawasulPersonID, sequenceNumber=:sequenceNumber, tawasulPersonIDUpdate=:tawasulPersonIDUpdate, timestampUpdate=:timestampUpdate WHERE tawasulFinanceExpenseApproverID=:tawasulFinanceExpenseApproverID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
        }
    }
}
