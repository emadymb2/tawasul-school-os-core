<?php
/**
 * @covers modules/TawasulReports/reports_generate_batch.php
 * @covers modules/TawasulReports/reports_generate_batchConfirm.php
 * @covers modules/TawasulReports/reports_generate_cancelConfirm.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check reports generate batch workflow');
$I->loginAsAdmin();

// Create test data
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

// Create a report template
$tawasulReportTemplateID = $I->haveInDatabase('tawasulReportTemplate', [
    'name' => 'Test Template',
    'context' => 'Student Enrolment',
]);

// Create a report
$tawasulReportID = $I->haveInDatabase('tawasulReport', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulReportTemplateID' => $tawasulReportTemplateID,
    'name' => 'Test Report',
]);

// Reports Generate Batch ------------------------------------

$I->amOnModulePage('Reports', 'reports_generate_batch.php', [
    'tawasulReportID' => $tawasulReportID,
]);
$I->seeBreadcrumb('Run');
$I->dontSeeErrors();

// Reports Generate Batch Confirm ----------------------------

$tawasulYearGroupID = $I->grabFromDatabase('tawasulYearGroup', 'tawasulYearGroupID', []);

$I->amOnModulePage('Reports', 'reports_generate_batchConfirm.php', [
    'tawasulReportID' => $tawasulReportID,
    'contextData' => $tawasulYearGroupID,
]);
$I->dontSeeErrors();

// Reports Generate Cancel Confirm ---------------------------

$I->amOnModulePage('Reports', 'reports_generate_cancelConfirm.php', [
    'tawasulReportID' => $tawasulReportID,
    'processID' => 1,
]);
$I->dontSeeErrors();

// Clean up test data ----------------------------------------

$I->deleteFromDatabase('tawasulReport', ['tawasulReportID' => $tawasulReportID]);
$I->deleteFromDatabase('tawasulReportTemplate', ['tawasulReportTemplateID' => $tawasulReportTemplateID]);
