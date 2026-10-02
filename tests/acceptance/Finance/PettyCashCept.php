<?php
/**
 * @covers modules/TawasulFinance/pettyCash.php
 * @covers modules/TawasulFinance/pettyCash_action.php
 * @covers modules/TawasulFinance/pettyCash_addEdit.php
 * @covers modules/TawasulFinance/pettyCash_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add, edit, action and delete petty cash transactions');
$I->loginAsAdmin();

$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$I->amOnModulePage('Finance', 'pettyCash.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);
$I->seeBreadcrumb('Petty Cash');

// Add ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Transaction');

// Select a person
$I->selectFromDropdown('tawasulPersonID', 1);

$formValues = [
    'amount' => '50.00',
    'reason' => 'Supplies',
    'actionRequired' => 'Repay',
];

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$tawasulFinancePettyCashID = $I->grabEditIDFromURL();

// Edit ------------------------------------------------
$I->amOnModulePage('Finance', 'pettyCash_addEdit.php', [
    'mode' => 'edit',
    'tawasulFinancePettyCashID' => $tawasulFinancePettyCashID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID
]);
$I->seeBreadcrumb('Edit Transaction');

$I->seeInField('amount', '50.00');

// Update amount
$I->fillField('amount', '75.00');

$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

// Action (Mark as Complete) --------------------------
$I->amOnModulePage('Finance', 'pettyCash_action.php', [
    'tawasulFinancePettyCashID' => $tawasulFinancePettyCashID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'action' => 'Complete'
]);

$I->fillField('statusDate', date('d/m/Y'));
$I->fillField('statusTime', date('H:i'));
$I->fillField('notes', 'Transaction completed');

$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

// Delete ------------------------------------------------
// Create a new transaction to delete
$I->amOnModulePage('Finance', 'pettyCash.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);
$I->clickNavigation('Add');

$I->selectFromDropdown('tawasulPersonID', 1);

$formValues = [
    'amount' => '25.00',
    'reason' => 'Supplies',
    'actionRequired' => 'None',
];

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$tawasulFinancePettyCashIDToDelete = $I->grabEditIDFromURL();

$I->amOnModulePage('Finance', 'pettyCash_delete.php', [
    'tawasulFinancePettyCashID' => $tawasulFinancePettyCashIDToDelete
]);

$I->click('Delete');
$I->seeSuccessMessage();
