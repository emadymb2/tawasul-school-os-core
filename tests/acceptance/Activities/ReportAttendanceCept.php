<?php
/**
 * @covers modules/TawasulActivities/report_attendance.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view attendance history by activity report');
$I->loginAsAdmin();
$I->amOnModulePage('Activities', 'report_attendance.php');
$I->seeBreadcrumb('Attendance History by Activity');

// Select an activity if available
$activityCount = $I->grabMultiple('#content select[name=tawasulActivityID] option:not([value=""])');
if (count($activityCount) > 0) {
    $I->selectFromDropdown('tawasulActivityID', 1);
    $I->submitForm('#content form', []);
    $I->seeInCurrentUrl('tawasulActivityID=');
}
