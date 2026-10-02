<?php
/**
 * @covers modules/TawasulIndividualNeeds/iep_view_myChildren.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View IEP for my children');
$I->loginAsParent();

// Get current school year
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$I->amOnModulePage('Individual Needs', 'iep_view_myChildren.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);
$I->dontSeeErrors();
