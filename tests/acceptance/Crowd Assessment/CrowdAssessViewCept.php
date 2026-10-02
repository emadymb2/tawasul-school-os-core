<?php
/**
 * @covers modules/TawasulCrowdAssessment/crowdAssess_view.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view a specific crowd assessment');
$I->loginAsAdmin();

// Get a planner entry with homework crowd assess enabled
$tawasulPlannerEntryID = $I->grabFromDatabase('tawasulPlannerEntry', 'tawasulPlannerEntryID', ['homeworkCrowdAssess' => 'Y']);

$I->amOnModulePage('Crowd Assessment', 'crowdAssess_view.php', ['tawasulPlannerEntryID' => $tawasulPlannerEntryID]);
$I->seeBreadcrumb('View Assessment');
$I->see('Class');
$I->see('Name');
