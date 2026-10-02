<?php
/**
 * @covers modules/TawasulReports/archive_byReport_view.php
 * @covers modules/TawasulReports/archive_byStudent_view.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view archive details by report and by student');
$I->loginAsAdmin();

// Get current school year
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', [
    'status' => 'Current'
]);

// Get an existing student
$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', [
    'status' => 'Full'
]);

// Get the default archive
$tawasulReportArchiveID = $I->grabFromDatabase('tawasulReportArchive', 'tawasulReportArchiveID', []);

// Create a test report
$tawasulReportID = $I->haveInDatabase('tawasulReport', [
    'tawasulReportArchiveID' => $tawasulReportArchiveID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'name' => 'Test Report Archive View',
    'active' => 'Y',
    'status' => 'Published',
]);

// Create a test archive entry
$tawasulReportArchiveEntryID = $I->haveInDatabase('tawasulReportArchiveEntry', [
    'tawasulReportArchiveID' => $tawasulReportArchiveID,
    'tawasulReportID' => $tawasulReportID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulPersonID' => $tawasulPersonID,
    'type' => 'Single',
    'status' => 'Final',
    'reportIdentifier' => 'test-report-archive',
    'filePath' => '/test/path.pdf',
    'timestampCreated' => date('Y-m-d H:i:s'),
]);

// Test Archive by Report ---------------------------------

$I->amOnModulePage('Reports', 'archive_byReport.php');
$I->seeBreadcrumb('View by Report');

// Basic Check
$I->dontSeeErrors();

// Test Archive by Report View -----------------------------

$I->amOnModulePage('Reports', 'archive_byReport_view.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulReportID' => $tawasulReportID
]);
$I->seeBreadcrumb('View by Report');
$I->dontSeeErrors();

// Test filter functionality
$I->selectFromDropdown('tawasulReportID', 1);
$I->submitForm('#content form', []);
$I->dontSeeErrors();

// Test Archive by Student ---------------------------------

$I->amOnModulePage('Reports', 'archive_byStudent.php');
$I->seeBreadcrumb('View by Student');

// Basic Check
$I->dontSeeErrors();

// Test Archive by Student View ---------------------------

$I->amOnModulePage('Reports', 'archive_byStudent_view.php', [
    'tawasulPersonID' => $tawasulPersonID,
    'allStudents' => 'on',
]);
$I->seeBreadcrumb('View by Student');
$I->dontSeeErrors();

// Clean up test data --------------------------------------

$I->deleteFromDatabase('tawasulReportArchiveEntry', ['tawasulReportArchiveEntryID' => $tawasulReportArchiveEntryID]);
$I->deleteFromDatabase('tawasulReport', ['tawasulReportID' => $tawasulReportID]);
