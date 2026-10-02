<?php
/**
 * @covers modules/TawasulPlanner/planner_duplicate.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check Duplicate Lesson Plan');
$I->loginAsAdmin();

// Get a course class and planner entry
$tawasulCourseClassID = $I->grabFromDatabase('tawasulCourseClass', 'tawasulCourseClassID', []);
$tawasulPlannerEntryID = $I->grabFromDatabase('tawasulPlannerEntry', 'tawasulPlannerEntryID', [
    'tawasulCourseClassID' => $tawasulCourseClassID
]);

$I->amOnModulePage('Planner', 'planner_duplicate.php', [
    'viewBy' => 'class',
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulPlannerEntryID' => $tawasulPlannerEntryID
]);
$I->seeBreadcrumb('Duplicate Lesson Plan');

// Basic Check -----------------------------------------

$I->dontSeeErrors();
