<?php
/**
 * @covers modules/TawasulPlanner/scopeAndSequence.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View scope and sequence');
$I->loginAsAdmin();

// Get current school year
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$I->amOnModulePage('Planner', 'scopeAndSequence.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);
$I->seeBreadcrumb('Scope And Sequence');
