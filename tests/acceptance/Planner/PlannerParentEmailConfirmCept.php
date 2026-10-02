<?php
/**
 * @covers modules/TawasulPlanner/planner_parentWeeklyEmailSummaryConfirm.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check Parent Weekly Email Summary Confirmation');
$I->loginAsAdmin();

// Get current school year
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

// Get a student
$tawasulPersonIDStudent = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full']);

// Create a test email summary record
$key = uniqid();
$I->haveInDatabase('tawasulPlannerParentWeeklyEmailSummary', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulPersonIDParent' => $tawasulPersonIDStudent,
    'tawasulPersonIDStudent' => $tawasulPersonIDStudent,
    'weekOfYear' => date('W'),
    'key' => $key,
    'confirmed' => 'N'
]);

$I->amOnModulePage('Planner', 'planner_parentWeeklyEmailSummaryConfirm.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulPersonIDStudent' => $tawasulPersonIDStudent,
    'tawasulPersonIDParent' => $tawasulPersonIDStudent,
    'key' => $key
]);

// Basic Check -----------------------------------------

$I->see('Thank you for confirming receipt and reading of this email.');
