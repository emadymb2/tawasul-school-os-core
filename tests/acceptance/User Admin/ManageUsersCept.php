<?php
/**
 * @covers modules/TawasulUserAdmin/user_manage.php
 * @covers modules/TawasulUserAdmin/user_manage_password.php
 * @covers modules/TawasulUserAdmin/user_manage_view_previousPhotos.php
 * @covers modules/TawasulUserAdmin/user_manage_view_status_log.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage users');
$I->loginAsAdmin();
$I->amOnModulePage('User Admin', 'user_manage.php');
$I->seeBreadcrumb('Manage Users');

// Get an existing user for testing
$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full']);

// Test Password Reset -----------------------------------
$I->amOnModulePage('User Admin', 'user_manage_password.php', [
    'tawasulPersonID' => $tawasulPersonID
]);
$I->seeBreadcrumb('Reset User Password');
$I->dontSeeErrors();

// Test View Previous Photos -----------------------------
$I->amOnModulePage('User Admin', 'user_manage_view_previousPhotos.php', [
    'tawasulPersonID' => $tawasulPersonID
]);
$I->dontSeeErrors();

// Test View Status Log ----------------------------------
$I->amOnModulePage('User Admin', 'user_manage_view_status_log.php', [
    'tawasulPersonID' => $tawasulPersonID
]);
$I->dontSeeErrors();
