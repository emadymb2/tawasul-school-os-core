<?php
/**
 * @covers modules/TawasulSchoolAdmin/schoolYearSpecialDay_manage.php
 * @covers modules/TawasulSchoolAdmin/schoolYearSpecialDay_manage_add.php
 * @covers modules/TawasulSchoolAdmin/schoolYearSpecialDay_manage_edit.php
 * @covers modules/TawasulSchoolAdmin/schoolYearSpecialDay_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage school year special days');
$I->loginAsAdmin();

$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$I->amOnModulePage('School Admin', 'schoolYearSpecialDay_manage.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID
]);
$I->seeBreadcrumb('Manage Special Days');

// Basic Check -----------------------------------------

$I->dontSeeErrors();

// Add Special Day -------------------------------------
// Click the first add link in a calendar cell
$I->click('//a[contains(@href, "schoolYearSpecialDay_manage_add.php")]');
$I->seeBreadcrumb('Add Special Day');

$I->selectFromDropdown('type', 1);

$formValues = [
    'name' => 'Test Special Day',
    'description' => 'This is a test special day',
];

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

// Grab the ID from the database since it's not in the URL
$tawasulSchoolYearSpecialDayID = $I->grabFromDatabase('tawasulSchoolYearSpecialDay', 'tawasulSchoolYearSpecialDayID', ['name' => 'Test Special Day']);

// Edit Special Day ------------------------------------

$I->amOnModulePage('School Admin', 'schoolYearSpecialDay_manage_edit.php', [
    'tawasulSchoolYearSpecialDayID' => $tawasulSchoolYearSpecialDayID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
]);
$I->seeBreadcrumb('Edit Special Day');

$I->seeInField('name', 'Test Special Day');

$formValues = [
    'name' => 'Updated Test Special Day',
    'description' => 'This is an updated test special day',
];

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

// Delete Special Day ----------------------------------

$I->amOnModulePage('School Admin', 'schoolYearSpecialDay_manage_delete.php', [
    'tawasulSchoolYearSpecialDayID' => $tawasulSchoolYearSpecialDayID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
]);

$I->click('Delete');
$I->seeSuccessMessage();
