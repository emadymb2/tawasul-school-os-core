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
$tawasulFinanceExpenseID = $_POST['tawasulFinanceExpenseID'] ?? '';
$status2 = $_POST['status2'] ?? '';
$tawasulFinanceBudgetID2 = $_POST['tawasulFinanceBudgetID2'] ?? '';

if ($tawasulFinanceBudgetCycleID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/expenseRequest_manage_view.php&tawasulFinanceExpenseID=$tawasulFinanceExpenseID&tawasulFinanceBudgetCycleID=$tawasulFinanceBudgetCycleID&tawasulFinanceBudgetID2=$tawasulFinanceBudgetID2&status2=$status2";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/expenseRequest_manage_view.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
        if ($highestAction == false) {
            $URL .= '&return=error0';
            header("Location: {$URL}");
        } else {
            //Check if params are specified
            if ($tawasulFinanceExpenseID == '' or $tawasulFinanceBudgetCycleID == '') {
                $URL .= '&return=error0';
                header("Location: {$URL}");
            } else {
                $budgetsAccess = false;
                if ($highestAction == 'Manage Expenses_all') { //Access to everything {
                    $budgetsAccess = true;
                } else {
                    //Check if have Full or Write in any budgets
                    $budgets = getBudgetsByPerson($connection2, $session->get('tawasulPersonID'));
                    if (is_array($budgets) && count($budgets)>0) {
                        foreach ($budgets as $budget) {
                            if ($budget[2] == 'Full' or $budget[2] == 'Write') {
                                $budgetsAccess = true;
                            }
                        }
                    }
                }

                if ($budgetsAccess == false) {
                    $URL .= '&return=error0';
                    header("Location: {$URL}");
                } else {
                    //Get and check settings
                    $settingGateway = $container->get(SettingGateway::class);
                    $expenseApprovalType = $settingGateway->getSettingByScope('Finance', 'expenseApprovalType');
                    $budgetLevelExpenseApproval = $settingGateway->getSettingByScope('Finance', 'budgetLevelExpenseApproval');
                    $expenseRequestTemplate = $settingGateway->getSettingByScope('Finance', 'expenseRequestTemplate');
                    if ($expenseApprovalType == '' or $budgetLevelExpenseApproval == '') {
                        $URL .= '&return=error0';
                        header("Location: {$URL}");
                    } else {
                        //Check if there are approvers
                        try {
                            $result = $container->get(FinanceExpenseApproverGateway::class)->selectExpenseApprovers();
                        } catch (PDOException $e) {
                        }

                        if ($result->rowCount() < 1) {
                            $URL .= '&return=error0';
                            header("Location: {$URL}");
                        } else {
                            $approvers = $result->fetchAll();

                            $notificationSender = $container->get(NotificationSender::class);

                            //Ready to go! Just check record exists and we have access, and load it ready to use...
                            try {
                                //Set Up filter wheres
                                $data = array('tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID, 'tawasulFinanceExpenseID' => $tawasulFinanceExpenseID);
                                //GET THE DATA ACCORDING TO FILTERS
                                if ($highestAction == 'Manage Expenses_all') { //Access to everything
                                    $sql = "SELECT tawasulFinanceExpense.*, tawasulFinanceBudget.name AS budget, surname, preferredName, 'Full' AS access
										FROM tawasulFinanceExpense
										JOIN tawasulFinanceBudget ON (tawasulFinanceExpense.tawasulFinanceBudgetID=tawasulFinanceBudget.tawasulFinanceBudgetID)
										JOIN tawasulPerson ON (tawasulFinanceExpense.tawasulPersonIDCreator=tawasulPerson.tawasulPersonID)
										WHERE tawasulFinanceBudgetCycleID=:tawasulFinanceBudgetCycleID AND tawasulFinanceExpenseID=:tawasulFinanceExpenseID";
                                } else { //Access only to own budgets
                                    $data['tawasulPersonID'] = $session->get('tawasulPersonID');
                                    $sql = "SELECT tawasulFinanceExpense.*, tawasulFinanceBudget.name AS budget, surname, preferredName, access
										FROM tawasulFinanceExpense
										JOIN tawasulFinanceBudget ON (tawasulFinanceExpense.tawasulFinanceBudgetID=tawasulFinanceBudget.tawasulFinanceBudgetID)
										JOIN tawasulFinanceBudgetPerson ON (tawasulFinanceBudgetPerson.tawasulFinanceBudgetID=tawasulFinanceBudget.tawasulFinanceBudgetID)
										JOIN tawasulPerson ON (tawasulFinanceExpense.tawasulPersonIDCreator=tawasulPerson.tawasulPersonID)
										WHERE tawasulFinanceBudgetCycleID=:tawasulFinanceBudgetCycleID AND tawasulFinanceExpenseID=:tawasulFinanceExpenseID AND tawasulFinanceBudgetPerson.tawasulPersonID=:tawasulPersonID AND (access='Full' OR access='Write')";
                                }
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
                            } else {
                                $row = $result->fetch();

                                $tawasulFinanceBudgetID = $row['tawasulFinanceBudgetID'];
                                $comment = $_POST['comment'] ?? '';

                                //Write comment to log
                                try {
                                    $data = array('tawasulFinanceExpenseID' => $tawasulFinanceExpenseID, 'tawasulPersonID' => $session->get('tawasulPersonID'), 'comment' => $comment);
                                    $sql = "INSERT INTO tawasulFinanceExpenseLog SET tawasulFinanceExpenseID=:tawasulFinanceExpenseID, tawasulPersonID=:tawasulPersonID, timestamp='".date('Y-m-d H:i:s')."', action='Comment', comment=:comment";
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                                } catch (PDOException $e) {
                                    $URL .= '&return=error2';
                                    header("Location: {$URL}");
                                    exit();
                                }

                                //Notify budget holders
                                $personName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);

                                if ($budgetLevelExpenseApproval == 'Y') {
                                    $dataHolder = array('tawasulFinanceBudgetID' => $tawasulFinanceBudgetID);
                                    $sqlHolder = "SELECT * FROM tawasulFinanceBudgetPerson WHERE access='Full' AND tawasulFinanceBudgetID=:tawasulFinanceBudgetID";
                                    $resultHolder = $connection2->prepare($sqlHolder);
                                    $resultHolder->execute($dataHolder);
                                    while ($rowHolder = $resultHolder->fetch()) {
                                        $notificationText = __('{person} has commented on the expense request for {title} in budget {budgetName}.', ['person' => $personName, 'title' => $row['title'], 'budgetName' => $row['budget']]);
                                        $notificationSender->addNotification($rowHolder['tawasulPersonID'], $notificationText, 'Finance', "/index.php?q=/modules/TawasulFinance/expenses_manage_view.php&tawasulFinanceExpenseID=$tawasulFinanceExpenseID&tawasulFinanceBudgetCycleID=$tawasulFinanceBudgetCycleID&status2=&tawasulFinanceBudgetID2=".$row['tawasulFinanceBudgetID']);
                                    }
                                }

                                //Notify approvers that it is commented upon
                                $notificationText = __('{person} has commented on the expense request for {title} in budget {budgetName}.', ['person' => $personName, 'title' => $row['title'], 'budgetName' => $row['budget']]);
                                foreach ($approvers as $approver) {
                                    $notificationSender->addNotification($approver['tawasulPersonID'], $notificationText, 'Finance', "/index.php?q=/modules/TawasulFinance/expenses_manage_view.php&tawasulFinanceExpenseID=$tawasulFinanceExpenseID&tawasulFinanceBudgetCycleID=$tawasulFinanceBudgetCycleID&status2=&tawasulFinanceBudgetID2=".$row['tawasulFinanceBudgetID']);
                                }

                                $notificationSender->sendNotifications();

                                $URL .= '&return=success0';
                                header("Location: {$URL}");
                            }
                        }
                    }
                }
            }
        }
    }
}
