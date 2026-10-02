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
use TawasulOS\Comms\NotificationSender;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Finance\FinanceExpenseApproverGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

//Module includes
include './moduleFunctions.php';

$tawasulFinanceBudgetCycleID = $_POST['tawasulFinanceBudgetCycleID'] ?? '';
$tawasulFinanceBudgetID2 = $_POST['tawasulFinanceBudgetID2'] ?? '';
$tawasulFinanceExpenseID = $_POST['tawasulFinanceExpenseID'] ?? '';
$status2 = $_POST['status2'] ?? '';
$countAgainstBudget = $_POST['countAgainstBudget'] ?? '';
$status = $_POST['status'] ?? '';

if ($tawasulFinanceBudgetCycleID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/expenses_manage_edit.php&tawasulFinanceExpenseID=$tawasulFinanceExpenseID&tawasulFinanceBudgetCycleID=$tawasulFinanceBudgetCycleID&tawasulFinanceBudgetID2=$tawasulFinanceBudgetID2&status2=$status2";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/expenses_manage_add.php', 'Manage Expenses_all') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
        if ($highestAction == false) {
            $URL .= '&return=error0';
            header("Location: {$URL}");
        } else {
            if ($tawasulFinanceExpenseID == '' or $status == '' or $status == 'Please select...' or $countAgainstBudget == '') {
                $URL .= '&return=error1';
                header("Location: {$URL}");
            } else {
                //Get and check settings
                $settingGateway = $container->get(SettingGateway::class);
                $expenseApprovalType = $settingGateway->getSettingByScope('Finance', 'expenseApprovalType');
                $budgetLevelExpenseApproval = $settingGateway->getSettingByScope('Finance', 'budgetLevelExpenseApproval');
                $expenseRequestTemplate = $settingGateway->getSettingByScope('Finance', 'expenseRequestTemplate');
                if ($expenseApprovalType == '' or $budgetLevelExpenseApproval == '') {
                    $URL .= '&return=error1';
                    header("Location: {$URL}");
                } else {
                    //Check if there are approvers
                    $result = $container->get(FinanceExpenseApproverGateway::class)->selectExpenseApprovers();
                    
                    if ($result->rowCount() < 1) {
                        $URL .= '&return=error0';
                        header("Location: {$URL}");
                    } else {
                        //Ready to go! Just check record exists and we have access, and load it ready to use...
                        try {
                            //Set Up filter wheres
                            $data = array('tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID, 'tawasulFinanceExpenseID' => $tawasulFinanceExpenseID);
                            $sql = "SELECT tawasulFinanceExpense.*, tawasulFinanceBudget.name AS budget, surname, preferredName, 'Full' AS access
								FROM tawasulFinanceExpense
								JOIN tawasulFinanceBudget ON (tawasulFinanceExpense.tawasulFinanceBudgetID=tawasulFinanceBudget.tawasulFinanceBudgetID)
								JOIN tawasulPerson ON (tawasulFinanceExpense.tawasulPersonIDCreator=tawasulPerson.tawasulPersonID)
								WHERE tawasulFinanceBudgetCycleID=:tawasulFinanceBudgetCycleID AND tawasulFinanceExpenseID=:tawasulFinanceExpenseID";
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
                            $statusOld = $row['status'];

                            //Check if params are specified
                            if ($status == 'Paid' and ($row['status'] == 'Approved' or $row['status'] == 'Ordered')) {
                                $paymentDate = !empty($_POST['paymentDate']) ? Format::dateConvert($_POST['paymentDate']) : null;
                                $paymentAmount = $_POST['paymentAmount'] ?? '';
                                $tawasulPersonIDPayment = $_POST['tawasulPersonIDPayment'] ?? '';
                                $paymentMethod = $_POST['paymentMethod'] ?? '';
                                $paymentID = $_POST['paymentID'] ?? '';
                            } else {
                                $paymentDate = $row['paymentDate'];
                                $paymentAmount = $row['paymentAmount'];
                                $tawasulPersonIDPayment = $row['tawasulPersonIDPayment'];
                                $paymentMethod = $row['paymentMethod'];
                                $paymentID = $row['paymentID'];
                            }
                            
                            $notificationSender = $container->get(NotificationSender::class);

                            //Do Reimbursement work
                            $paymentReimbursementStatus = null;
                            $reimbursementComment = '';
                            if (isset($_POST['paymentReimbursementStatus'])) {
                                $paymentReimbursementStatus = $_POST['paymentReimbursementStatus'] ?? '';
                                if ($paymentReimbursementStatus != 'Requested' and $paymentReimbursementStatus != 'Complete') {
                                    $paymentReimbursementStatus = null;
                                }
                                if ($row['status'] == 'Paid' and $row['purchaseBy'] == 'Self' and $row['paymentReimbursementStatus'] == 'Requested' and $paymentReimbursementStatus == 'Complete') {
                                    $paymentID = $_POST['paymentID'] ?? '';
                                    $reimbursementComment = $_POST['reimbursementComment'] ?? '';
                                    
                                    $notificationText = sprintf(__('Your reimbursement expense request for "%1$s" in budget "%2$s" has been completed.'), $row['title'], $row['budget']);
                                    $notificationSender->addNotification($row['tawasulPersonIDCreator'], $notificationText, 'Finance', "/index.php?q=/modules/TawasulFinance/expenseRequest_manage_view.php&tawasulFinanceExpenseID=$tawasulFinanceExpenseID&tawasulFinanceBudgetCycleID=$tawasulFinanceBudgetCycleID&status=&tawasulFinanceBudgetID=".$row['tawasulFinanceBudgetID']);
                                    $notificationSender->sendNotifications();

                                    //Write change to log
                                    try {
                                        $data = array('tawasulFinanceExpenseID' => $tawasulFinanceExpenseID, 'tawasulPersonID' => $session->get('tawasulPersonID'), 'action' => 'Reimbursement Completion', 'comment' => $reimbursementComment);
                                        $sql = "INSERT INTO tawasulFinanceExpenseLog SET tawasulFinanceExpenseID=:tawasulFinanceExpenseID, tawasulPersonID=:tawasulPersonID, timestamp='".date('Y-m-d H:i:s')."', action=:action, comment=:comment";
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                    } catch (PDOException $e) {
                                        $URL .= '&return=error2';
                                        header("Location: {$URL}");
                                        exit();
                                    }
                                }
                            }

                            //Write back to tawasulFinanceExpense
                            try {
                                $data = array('tawasulFinanceExpenseID' => $tawasulFinanceExpenseID, 'status' => $status, 'countAgainstBudget' => $countAgainstBudget, 'paymentDate' => $paymentDate, 'paymentAmount' => $paymentAmount, 'tawasulPersonIDPayment' => $tawasulPersonIDPayment, 'paymentMethod' => $paymentMethod, 'paymentID' => $paymentID, 'paymentReimbursementStatus' => $paymentReimbursementStatus);
                                $sql = 'UPDATE tawasulFinanceExpense SET status=:status, countAgainstBudget=:countAgainstBudget, paymentDate=:paymentDate, paymentAmount=:paymentAmount, tawasulPersonIDPayment=:tawasulPersonIDPayment, paymentMethod=:paymentMethod, paymentID=:paymentID, paymentReimbursementStatus=:paymentReimbursementStatus WHERE tawasulFinanceExpenseID=:tawasulFinanceExpenseID';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $URL .= '&return=error2';
                                header("Location: {$URL}");
                                exit();
                            }

                            if ($statusOld != $status) {
                                $action = '';
                                if ($status == 'Requested') {
                                    $action = 'Request';
                                } elseif ($status == 'Approved') {
                                    $action = 'Approval - Exempt';
                                    //Notify original creator that it is approved
                                    $notificationText = sprintf(__('Your expense request for "%1$s" in budget "%2$s" has been fully approved.'), $row['title'], $row['budget']);
                                    $notificationSender->addNotification($row['tawasulPersonIDCreator'], $notificationText, 'Finance', "/index.php?q=/modules/TawasulFinance/expenses_manage_view.php&tawasulFinanceExpenseID=$tawasulFinanceExpenseID&tawasulFinanceBudgetCycleID=$tawasulFinanceBudgetCycleID&status=&tawasulFinanceBudgetID=".$row['tawasulFinanceBudgetID']);
                                    $notificationSender->sendNotifications();
                                    
                                } elseif ($status == 'Rejected') {
                                    $action = 'Rejection';
                                    //Notify original creator that it is rejected
                                    $notificationText = sprintf(__('Your expense request for "%1$s" in budget "%2$s" has been rejected.'), $row['title'], $row['budget']);
                                    $notificationSender->addNotification($row['tawasulPersonIDCreator'], $notificationText, 'Finance', "/index.php?q=/modules/TawasulFinance/expenses_manage_view.php&tawasulFinanceExpenseID=$tawasulFinanceExpenseID&tawasulFinanceBudgetCycleID=$tawasulFinanceBudgetCycleID&status=&tawasulFinanceBudgetID=".$row['tawasulFinanceBudgetID']);
                                    $notificationSender->sendNotifications();
                                } elseif ($status == 'Ordered') {
                                    $action = 'Order';
                                } elseif ($status == 'Paid') {
                                    $action = 'Payment';
                                } elseif ($status == 'Cancelled') {
                                    $action = 'Cancellation';
                                }

                                //Write change to log
                                try {
                                    $data = array('tawasulFinanceExpenseID' => $tawasulFinanceExpenseID, 'tawasulPersonID' => $session->get('tawasulPersonID'), 'action' => $action);
                                    $sql = "INSERT INTO tawasulFinanceExpenseLog SET tawasulFinanceExpenseID=:tawasulFinanceExpenseID, tawasulPersonID=:tawasulPersonID, timestamp='".date('Y-m-d H:i:s')."', action=:action";
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                                } catch (PDOException $e) {
                                    $URL .= '&return=error2';
                                    header("Location: {$URL}");
                                    exit();
                                }
                            }

                            $URL .= '&return=success0';
                            header("Location: {$URL}");
                        }
                    }
                }
            }
        }
    }
}
