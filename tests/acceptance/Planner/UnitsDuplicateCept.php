<?php
/**
 * @covers modules/TawasulPlanner/units_duplicate.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check Duplicate Unit');
$I->loginAsAdmin();

// Get current school year
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

// Get a course and unit
$tawasulCourseID = $I->grabFromDatabase('tawasulCourse', 'tawasulCourseID', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID
]);
$tawasulUnitID = $I->grabFromDatabase('tawasulUnit', 'tawasulUnitID', [
    'tawasulCourseID' => $tawasulCourseID
]);

$I->amOnModulePage('Planner', 'units_duplicate.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulCourseID' => $tawasulCourseID,
    'tawasulUnitID' => $tawasulUnitID
]);
$I->seeBreadcrumb('Duplicate Unit');

// Basic Check -----------------------------------------

$I->dontSeeErrors();
