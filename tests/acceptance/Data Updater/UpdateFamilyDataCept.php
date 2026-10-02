<?php 
/**
 * @covers modules/TawasulDataUpdater/data_family.php
 * @covers modules/TawasulDataUpdater/data_family_manage_edit.php
 * @covers modules/TawasulDataUpdater/data_family_manage_delete.php
 * @covers modules/TawasulUserAdmin/family_manage_edit.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('submit and approve a family data update');
$I->loginAsParent();
$I->amOnModulePage('Data Updater', 'data_family.php');

// Select ------------------------------------------------
$I->seeBreadcrumb('Update Family Data');

$I->selectFromDropdown('tawasulFamilyID', 1);
$I->click('Submit');

// Update ------------------------------------------------
$I->see('Update Data');

$editFormValues = array(
    'nameAddress'           => '234',
    'homeAddress'           => '234 Ficticious Ave.',
    'homeAddressDistrict'   => 'Somewhere',
    'homeAddressCountry'    => 'Antarctica',
    'languageHomePrimary'   => 'English',
    'languageHomeSecondary' => 'Latin',
);
// $I->fillField('existing', 'N');

$I->submitForm('#content form[method="post"]', $editFormValues, 'Submit');

// Confirm ------------------------------------------------
$I->seeSuccessMessage();

$tawasulFamilyID = $I->grabValueFromURL('tawasulFamilyID');

$I->amOnModulePage('Data Updater', 'data_family.php', ['tawasulFamilyID' => $tawasulFamilyID]);
$I->seeInFormFields('#content form[method="post"]', $editFormValues);

$tawasulFamilyUpdateID = $I->grabValueFrom("input[type='hidden'][name='existing']");

$I->click('Logout', 'a');

// Accept ------------------------------------------------
$I->loginAsAdmin();
$I->amOnModulePage('Data Updater', 'data_family_manage_edit.php', array('tawasulFamilyUpdateID' => $tawasulFamilyUpdateID));
$I->seeBreadcrumb('Edit Request');

$I->see('234', 'td');
$I->see('234 Ficticious Ave.', 'td');
$I->see('Somewhere', 'td');
$I->see('Antarctica', 'td');
$I->see('English', 'td');
$I->see('Latin', 'td');

$I->click('Submit');
$I->seeSuccessMessage();

$tawasulFamilyUpdateID = $I->grabValueFromURL('tawasulFamilyUpdateID');

// Delete ------------------------------------------------
$I->amOnModulePage('Data Updater', 'data_family_manage_delete.php', array('tawasulFamilyUpdateID' => $tawasulFamilyUpdateID));

$I->click('Delete');
$I->seeSuccessMessage();

// Reset Data ------------------------------------------------
$I->amOnModulePage('User Admin', 'family_manage_edit.php', array('tawasulFamilyID' => $tawasulFamilyID));

$editFormValues = array(
    'name'                  => 'Testing Family',
    'status'                => 'Other',
    'languageHomePrimary'   => 'Mongolian',
    'languageHomeSecondary' => 'Latin',
    'nameAddress'           => 'Mr. & Mrs. Test Family Too',
    'homeAddress'           => '123 Nowhere St.',
    'homeAddressDistrict'   => 'Testland',
    'homeAddressCountry'    => 'Antarctica',
);

$I->submitForm('#content form', $editFormValues, 'Submit');
