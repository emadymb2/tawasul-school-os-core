<?php
/**
 * @covers modules/TawasulStaff/staff_view.php
 * @covers modules/TawasulStaff/staff_view_details.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View staff profile');
$I->loginAsAdmin();

$I->amOnModulePage('Staff', 'staff_view.php');
$I->seeBreadcrumb('Staff Directory');

// Get a staff member
$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['username' => 'testingadmin']);

$I->amOnModulePage('Staff', 'staff_view_details.php', ['tawasulPersonID' => $tawasulPersonID]);
$I->seeBreadcrumb('Staff Directory');
