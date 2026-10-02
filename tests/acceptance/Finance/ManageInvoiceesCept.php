<?php
/**
 * @covers modules/TawasulFinance/invoicees_manage.php
 * @covers modules/TawasulFinance/invoicees_manage_edit.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage invoicees');
$I->loginAsAdmin();
$I->amOnModulePage('Finance', 'invoicees_manage.php');
$I->seeBreadcrumb('Manage Invoicees');

// Search Test -----------------------------------------

$I->fillField('search', 'test');
$I->submitForm('#action', []);
$I->dontSeeErrors();

// Edit ------------------------------------------------

// Get an existing invoicee
$tawasulFinanceInvoiceeID = $I->grabFromDatabase('tawasulFinanceInvoicee', 'tawasulFinanceInvoiceeID', []);

if (!$tawasulFinanceInvoiceeID) {
    $I->comment('No invoicees found, skipping edit test');
    return;
}

$I->amOnModulePage('Finance', 'invoicees_manage_edit.php', [
    'tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID
]);
$I->seeBreadcrumb('Edit Invoicee');

// Test changing to Company
$I->click('input[name="invoiceTo"][value="Company"]');

$formValues = [
    'invoiceTo' => 'Company',
    'companyName' => 'Test Company',
    'companyContact' => 'John Doe',
    'companyAddress' => '123 Test Street',
    'companyEmail' => 'test@company.com',
    'companyCCFamily' => 'Y',
    'companyPhone' => '1234567890',
    'companyAll' => 'Y',
];

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

// Verify changes
$I->amOnModulePage('Finance', 'invoicees_manage_edit.php', [
    'tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID
]);

$I->seeInField('companyName', 'Test Company');
$I->seeInField('companyContact', 'John Doe');

// Change back to Family
$I->click('input[name="invoiceTo"][value="Family"]');
$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();
