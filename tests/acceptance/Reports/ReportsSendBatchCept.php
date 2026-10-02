<?php
/**
 * @covers modules/TawasulReports/reports_send_batch.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check reports send batch page');
$I->loginAsAdmin();

// Create test data
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

// Create a report archive
$tawasulReportArchiveID = $I->haveInDatabase('tawasulReportArchive', [
    'name' => 'Test Archive',
    'viewableParents' => 'Y',
    'viewableStudents' => 'Y',
]);

// Create a report template
$tawasulReportTemplateID = $I->haveInDatabase('tawasulReportTemplate', [
    'name' => 'Test Template',
    'context' => 'Student Enrolment',
]);

// Create a report
$tawasulReportID = $I->haveInDatabase('tawasulReport', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulReportTemplateID' => $tawasulReportTemplateID,
    'tawasulReportArchiveID' => $tawasulReportArchiveID,
    'name' => 'Test Report',
]);

$tawasulYearGroupID = $I->grabFromDatabase('tawasulYearGroup', 'tawasulYearGroupID', []);

// Reports Send Batch ----------------------------------------

$I->amOnModulePage('Reports', 'reports_send_batch.php', [
    'tawasulReportID' => $tawasulReportID,
    'contextData' => $tawasulYearGroupID,
]);
$I->seeBreadcrumb('Select Reports');
$I->dontSeeErrors();

// Clean up test data ----------------------------------------

$I->deleteFromDatabase('tawasulReport', ['tawasulReportID' => $tawasulReportID]);
$I->deleteFromDatabase('tawasulReportTemplate', ['tawasulReportTemplateID' => $tawasulReportTemplateID]);
$I->deleteFromDatabase('tawasulReportArchive', ['tawasulReportArchiveID' => $tawasulReportArchiveID]);
