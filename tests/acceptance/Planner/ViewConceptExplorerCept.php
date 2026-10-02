<?php
/**
 * @covers modules/TawasulPlanner/conceptExplorer.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View concept explorer');
$I->loginAsAdmin();

// Get current school year
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$I->amOnModulePage('Planner', 'conceptExplorer.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);
$I->seeBreadcrumb('Concept Explorer');
