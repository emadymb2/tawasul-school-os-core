<?php
/**
 * @covers modules/TawasulPlanner/units_public_view.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check View Unit (Public)');
$I->loginAsAdmin();

// Get current school year
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

// Get a public unit
$tawasulUnitID = $I->grabFromDatabase('tawasulUnit', 'tawasulUnitID', ['sharedPublic' => 'Y']);

$I->amOnModulePage('Planner', 'units_public_view.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulUnitID' => $tawasulUnitID,
    'sidebar' => 'false'
]);
$I->seeBreadcrumb('View Unit');

// Basic Check -----------------------------------------

$I->dontSeeErrors();
