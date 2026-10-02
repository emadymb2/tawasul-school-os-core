<?php
/**
 * @covers modules/TawasulFinance/expenseApprovers_manage.php
 * @covers modules/TawasulFinance/expenseApprovers_manage_add.php
 * @covers modules/TawasulFinance/expenseApprovers_manage_edit.php
 * @covers modules/TawasulFinance/expenseApprovers_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add, edit and delete an expense approver');
$I->loginAsAdmin();
$I->amOnModulePage('Finance', 'expenseApprovers_manage.php');
$I->seeBreadcrumb('Manage Expense Approvers');

// Add ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Expense Approver');

$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full']);

$I->selectFromDropdown('tawasulPersonID', 1);

$formValues = array(
    'sequenceNumber' => '1',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');

$tawasulFinanceExpenseApproverID = $I->grabEditIDFromURL();

// Edit ------------------------------------------------
$I->amOnModulePage('Finance', 'expenseApprovers_manage_edit.php', array('tawasulFinanceExpenseApproverID' => $tawasulFinanceExpenseApproverID));
$I->seeBreadcrumb('Edit Expense Approver');

$formValues['sequenceNumber'] = '2';

$I->submitForm('#content form', $formValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');

// Delete ------------------------------------------------
$I->amOnModulePage('Finance', 'expenseApprovers_manage_delete.php', array('tawasulFinanceExpenseApproverID' => $tawasulFinanceExpenseApproverID));

$I->click('Delete');
$I->see('Your request was completed successfully.', '.success');
