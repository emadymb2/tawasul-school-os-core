<?php
/**
 * @covers modules/TawasulStaff/coverage_my.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View my coverage');
$I->loginAsAdmin();

// Get current school year
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$I->amOnModulePage('Staff', 'coverage_my.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);
$I->seeBreadcrumb('My Coverage');
