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
use TawasulOS\Services\Format;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['body' => 'HTML']);

//Module includes
include './moduleFunctions.php';

$tawasulFinanceBudgetCycleID = $_POST['tawasulFinanceBudgetCycleID'] ?? '';
$tawasulFinanceBudgetID2 = $_POST['tawasulFinanceBudgetID2'] ?? '';
$status2 = $_POST['status2'] ?? '';

if ($tawasulFinanceBudgetCycleID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/expenses_manage_add.php&tawasulFinanceBudgetCycleID=$tawasulFinanceBudgetCycleID&tawasulFinanceBudgetID2=$tawasulFinanceBudgetID2&status2=$status2";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/expenses_manage_add.php', 'Manage Expenses_all') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        $allowExpenseAdd = $container->get(SettingGateway::class)->getSettingByScope('Finance', 'allowExpenseAdd');
        if ($allowExpenseAdd != 'Y') {
            $URL .= '&return=error0';
            header("Location: {$URL}");
        } else {
            $tawasulFinanceBudgetID = $_POST['tawasulFinanceBudgetID'] ?? '';
            $status = $_POST['status'] ?? '';
            $title = $_POST['title'] ?? '';
            $body = $_POST['body'] ?? '';
            $cost = $_POST['cost'] ?? '';
            $countAgainstBudget = $_POST['countAgainstBudget'] ?? '';
            $purchaseBy = $_POST['purchaseBy'] ?? '';
            $purchaseDetails = $_POST['purchaseDetails'] ?? '';
            if ($status == 'Paid') {
                $paymentDate = !empty($_POST['paymentDate']) ? Format::dateConvert($_POST['paymentDate']) : null;
                $paymentAmount = $_POST['paymentAmount'] ?? '';
                $tawasulPersonIDPayment = $_POST['tawasulPersonIDPayment'] ?? '';
                $paymentMethod = $_POST['paymentMethod'] ?? '';
                $paymentID = $_POST['paymentID'] ?? '';
            } else {
                $paymentDate = null;
                $paymentAmount = null;
                $tawasulPersonIDPayment = null;
                $paymentMethod = null;
                $paymentID = null;
            }

            if ($status == '' or $title == '' or $cost == '' or $countAgainstBudget == '' or $purchaseBy == '' or ($status == 'Paid' and ($paymentDate == '' or $paymentAmount == '' or $tawasulPersonIDPayment == '' or $paymentMethod == ''))) {
                $URL .= '&return=error1';
                header("Location: {$URL}");
            } else {
                //Write to database
                try {
                    $data = array('tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID, 'tawasulFinanceBudgetID' => $tawasulFinanceBudgetID, 'title' => $title, 'body' => $body, 'status' => $status, 'statusApprovalBudgetCleared' => 'Y', 'cost' => $cost, 'countAgainstBudget' => $countAgainstBudget, 'purchaseBy' => $purchaseBy, 'purchaseDetails' => $purchaseDetails, 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'), 'paymentDate' => $paymentDate, 'paymentAmount' => $paymentAmount, 'tawasulPersonIDPayment' => $tawasulPersonIDPayment, 'paymentMethod' => $paymentMethod, 'paymentID' => $paymentID);
                    $sql = "INSERT INTO tawasulFinanceExpense SET tawasulFinanceBudgetCycleID=:tawasulFinanceBudgetCycleID, tawasulFinanceBudgetID=:tawasulFinanceBudgetID, title=:title, body=:body, status=:status, statusApprovalBudgetCleared=:statusApprovalBudgetCleared, cost=:cost, countAgainstBudget=:countAgainstBudget, purchaseBy=:purchaseBy, purchaseDetails=:purchaseDetails, tawasulPersonIDCreator=:tawasulPersonIDCreator, timestampCreator='".date('Y-m-d H:i:s')."', paymentDate=:paymentDate, paymentAmount=:paymentAmount, tawasulPersonIDPayment=:tawasulPersonIDPayment, paymentMethod=:paymentMethod, paymentID=:paymentID";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                $tawasulFinanceExpenseID = $connection2->lastInsertID();

                //Add log entry
                try {
                    $data = array('tawasulFinanceExpenseID' => $tawasulFinanceExpenseID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
                    $sql = "INSERT INTO tawasulFinanceExpenseLog SET tawasulFinanceExpenseID=:tawasulFinanceExpenseID, tawasulPersonID=:tawasulPersonID, timestamp='".date('Y-m-d H:i:s')."', action='Approval - Exempt', comment=''";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                //Last insert ID
                $AI = str_pad($connection2->lastInsertID(), 14, '0', STR_PAD_LEFT);

                //Add Payment log entry if needed
                if ($status == 'Paid') {
                    try {
                        $data = array('tawasulFinanceExpenseID' => $tawasulFinanceExpenseID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
                        $sql = "INSERT INTO tawasulFinanceExpenseLog SET tawasulFinanceExpenseID=:tawasulFinanceExpenseID, tawasulPersonID=:tawasulPersonID, timestamp='".date('Y-m-d H:i:s')."', action='Payment', comment=''";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }
                }

                $URL .= "&return=success0&editID=$tawasulFinanceExpenseID";
                header("Location: {$URL}");
            }
        }
    }
}
