<?php
/**
 * @covers modules/TawasulUserAdmin/family_manage.php
 * @covers modules/TawasulUserAdmin/family_manage_add.php
 * @covers modules/TawasulUserAdmin/family_manage_edit.php
 * @covers modules/TawasulUserAdmin/family_manage_delete.php
 * @covers modules/TawasulUserAdmin/family_manage_edit_editAdult.php
 * @covers modules/TawasulUserAdmin/family_manage_edit_editChild.php
 * @covers modules/TawasulUserAdmin/family_manage_edit_deleteAdult.php
 * @covers modules/TawasulUserAdmin/family_manage_edit_deleteChild.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add, edit and delete a family');
$I->loginAsAdmin();
$I->amOnModulePage('User Admin', 'family_manage.php');

// Add ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Family');

$addFormValues = array(
    'name'                  => 'Test Family',
    'status'                => 'De Facto',
    'languageHomePrimary'   => 'Swedish',
    'languageHomeSecondary' => 'Hindi',
    'nameAddress'           => 'Mr. & Mrs. Test Family',
    'homeAddress'           => '1 2 3 Ficticious Lane',
    'homeAddressDistrict'   => 'Testing',
    'homeAddressCountry'    => 'Hong Kong',
);

$I->submitForm('#content form', $addFormValues, 'Submit');
$I->seeSuccessMessage();

$tawasulFamilyID = $I->grabEditIDFromURL();

// Edit ------------------------------------------------
$I->amOnModulePage('User Admin', 'family_manage_edit.php', array('tawasulFamilyID' => $tawasulFamilyID));
$I->seeBreadcrumb('Edit Family');

$I->seeInFormFields('#content form', $addFormValues);

$editFormValues = array(
    'name'                  => 'Test Family Too',
    'status'                => 'Other',
    'languageHomePrimary'   => 'Mongolian',
    'languageHomeSecondary' => 'Latin',
    'nameAddress'           => 'Mr. & Mrs. Test Family Too',
    'homeAddress'           => '123 Nowhere St.',
    'homeAddressDistrict'   => 'Testland',
    'homeAddressCountry'    => 'Antarctica',
);

$I->submitForm('#content form', $editFormValues, 'Submit');
$I->seeSuccessMessage();

// Test Edit Adult (nested action) ----------------------
// First, we need to add an adult to the family
$tawasulPersonIDAdult = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full', 'tawasulRoleIDPrimary' => '004']);

// Add adult to family
$I->haveInDatabase('tawasulFamilyAdult', [
    'tawasulFamilyID' => $tawasulFamilyID,
    'tawasulPersonID' => $tawasulPersonIDAdult,
    'comment' => 'Test Adult',
    'childDataAccess' => 'Y',
    'contactPriority' => '1',
    'contactCall' => 'Y',
    'contactSMS' => 'Y',
    'contactEmail' => 'Y',
    'contactMail' => 'Y',
]);

$I->amOnModulePage('User Admin', 'family_manage_edit_editAdult.php', [
    'tawasulFamilyID' => $tawasulFamilyID,
    'tawasulPersonID' => $tawasulPersonIDAdult
]);
$I->seeBreadcrumb('Edit Adult');

$adultEditValues = [
    'comment' => 'Updated adult comment',
    'childDataAccess' => 'N',
    'contactPriority' => '2',
];

$I->submitForm('#content form', $adultEditValues, 'Submit');
$I->seeSuccessMessage();

// Test Edit Child (nested action) ----------------------
// First, we need to add a child to the family
$tawasulPersonIDChild = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full', 'tawasulRoleIDPrimary' => '003']);

// Add child to family
$I->haveInDatabase('tawasulFamilyChild', [
    'tawasulFamilyID' => $tawasulFamilyID,
    'tawasulPersonID' => $tawasulPersonIDChild,
    'comment' => 'Test Child',
]);

$I->amOnModulePage('User Admin', 'family_manage_edit_editChild.php', [
    'tawasulFamilyID' => $tawasulFamilyID,
    'tawasulPersonID' => $tawasulPersonIDChild
]);
$I->seeBreadcrumb('Edit Child');

$childEditValues = [
    'comment' => 'Updated child comment',
];

$I->submitForm('#content form', $childEditValues, 'Submit');
$I->seeSuccessMessage();

// Test Delete Adult (nested action) --------------------
$I->amOnModulePage('User Admin', 'family_manage_edit_deleteAdult.php', [
    'tawasulFamilyID' => $tawasulFamilyID,
    'tawasulPersonID' => $tawasulPersonIDAdult
]);

$I->click('Delete');
$I->seeSuccessMessage();

// Test Delete Child (nested action) --------------------
$I->amOnModulePage('User Admin', 'family_manage_edit_deleteChild.php', [
    'tawasulFamilyID' => $tawasulFamilyID,
    'tawasulPersonID' => $tawasulPersonIDChild
]);

$I->click('Delete');
$I->seeSuccessMessage();

// Delete ------------------------------------------------
$I->amOnModulePage('User Admin', 'family_manage_delete.php', array('tawasulFamilyID' => $tawasulFamilyID));

$I->click('Delete');
$I->seeSuccessMessage();
