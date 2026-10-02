<?php
/**
 * @covers modules/TawasulPlanner/planner_edit.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check Edit Lesson Plan');
$I->loginAsAdmin();

// Get a course class and planner entry
$tawasulCourseClassID = $I->grabFromDatabase('tawasulCourseClass', 'tawasulCourseClassID', []);
$tawasulPlannerEntryID = $I->grabFromDatabase('tawasulPlannerEntry', 'tawasulPlannerEntryID', [
    'tawasulCourseClassID' => $tawasulCourseClassID
]);

// If no planner entry exists for this class, skip the test
if (!$tawasulPlannerEntryID) {
    $I->comment('Skipping: No planner entry found for this course class');
    return;
}

$I->amOnModulePage('Planner', 'planner_edit.php', [
    'viewBy' => 'class',
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulPlannerEntryID' => $tawasulPlannerEntryID
]);
$I->seeBreadcrumb('Edit');

// Basic Check -----------------------------------------

$I->dontSeeErrors();
