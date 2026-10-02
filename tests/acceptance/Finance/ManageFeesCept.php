<?php
/**
 * @covers modules/TawasulFinance/fees_manage.php
 * @covers modules/TawasulFinance/fees_manage_add.php
 * @covers modules/TawasulFinance/fees_manage_edit.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add and edit fees');
$I->loginAsAdmin();

$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$I->amOnModulePage('Finance', 'fees_manage.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);
$I->seeBreadcrumb('Manage Fees');

// Add ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Fee');

$I->selectFromDropdown('tawasulFinanceFeeCategoryID', 1);

$formValues = [
    'name' => 'Test Fee',
    'nameShort' => 'TF',
    'active' => 'Y',
    'description' => 'Test fee description',
    'fee' => '100.00',
];

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$tawasulFinanceFeeID = $I->grabEditIDFromURL();

// Edit ------------------------------------------------
$I->amOnModulePage('Finance', 'fees_manage_edit.php', [
    'tawasulFinanceFeeID' => $tawasulFinanceFeeID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID
]);
$I->seeBreadcrumb('Edit Fee');

$I->seeInFormFields('#content form', [
    'name' => 'Test Fee',
    'nameShort' => 'TF',
]);

$I->selectFromDropdown('tawasulFinanceFeeCategoryID', 2);

$formValues = [
    'name' => 'Updated Test Fee',
    'nameShort' => 'UTF',
    'active' => 'N',
    'description' => 'Updated description',
    'fee' => '150.00',
];

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();
