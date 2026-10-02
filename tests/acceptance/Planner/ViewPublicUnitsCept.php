<?php
/**
 * @covers modules/TawasulPlanner/units_public.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View public units');
$I->loginAsParent();

// Get current school year
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$I->amOnModulePage('Planner', 'units_public.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);
$I->seeBreadcrumb('Learn With Us');
