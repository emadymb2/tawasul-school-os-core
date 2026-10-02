<?php
/**
 * @covers modules/TawasulFinance/expenses_manage.php
 * @covers modules/TawasulFinance/expenses_manage_add.php
 * @covers modules/TawasulFinance/expenses_manage_edit.php
 * @covers modules/TawasulFinance/expenses_manage_approve.php
 * @covers modules/TawasulFinance/expenses_manage_view.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add, edit, approve and view expenses');
$I->loginAsAdmin();

// Get a budget cycle
$tawasulFinanceBudgetCycleID = $I->grabFromDatabase('tawasulFinanceBudgetCycle', 'tawasulFinanceBudgetCycleID', ['status' => 'Current']);

if (!$tawasulFinanceBudgetCycleID) {
    $I->comment('No current budget cycle found, skipping expense management test');
    return;
}

$I->amOnModulePage('Finance', 'expenses_manage.php', ['tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID]);
$I->seeBreadcrumb('Manage Expenses');

// Add ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Expense');

// Select a budget
$I->selectFromDropdown('tawasulFinanceBudgetID', 1);

$formValues = [
    'title' => 'Test Expense',
    'status' => 'Approved',
    'body' => 'Test expense description',
    'cost' => '500.00',
    'countAgainstBudget' => 'Y',
    'purchaseBy' => 'School',
    'purchaseDetails' => 'Test purchase details',
];

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$tawasulFinanceExpenseID = $I->grabEditIDFromURL();

// Edit ------------------------------------------------
$I->amOnModulePage('Finance', 'expenses_manage_edit.php', [
    'tawasulFinanceExpenseID' => $tawasulFinanceExpenseID,
    'tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID
]);
$I->seeBreadcrumb('Edit Expense');

$I->seeInField('title', 'Test Expense');

// Change status to Paid
$I->selectOption('status', 'Paid');

// Fill in payment information
$I->fillField('paymentDate', date('d/m/Y'));
$I->fillField('paymentAmount', '500.00');
$I->selectFromDropdown('tawasulPersonIDPayment', 1);
$I->selectOption('paymentMethod', 'Bank Transfer');
$I->fillField('paymentID', 'TEST123');

$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

// View ------------------------------------------------
$I->amOnModulePage('Finance', 'expenses_manage_view.php', [
    'tawasulFinanceExpenseID' => $tawasulFinanceExpenseID,
    'tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID
]);
$I->seeBreadcrumb('View Expense');

$I->seeInField('title', 'Test Expense');
$I->seeInField('status', 'Paid');
