<?php
/**
 * @covers modules/TawasulReports/notification_send.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check notification send page');
$I->loginAsAdmin();

// Create test data
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$tawasulReportingCycleID = $I->haveInDatabase('tawasulReportingCycle', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'name' => 'Test Reporting Cycle',
    'nameShort' => 'Test',
    'sequenceNumber' => 1,
    'dateStart' => date('Y-m-d'),
    'dateEnd' => date('Y-m-d', strtotime('+30 days')),
]);

// Notification Send -----------------------------------------

$I->amOnModulePage('Reports', 'notification_send.php', [
    'tawasulReportingCycleID' => $tawasulReportingCycleID,
]);
$I->seeBreadcrumb('Send Notifications');
$I->dontSeeErrors();

// Clean up test data ----------------------------------------

$I->deleteFromDatabase('tawasulReportingCycle', ['tawasulReportingCycleID' => $tawasulReportingCycleID]);
