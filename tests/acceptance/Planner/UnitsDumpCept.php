<?php
/**
 * @covers modules/TawasulPlanner/units_dump.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check Dump Unit');
$I->loginAsAdmin();

// This page requires tawasulSchoolYearID, tawasulCourseID, and tawasulUnitID parameters
// We'll create test data to access it

$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

// Create a test course
$tawasulDepartmentID = $I->grabFromDatabase('tawasulDepartment', 'tawasulDepartmentID', []);
$tawasulCourseID = $I->haveInDatabase('tawasulCourse', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulDepartmentID' => $tawasulDepartmentID,
    'name' => 'Test Course for Dump',
    'nameShort' => 'TESTDUMP',
    'description' => 'Test course description',
    'tawasulYearGroupIDList' => '',
    'orderBy' => 0,
]);

// Create a test unit
$tawasulUnitID = $I->haveInDatabase('tawasulUnit', [
    'tawasulCourseID' => $tawasulCourseID,
    'name' => 'Test Unit',
    'description' => 'Test unit for dump',
    'tags' => '',
    'details' => '',
    'attachment' => '',
    'active' => 'Y',
    'ordering' => 0,
    'tawasulPersonIDCreator' => 1,
    'tawasulPersonIDLastEdit' => 1,
]);

$I->amOnModulePage('Planner', 'units_dump.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulCourseID' => $tawasulCourseID,
    'tawasulUnitID' => $tawasulUnitID,
]);
$I->seeBreadcrumb('Dump Unit');

// Basic Check -----------------------------------------

$I->dontSeeErrors();
