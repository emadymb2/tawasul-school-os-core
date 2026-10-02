<?php
/**
 * @covers modules/TawasulActivities/activities_attendance.php
 * @covers modules/TawasulActivities/activities_attendance_sheet.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('enter activity attendance');
$I->loginAsAdmin();
$I->amOnModulePage('Activities', 'activities_attendance.php');
$I->seeBreadcrumb('Enter Activity Attendance');

// Select an activity if available
$activityCount = $I->grabMultiple('#content select[name=tawasulActivityID] option:not([value=""])');
if (count($activityCount) > 0) {
    $I->selectFromDropdown('tawasulActivityID', 1);
    $I->submitForm('#content form', []);
    $I->seeInCurrentUrl('tawasulActivityID=');
}

// Test Printable Attendance Sheet ----------------------

$I->amOnModulePage('Activities', 'activities_attendance_sheet.php');
$I->seeBreadcrumb('Printable Attendance Sheet');

// Basic Check -----------------------------------------

$I->dontSeeErrors();

// Filter Test -----------------------------------------

$I->selectFromDropdown('tawasulActivityID', 1);
$I->selectFromDropdown('numberOfColumns', 5);
$I->submitForm('#action', []);
$I->dontSeeErrors();
