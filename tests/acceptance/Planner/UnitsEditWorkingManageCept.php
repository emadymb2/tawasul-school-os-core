<?php
/**
 * @covers modules/TawasulPlanner/units_edit_working_add.php
 * @covers modules/TawasulPlanner/units_edit_working_copyback.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check Add Lessons to Working Copy');
$I->loginAsAdmin();

// Get current school year
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

// Get a course, class, and unit
$tawasulCourseID = $I->grabFromDatabase('tawasulCourse', 'tawasulCourseID', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID
]);
$tawasulCourseClassID = $I->grabFromDatabase('tawasulCourseClass', 'tawasulCourseClassID', [
    'tawasulCourseID' => $tawasulCourseID
]);
$tawasulUnitID = $I->grabFromDatabase('tawasulUnit', 'tawasulUnitID', [
    'tawasulCourseID' => $tawasulCourseID
]);
$tawasulUnitClassID = $I->grabFromDatabase('tawasulUnitClass', 'tawasulUnitClassID', [
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulUnitID' => $tawasulUnitID
]);

$I->amOnModulePage('Planner', 'units_edit_working_add.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulCourseID' => $tawasulCourseID,
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulUnitID' => $tawasulUnitID,
    'tawasulUnitClassID' => $tawasulUnitClassID
]);
$I->seeBreadcrumb('Add Lessons');

// Basic Check -----------------------------------------

$I->dontSeeErrors();
