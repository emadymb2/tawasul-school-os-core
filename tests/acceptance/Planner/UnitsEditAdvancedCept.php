<?php
/**
 * @covers modules/TawasulPlanner/units_edit_copyBack.php
 * @covers modules/TawasulPlanner/units_edit_copyForward.php
 * @covers modules/TawasulPlanner/units_edit_deploy.php
 * @covers modules/TawasulPlanner/units_edit_smartBlockify.php
 * @covers modules/TawasulPlanner/units_edit_working.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check Edit Working Copy');
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

$I->amOnModulePage('Planner', 'units_edit_working.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulCourseID' => $tawasulCourseID,
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulUnitID' => $tawasulUnitID,
    'tawasulUnitClassID' => $tawasulUnitClassID
]);
$I->seeBreadcrumb('Edit Working Copy');

// Basic Check -----------------------------------------

$I->dontSeeErrors();
