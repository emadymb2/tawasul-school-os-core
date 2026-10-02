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

use TawasulOS\Http\Url;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\User\UserGateway;
use Tos\Module\TawasulFinance\Tables\ExpenseLog;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulFinance/expenses_manage_view.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        // Proceed!
        $tawasulFinanceBudgetCycleID = $_GET['tawasulFinanceBudgetCycleID'] ?? '';

        $urlParams = compact('tawasulFinanceBudgetCycleID');

        $page->breadcrumbs
            ->add(__('Manage Expenses'), 'expenses_manage.php', $urlParams)
            ->add(__('View Expense'));

        // Check if params are specified
        $tawasulFinanceExpenseID = $_GET['tawasulFinanceExpenseID'] ?? '';
        $status2 = $_GET['status2'] ?? '';
        $tawasulFinanceBudgetID2 = $_GET['tawasulFinanceBudgetID2'] ?? '';
        if ($tawasulFinanceExpenseID == '' or $tawasulFinanceBudgetCycleID == '') {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {
            // Check if you have Full or Write access in any budgets
            $budgets = getBudgetsByPerson($connection2, $session->get('tawasulPersonID'));
            $budgetsAccess = false;
            if ($highestAction == 'Manage Expenses_all') { // Access to everything
                $budgetsAccess = true;
            } else {
                // Check if you have Full or Write access in any budgets
                $budgets = getBudgetsByPerson($connection2, $session->get('tawasulPersonID'));
                if (is_array($budgets) && count($budgets) > 0) {
                    foreach ($budgets as $budget) {
                        if ($budget[2] == 'Full' or $budget[2] == 'Write') {
                            $budgetsAccess = true;
                        }
                    }
                }
            }

            if ($budgetsAccess == false) {
                $page->addError(__('You do not have Full or Write access to any budgets.'));
            } else {
                // Get and check settings
                $settingGateway = $container->get(SettingGateway::class);
                $expenseApprovalType = $settingGateway->getSettingByScope('Finance', 'expenseApprovalType');
                $budgetLevelExpenseApproval = $settingGateway->getSettingByScope('Finance', 'budgetLevelExpenseApproval');
                $expenseRequestTemplate = $settingGateway->getSettingByScope('Finance', 'expenseRequestTemplate');
                if ($expenseApprovalType == '' or $budgetLevelExpenseApproval == '') {
                    $page->addError(__('An error has occurred with your expense and budget settings.'));
                } else {
                    // Check if there are approvers
                    try {
                        $data = array();
                        $sql = "SELECT * FROM tawasulFinanceExpenseApprover JOIN tawasulPerson ON (tawasulFinanceExpenseApprover.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE status='Full'";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                    }

                    if ($result->rowCount() < 1) {
                        $page->addError(__('An error has occurred with your expense and budget settings.'));
                    } else {
                        // Ready to go! Just check record exists and we have access, and load it ready to use...
                        try {
                            // Set Up filter wheres
                            $data = array('tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID, 'tawasulFinanceExpenseID' => $tawasulFinanceExpenseID);
                            // GET THE DATA ACCORDING TO FILTERS
                            if ($highestAction == 'Manage Expenses_all') { // Access to everything
                                $sql = "SELECT tawasulFinanceExpense.*, tawasulFinanceBudget.name AS budget, surname, preferredName, 'Full' AS access
									FROM tawasulFinanceExpense
									JOIN tawasulFinanceBudget ON (tawasulFinanceExpense.tawasulFinanceBudgetID=tawasulFinanceBudget.tawasulFinanceBudgetID)
									JOIN tawasulPerson ON (tawasulFinanceExpense.tawasulPersonIDCreator=tawasulPerson.tawasulPersonID)
									WHERE tawasulFinanceBudgetCycleID=:tawasulFinanceBudgetCycleID AND tawasulFinanceExpenseID=:tawasulFinanceExpenseID
									ORDER BY FIND_IN_SET(tawasulFinanceExpense.status, 'Pending,Issued,Paid,Refunded,Cancelled'), timestampCreator DESC";
                            } else { // Access only to own budgets
                                $data['tawasulPersonID'] = $session->get('tawasulPersonID');
                                $sql = "SELECT tawasulFinanceExpense.*, tawasulFinanceBudget.name AS budget, surname, preferredName, access
									FROM tawasulFinanceExpense
									JOIN tawasulFinanceBudget ON (tawasulFinanceExpense.tawasulFinanceBudgetID=tawasulFinanceBudget.tawasulFinanceBudgetID)
									JOIN tawasulFinanceBudgetPerson ON (tawasulFinanceBudgetPerson.tawasulFinanceBudgetID=tawasulFinanceBudget.tawasulFinanceBudgetID)
									JOIN tawasulPerson ON (tawasulFinanceExpense.tawasulPersonIDCreator=tawasulPerson.tawasulPersonID)
									WHERE tawasulFinanceBudgetCycleID=:tawasulFinanceBudgetCycleID AND tawasulFinanceExpenseID=:tawasulFinanceExpenseID AND tawasulFinanceBudgetPerson.tawasulPersonID=:tawasulPersonID
									ORDER BY FIND_IN_SET(tawasulFinanceExpense.status, 'Pending,Issued,Paid,Refunded,Cancelled'), timestampCreator DESC";
                            }
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                        }

                        if ($result->rowCount() != 1) {
                            $page->addError(__('The specified record cannot be found.'));
                        } else {
                            // Let's go!
                            $values = $result->fetch();

                            if ($status2 != '' or $tawasulFinanceBudgetID2 != '') {
                                $params = [
                                    "tawasulFinanceBudgetCycleID" => $tawasulFinanceBudgetCycleID,
                                    "status2" => $status2,
                                    "tawasulFinanceBudgetID2" => $tawasulFinanceBudgetID2,
                                ];
                                $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulFinance', 'expenses_manage.php')->withQueryParams($params));
                            }

                            $form = Form::create('action', '');

                            $form->addHiddenValue('address', $session->get('address'));
                            $form->addHiddenValue('status2', $status2);
                            $form->addHiddenValue('tawasulFinanceBudgetID2', $tawasulFinanceBudgetID2);
                            $form->addHiddenValue('tawasulFinanceExpenseID', $tawasulFinanceExpenseID);
                            $form->addHiddenValue('tawasulFinanceBudgetCycleID', $tawasulFinanceBudgetCycleID);
                            $form->addHiddenValue('tawasulFinanceBudgetID', $values['tawasulFinanceBudgetID']);

                            $form->addRow()->addHeading('Basic Information', __('Basic Information'));

                            $cycleName = getBudgetCycleName($tawasulFinanceBudgetCycleID, $connection2);
                            $row = $form->addRow();
                                $row->addLabel('name', __('Budget Cycle'));
                                $row->addTextField('name')->setValue($cycleName)->maxLength(20)->required()->readonly();

                            $row = $form->addRow();
                                $row->addLabel('budget', __('Budget'));
                                $row->addTextField('budget')->setValue($values['budget'])->maxLength(20)->required()->readonly();

                            $row = $form->addRow();
                                $row->addLabel('title', __('Title'));
                                $row->addTextField('title')->maxLength(60)->required()->readonly()->setValue($values['title']);

                            $row = $form->addRow();
                                $row->addLabel('status', __('Status'));
                                $row->addTextField('status')->maxLength(60)->required()->readonly()->setValue(__($values['status']));

                            $row = $form->addRow();
                            $column = $row->addColumn();
                                $column->addLabel('body', __('Description'));
                                $column->addContent($values['body'])->setClass('w-full');

                            $row = $form->addRow();
                                $row->addLabel('cost', __('Total Cost'));
                                $row->addCurrency('cost')->required()->maxLength(15)->readonly()->setValue($values['cost']);

                            $row = $form->addRow();
                                $row->addLabel('countAgainstBudget', __('Count Against Budget'));
                                $row->addTextField('countAgainstBudget')->maxLength(3)->required()->readonly()->setValue(Format::yesNo($values['countAgainstBudget']));

                            $row = $form->addRow();
                                $row->addLabel('purchaseBy', __('Purchase By'));
                                $row->addTextField('purchaseBy')->required()->readonly()->setValue(__($values['purchaseBy']));

                            $row = $form->addRow();
                            $column = $row->addColumn();
                                $column->addLabel('purchaseDetails', __('Purchase Details'));
                                $column->addContent($values['purchaseDetails'])->setClass('w-full');

                            $form->addRow()->addHeading('Log', __('Log'));
                                $expenseLog = $container->get(ExpenseLog::class)->create($tawasulFinanceExpenseID);
                                $form->addRow()->addContent($expenseLog->getOutput());

                            if ($values['status'] == 'Paid') {

                                $form->addRow()->addHeading('PaymentInformation', __('Payment Information'));

                                $row = $form->addRow();
                                    $row->addLabel('paymentDate', __('Date Paid'))->description(__('Date of payment, not entry to system'));
                                    $row->addDate('paymentDate')->maxLength(10)->required()->readonly()->setValue(Format::date($values['paymentDate']));

                                $row = $form->addRow();
                                    $row->addLabel('paymentAmount', __('Amount Paid'))->description(__('Final amount paid'));
                                    $row->addCurrency('paymentAmount')->required()->maxLength(10)->readonly()->setValue($values['paymentAmount']);


                                $paymentPerson = $container->get(UserGateway::class)->getByID($values['tawasulPersonIDPayment'], ['surname', 'preferredName']);
                                $row = $form->addRow();
                                    $row->addLabel('payee', __('Payee'))->description(__('Staff who made, or arranged, the payment'));
                                    $row->addTextField('payee')->setValue(Format::name('', $paymentPerson['surname'], $paymentPerson['preferredName'], 'Staff', true, true))->required()->readonly();

                                $row = $form->addRow();
                                    $row->addLabel('paymentMethod', __('Payment Method'));
                                    $row->addTextField('paymentMethod')->maxLength(10)->required()->readonly()->setValue(($values['paymentMethod']));

                                $row = $form->addRow();
                                    $row->addLabel('paymentID', __('Payment ID'))->description(__('Transaction ID to identify this payment'));
                                    $row->addTextField('paymentID')->maxLength(100)->required()->readonly()->setValue(($values['paymentID']));
                            }

                            echo $form->getOutput();
                        }
                    }
                }
            }
        }
    }
}
