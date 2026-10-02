<?php
/**
 * @covers modules/TawasulUserAdmin/district_manage.php
 * @covers modules/TawasulUserAdmin/district_manage_add.php
 * @covers modules/TawasulUserAdmin/district_manage_edit.php
 * @covers modules/TawasulUserAdmin/district_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add, edit and delete a district');
$I->loginAsAdmin();
$I->amOnModulePage('User Admin', 'district_manage.php');

// Add ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add District');

$addFormValues = array(
    'name' => 'Test District',
);

$I->submitForm('#content form', $addFormValues, 'Submit');
$I->seeSuccessMessage();

$tawasulDistrictID = $I->grabEditIDFromURL();

// Edit ------------------------------------------------
$I->amOnModulePage('User Admin', 'district_manage_edit.php', array('tawasulDistrictID' => $tawasulDistrictID));
$I->seeBreadcrumb('Edit District');

$I->seeInFormFields('#content form', $addFormValues);

$editFormValues = array(
    'name' => 'Test District Too?',
);

$I->submitForm('#content form', $editFormValues, 'Submit');
$I->seeSuccessMessage();

// Delete ------------------------------------------------
$I->amOnModulePage('User Admin', 'district_manage_delete.php', array('tawasulDistrictID' => $tawasulDistrictID));

$I->click('Delete');
$I->seeSuccessMessage();
