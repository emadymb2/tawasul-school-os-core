<?php
/**
 * @covers modules/TawasulFinance/budgets_manage.php
 * @covers modules/TawasulFinance/budgets_manage_add.php
 * @covers modules/TawasulFinance/budgets_manage_edit.php
 * @covers modules/TawasulFinance/budgets_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add, edit and delete a budget');
$I->loginAsAdmin();
$I->amOnModulePage('Finance', 'budgets_manage.php');
$I->seeBreadcrumb('Manage Budgets');

// Add ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Budget');

$formValues = array(
    'name' => 'Test Budget ' . uniqid(),
    'nameShort' => 'TB' . uniqid(),
    'active' => 'Y',
    'category' => 'Other',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');

$tawasulFinanceBudgetID = $I->grabEditIDFromURL();

// Edit ------------------------------------------------
$I->amOnModulePage('Finance', 'budgets_manage_edit.php', array('tawasulFinanceBudgetID' => $tawasulFinanceBudgetID));
$I->seeBreadcrumb('Edit Budget');

$I->seeInFormFields('#content form', array(
    'name' => $formValues['name'],
));

$formValues['name'] = 'Updated Budget ' . uniqid();

$I->submitForm('#content form', $formValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');

// Delete ------------------------------------------------
$I->amOnModulePage('Finance', 'budgets_manage_delete.php', array('tawasulFinanceBudgetID' => $tawasulFinanceBudgetID));

$I->click('Delete');
$I->see('Your request was completed successfully.', '.success');
