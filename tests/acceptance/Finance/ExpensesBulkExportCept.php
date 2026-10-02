<?php
/**
 * @covers modules/TawasulFinance/expenses_manage_processBulkExportContents.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('export expenses in bulk');
$I->loginAsAdmin();

// Get a budget cycle
$tawasulFinanceBudgetCycleID = $I->grabFromDatabase('tawasulFinanceBudgetCycle', 'tawasulFinanceBudgetCycleID', ['status' => 'Current']);

if (!$tawasulFinanceBudgetCycleID) {
    $I->comment('No current budget cycle found, skipping bulk export test');
    return;
}

// Get a budget
$tawasulFinanceBudgetID = $I->grabFromDatabase('tawasulFinanceBudget', 'tawasulFinanceBudgetID', ['active' => 'Y']);

if (!$tawasulFinanceBudgetID) {
    $I->comment('No active budget found, skipping bulk export test');
    return;
}

// Create a test expense for export
$tawasulFinanceExpenseID = $I->haveInDatabase('tawasulFinanceExpense', [
    'tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID,
    'tawasulFinanceBudgetID' => $tawasulFinanceBudgetID,
    'tawasulPersonIDCreator' => $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', []),
    'title' => 'Test Expense for Export',
    'body' => 'Test expense description',
    'status' => 'Approved',
    'cost' => 100.00,
    'countAgainstBudget' => 'Y',
    'purchaseBy' => 'School',
    'timestampCreator' => date('Y-m-d H:i:s'),
]);

// Set the session variable for export
$I->haveHttpHeader('Cookie', 'financeExpenseExportIDs=' . serialize([$tawasulFinanceExpenseID]));

// Navigate to the export page
$I->amOnModulePage('Finance', 'expenses_manage_processBulkExportContents.php', [
    'tawasulFinanceBudgetCycleID' => $tawasulFinanceBudgetCycleID
]);

$I->dontSeeErrors();
