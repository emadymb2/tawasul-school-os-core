<?php
/**
 * @covers modules/TawasulStaff/coverage_availability.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View coverage availability');
$I->loginAsAdmin();

// Get current school year
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$I->amOnModulePage('Staff', 'coverage_availability.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);
$I->seeBreadcrumb('Edit Availability');
