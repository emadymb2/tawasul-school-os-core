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

$_POST = $container->get(Validator::class)->sanitize($_POST, ['body' => 'HTML']);

//Module includes
include './moduleFunctions.php';

$tawasulFinanceBudgetCycleID = $_POST['tawasulFinanceBudgetCycleID'] ?? '';
$tawasulFinanceBudgetID = $_POST['tawasulFinanceBudgetID'] ?? '';
$status = $_POST['status'] ?? '';
$tawasulFinanceBudgetID2 = $_POST['tawasulFinanceBudgetID2'] ?? '';
$status2 = $_POST['status2'] ?? '';

if ($tawasulFinanceBudgetCycleID == '' or $tawasulFinanceBudgetID == '' or $status == '' or $status != 'Requested') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/expenseRequest_manage_add.php&tawasulFinanceBudgetCycleID=$tawasulFinanceBudgetCycleID&tawasulFinanceBudgetID2=$tawasulFinanceBudgetID2&status2=$status2";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/expenseRequest_manage_add.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        $title = $_POST['title'] ?? '';
        $body = $_POST['body'] ?? '';
        $cost = $_POST['cost'] ?? '';
        $countAgainstBudget = $_POST['countAgainstBudget'] ?? '';
        $purchaseBy = $_POST['purchaseBy'] ?? '';
        $purchaseDetails = $_POST['purchaseDetails'] ?? '';

        if ($title == '' or $cost == '' or $purchaseBy == '' or $countAgainstBudget == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            //Prepare approval settings
            $budgetLevelExpenseApproval = $container->get(SettingGateway::class)->getSettingByScope('Finance', 'budgetLevelExpenseApproval');
            if ($budgetLevelExpenseApproval == '') {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            } else {
                if ($budgetLevelExpenseApproval == 'N') { //Skip budget-level approval
                    $statusApprovalBudgetCleared = 'Y';
                } else {
                    $budgets = getBudgetsByPerson($connection2, $session->get('tawasulPersonID'), $tawasulFinanceBudgetID);
                    if (@$budgets[0][2] == 'Full') { //I can self-approve budget-level, as have Full access
                        $statusApprovalBudgetCleared = 'Y';
                    } else { //I cannot self-approve budget-level
                        $statusApprovalBudgetCleared = 'N';
                    }
                }
            }

            //Write to database
            try {
                $data = array('tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID, 'tawasulFinanceBudgetID' => $tawasulFinanceBudgetID, 'title' => $title, 'body' => $body, 'status' => $status, 'statusApprovalBudgetCleared' => $statusApprovalBudgetCleared, 'cost' => $cost, 'countAgainstBudget' => $countAgainstBudget, 'purchaseBy' => $purchaseBy, 'purchaseDetails' => $purchaseDetails, 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'));
                $sql = "INSERT INTO tawasulFinanceExpense SET tawasulFinanceBudgetCycleID=:tawasulFinanceBudgetCycleID, tawasulFinanceBudgetID=:tawasulFinanceBudgetID, title=:title, body=:body, status=:status, statusApprovalBudgetCleared=:statusApprovalBudgetCleared, cost=:cost, countAgainstBudget=:countAgainstBudget, purchaseBy=:purchaseBy, purchaseDetails=:purchaseDetails, tawasulPersonIDCreator=:tawasulPersonIDCreator, timestampCreator='".date('Y-m-d H:i:s')."'";
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            $tawasulFinanceExpenseID = str_pad($connection2->lastInsertID(), 14, '0', STR_PAD_LEFT);

            //Add log entry
            try {
                $data = array('tawasulFinanceExpenseID' => $tawasulFinanceExpenseID, 'tawasulPersonID' => $session->get('tawasulPersonID'));
                $sql = "INSERT INTO tawasulFinanceExpenseLog SET tawasulFinanceExpenseID=:tawasulFinanceExpenseID, tawasulPersonID=:tawasulPersonID, timestamp='".date('Y-m-d H:i:s')."', action='Request', comment=''";
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            //Last insert ID
            $AI = str_pad($connection2->lastInsertID(), 14, '0', STR_PAD_LEFT);

            //Do notifications
            $partialFail = false;
            if (setExpenseNotification($guid, $tawasulFinanceExpenseID, $tawasulFinanceBudgetCycleID, $connection2) == false) {
                $partialFail = true;
            }

            if ($partialFail == true) {
                $URL .= "&return=success1&editID=$AI";
                header("Location: {$URL}");
            } else {
                $URL .= "&return=success0&editID=$AI";
                header("Location: {$URL}");
            }
        }
    }
}
