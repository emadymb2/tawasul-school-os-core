<?php
/**
 * @covers modules/TawasulUserAdmin/role_manage.php
 * @covers modules/TawasulUserAdmin/role_manage_add.php
 * @covers modules/TawasulUserAdmin/role_manage_edit.php
 * @covers modules/TawasulUserAdmin/role_manage_delete.php
 * @covers modules/TawasulUserAdmin/role_manage_duplicate.php
 * @covers modules/TawasulUserAdmin/role_manage_view.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add, edit and delete a role');
$I->loginAsAdmin();
$I->amOnModulePage('User Admin', 'role_manage.php');

// Add ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Role');

$formValues = array(
    'category'         => 'Other',
    'name'             => 'Testing Role',
    'nameShort'        => 'TSTR',
    'description'      => 'For testing.',
    'type'             => 'Additional',
    'pastYearsLogin'   => 'N',
    'futureYearsLogin' => 'N',
    'restriction'      => 'Same Role',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');

$tawasulRoleID = $I->grabEditIDFromURL();

// Edit ------------------------------------------------
$I->amOnModulePage('User Admin', 'role_manage_edit.php', array('tawasulRoleID' => $tawasulRoleID));
$I->seeBreadcrumb('Edit Role');

$I->seeInFormFields('#content form', $formValues);

$formValues = array(
    'category'         => 'Staff',
    'name'             => 'Testing Role Too',
    'nameShort'        => 'TST2',
    'description'      => 'Also for testing.',
    'type'             => 'Additional',
    'pastYearsLogin'   => 'Y',
    'futureYearsLogin' => 'Y',
    'restriction'      => 'None',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');

// Test Duplicate Action ---------------------------------
$I->amOnModulePage('User Admin', 'role_manage_duplicate.php', ['tawasulRoleID' => $tawasulRoleID]);
$I->seeBreadcrumb('Duplicate Role');

$duplicateFormValues = [
    'name' => 'Duplicated Role',
    'nameShort' => 'DTST',
];

$I->submitForm('#content form', $duplicateFormValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');

// Get the duplicated role ID to delete it later
$tawasulRoleIDDuplicate = $I->grabFromDatabase('tawasulRole', 'tawasulRoleID', ['name' => 'Duplicated Role']);

// Test View Action --------------------------------------
$I->amOnModulePage('User Admin', 'role_manage_view.php', ['tawasulRoleID' => $tawasulRoleID]);
$I->seeBreadcrumb('View Role');
$I->dontSeeErrors();

// Delete ------------------------------------------------
$I->amOnModulePage('User Admin', 'role_manage_delete.php', array('tawasulRoleID' => $tawasulRoleID));
$I->click('Delete');
$I->see('Your request was completed successfully.', '.success');

// Delete Duplicate Role ---------------------------------
$I->amOnModulePage('User Admin', 'role_manage_delete.php', ['tawasulRoleID' => $tawasulRoleIDDuplicate]);
$I->click('Delete');
$I->see('Your request was completed successfully.', '.success');
