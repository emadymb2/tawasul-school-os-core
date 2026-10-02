<?php
/**
 * @covers modules/TawasulReports/reports_generate_single.php
 * @covers modules/TawasulReports/reports_generate_singleDebug.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check reports generate single workflow');
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

$tawasulYearGroupID = $I->grabFromDatabase('tawasulYearGroup', 'tawasulYearGroupID', []);

// Reports Generate Single -----------------------------------

$I->amOnModulePage('Reports', 'reports_generate_single.php', [
    'tawasulReportID' => $tawasulReportID,
    'contextData' => $tawasulYearGroupID,
]);
$I->seeBreadcrumb('Single');
$I->dontSeeErrors();

// Reports Generate Single Debug -----------------------------

$tawasulStudentEnrolmentID = $I->grabFromDatabase('tawasulStudentEnrolment', 'tawasulStudentEnrolmentID', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);

$I->amOnModulePage('Reports', 'reports_generate_singleDebug.php', [
    'tawasulReportID' => $tawasulReportID,
    'contextData' => $tawasulYearGroupID,
    'tawasulStudentEnrolmentID' => $tawasulStudentEnrolmentID,
]);
$I->dontSeeErrors();

// Clean up test data ----------------------------------------

$I->deleteFromDatabase('tawasulReport', ['tawasulReportID' => $tawasulReportID]);
$I->deleteFromDatabase('tawasulReportTemplate', ['tawasulReportTemplateID' => $tawasulReportTemplateID]);
