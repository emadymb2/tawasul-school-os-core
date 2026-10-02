<?php
/**
 * @covers modules/TawasulCrowdAssessment/crowdAssess_view_discuss.php
 * @covers modules/TawasulCrowdAssessment/crowdAssess_view_discuss_post.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view crowd assessment discussion and add post');
$I->loginAsAdmin();

// Get a planner entry homework record
$tawasulPlannerEntryHomeworkID = $I->grabFromDatabase('tawasulPlannerEntryHomework', 'tawasulPlannerEntryHomeworkID', []);
$tawasulPlannerEntryID = $I->grabFromDatabase('tawasulPlannerEntryHomework', 'tawasulPlannerEntryID', ['tawasulPlannerEntryHomeworkID' => $tawasulPlannerEntryHomeworkID]);
$tawasulPersonID = $I->grabFromDatabase('tawasulPlannerEntryHomework', 'tawasulPersonID', ['tawasulPlannerEntryHomeworkID' => $tawasulPlannerEntryHomeworkID]);

$I->amOnModulePage('Crowd Assessment', 'crowdAssess_view_discuss.php', [
    'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
    'tawasulPersonID' => $tawasulPersonID,
    'tawasulPlannerEntryHomeworkID' => $tawasulPlannerEntryHomeworkID
]);
$I->seeBreadcrumb('Discuss');
$I->see('Student');

// Test Add Post Action -----------------------------------

$I->amOnModulePage('Crowd Assessment', 'crowdAssess_view_discuss_post.php', [
    'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
    'tawasulPersonID' => $tawasulPersonID,
    'tawasulPlannerEntryHomeworkID' => $tawasulPlannerEntryHomeworkID
]);
$I->seeBreadcrumb('Add Post');
$I->dontSeeErrors();
