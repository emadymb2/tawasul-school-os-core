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
use TawasulOS\Contracts\Filesystem\FileHandler;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

//Module includes
include './moduleFunctions.php';

$tawasulFinanceBudgetCycleID = $_POST['tawasulFinanceBudgetCycleID'] ?? '';
$tawasulFinanceBudgetID = $_POST['tawasulFinanceBudgetID'] ?? '';
$tawasulFinanceExpenseID = $_POST['tawasulFinanceExpenseID'] ?? '';
$status = $_POST['status'] ?? '';
$tawasulFinanceBudgetID2 = $_POST['tawasulFinanceBudgetID2'] ?? '';
$status2 = $_POST['status2'] ?? '';

if ($tawasulFinanceBudgetCycleID == '' or $tawasulFinanceBudgetID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/expenseRequest_manage_reimburse.php&tawasulFinanceExpenseID=$tawasulFinanceExpenseID&tawasulFinanceBudgetCycleID=$tawasulFinanceBudgetCycleID&tawasulFinanceBudgetID2=$tawasulFinanceBudgetID2&status2=$status2";
    $URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/expenseRequest_manage.php&tawasulFinanceExpenseID=$tawasulFinanceExpenseID&tawasulFinanceBudgetCycleID=$tawasulFinanceBudgetCycleID&tawasulFinanceBudgetID2=$tawasulFinanceBudgetID2&status2=$status2";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/expenseRequest_manage_reimburse.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit();
    } else {
        if ($tawasulFinanceExpenseID == '' or $status == '' or $status != 'Paid' or empty($_FILES['file']['tmp_name'])) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit();
        } else {
            //Get and check settings
            $settingGateway = $container->get(SettingGateway::class);
            $expenseApprovalType = $settingGateway->getSettingByScope('Finance', 'expenseApprovalType');
            $budgetLevelExpenseApproval = $settingGateway->getSettingByScope('Finance', 'budgetLevelExpenseApproval');
            $expenseRequestTemplate = $settingGateway->getSettingByScope('Finance', 'expenseRequestTemplate');
            if ($expenseApprovalType == '' or $budgetLevelExpenseApproval == '') {
                $URL .= '&return=error0';
                header("Location: {$URL}");
                exit();
            } else {
                //Check if there are approvers
                try {
                    $result = $container->get(FinanceExpenseApproverGateway::class)->selectExpenseApprovers();
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($result->rowCount() < 1) {
                    $URL .= '&return=error0';
                    header("Location: {$URL}");
                    exit();
                } else {
                    //Ready to go! Just check record exists and we have access, and load it ready to use...
                    try {
                        //Set Up filter wheres
                        $data = array('tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID, 'tawasulFinanceExpenseID' => $tawasulFinanceExpenseID);
                        $sql = "SELECT tawasulFinanceExpense.*, tawasulFinanceBudget.name AS budget, surname, preferredName, 'Full' AS access
							FROM tawasulFinanceExpense
							JOIN tawasulFinanceBudget ON (tawasulFinanceExpense.tawasulFinanceBudgetID=tawasulFinanceBudget.tawasulFinanceBudgetID)
							JOIN tawasulPerson ON (tawasulFinanceExpense.tawasulPersonIDCreator=tawasulPerson.tawasulPersonID)
							WHERE tawasulFinanceBudgetCycleID=:tawasulFinanceBudgetCycleID AND tawasulFinanceExpenseID=:tawasulFinanceExpenseID AND tawasulFinanceExpense.status='Approved'";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    if ($result->rowCount() != 1) {
                        $URL .= '&return=error0';
                        header("Location: {$URL}");
                        exit();
                    } else {
                        $row = $result->fetch();

                        //Get relevant
                        $paymentDate = !empty($_POST['paymentDate']) ? Format::dateConvert($_POST['paymentDate']) : null;
                        $paymentAmount = $_POST['paymentAmount'] ?? '';
                        $tawasulPersonIDPayment = $_POST['tawasulPersonIDPayment'] ?? '';
                        $paymentMethod = $_POST['paymentMethod'] ?? '';
                        $fileMetaData = null;

                        $fileUploader = new TawasulOS\FileUploader($pdo, $session);

                        $file = (isset($_FILES['file']))? $_FILES['file'] : null;

                        // Upload the file, return the /uploads relative path
                        $attachment = $fileUploader->uploadFromPost($file, $row['title']);

                        if (empty($attachment)) {
                            $URL .= '&return=error5';
                            header("Location: {$URL}");
                            exit();
                        } else {
                            $fileMetaData = $fileUploader->getFileMetaData($attachment);
                        }
                        
                        //Write back to tawasulFinanceExpense
                        try {
                            $data = array('tawasulFinanceExpenseID' => $tawasulFinanceExpenseID, 'status' => 'Paid', 'paymentDate' => $paymentDate, 'paymentAmount' => $paymentAmount, 'tawasulPersonIDPayment' => $tawasulPersonIDPayment, 'paymentMethod' => $paymentMethod, 'paymentReimbursementReceipt' => $attachment, 'paymentReimbursementStatus' => 'Requested');
                            $sql = 'UPDATE tawasulFinanceExpense SET status=:status, paymentDate=:paymentDate, paymentAmount=:paymentAmount, tawasulPersonIDPayment=:tawasulPersonIDPayment, paymentMethod=:paymentMethod, paymentReimbursementReceipt=:paymentReimbursementReceipt, paymentReimbursementStatus=:paymentReimbursementStatus WHERE tawasulFinanceExpenseID=:tawasulFinanceExpenseID';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $URL .= '&return=error2';
                            header("Location: {$URL}");
                            exit();
                        }

                        // Record file tracking
                        if (!empty($fileMetaData) && !empty($tawasulFinanceExpenseID)) {
                            $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulFinanceExpense', $tawasulFinanceExpenseID, 'paymentReimbursementReceipt');
                            
                            if (empty($tawasulFileID)) {
                                $URL .= '&return=error2';
                                header("Location: {$URL}");
                                exit();
                            }
                        }

                        //Notify reimbursement officer that action is required
                        $reimbursementOfficer = $settingGateway->getSettingByScope('Finance', 'reimbursementOfficer');
                        $personName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);

                        if ($reimbursementOfficer != false and $reimbursementOfficer != '') {
                            $notificationText = __('{person} has requested reimbursement for {title} in budget {budgetName}.', ['person' => $personName, 'title' => $row['title'], 'budgetName' => $row['budget']]);
                            $notificationSender = $container->get(NotificationSender::class);
                            $notificationSender->addNotification($reimbursementOfficer, $notificationText, 'Finance', "/index.php?q=/modules/TawasulFinance/expenses_manage_edit.php&tawasulFinanceExpenseID=$tawasulFinanceExpenseID&tawasulFinanceBudgetCycleID=$tawasulFinanceBudgetCycleID&status=&tawasulFinanceBudgetID2=".$row['tawasulFinanceBudgetID']);
                            $notificationSender->sendNotifications();
                        }

                        //Write paid change to log
                        try {
                            $data = array('tawasulFinanceExpenseID' => $tawasulFinanceExpenseID, 'tawasulPersonID' => $session->get('tawasulPersonID'), 'action' => 'Payment');
                            $sql = "INSERT INTO tawasulFinanceExpenseLog SET tawasulFinanceExpenseID=:tawasulFinanceExpenseID, tawasulPersonID=:tawasulPersonID, timestamp='".date('Y-m-d H:i:s')."', action=:action";
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $URL .= '&return=error2';
                            header("Location: {$URL}");
                            exit();
                        }

                        //Write reimbursement request change to log
                        try {
                            $data = array('tawasulFinanceExpenseID' => $tawasulFinanceExpenseID, 'tawasulPersonID' => $session->get('tawasulPersonID'), 'action' => 'Reimbursement Request');
                            $sql = "INSERT INTO tawasulFinanceExpenseLog SET tawasulFinanceExpenseID=:tawasulFinanceExpenseID, tawasulPersonID=:tawasulPersonID, timestamp='".date('Y-m-d H:i:s')."', action=:action";
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $URL .= '&return=error2';
                            header("Location: {$URL}");
                            exit();
                        }

                        $URLSuccess .= '&return=success0';
                        header("Location: {$URLSuccess}");
                    }
                }
            }
        }
    }
}
