<?php
/**
 * @covers modules/TawasulReports/archive_manage_upload.php
 * @covers modules/TawasulReports/archive_manage_uploadPreview.php
 * @covers modules/TawasulReports/archive_manage_uploadProcess.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('upload a ZIP archive of reports');
$I->loginAsAdmin();

// Test Archive Upload Page (Step 1) ----------------------

$I->amOnModulePage('Reports', 'archive_manage_upload.php');
$I->seeBreadcrumb('Upload Reports');
$I->dontSeeErrors();

// Verify key form elements are present
$I->seeElement('input[name="file"]');
$I->seeElement('select[name="tawasulReportArchiveID"]');
$I->seeElement('select[name="tawasulSchoolYearID"]');
$I->seeElement('input[name="reportIdentifier"]');
$I->seeElement('input[name="reportDate"]');

// Submit Step 1 with ZIP file ----------------------------

$I->attachFile('file', 'test_archive.zip');
$I->selectFromDropdown('tawasulReportArchiveID', 1);
$I->fillField('reportIdentifier', 'TestUploadReport');
$I->fillField('reportDate', date('d/m/Y'));

$I->submitForm('#content form', [], 'Submit');

// Step 2 - Preview page ---------------------------------

$I->seeBreadcrumb('Step 2');
$I->dontSeeErrors();

// Submit Step 2 to process the import
$I->submitForm('#content form', [], 'Submit');

$I->see('Import successful', '.success');

// Cleanup: remove the imported archive entry and file
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);
$tawasulReportArchiveEntryID = $I->grabFromDatabase('tawasulReportArchiveEntry', 'tawasulReportArchiveEntryID', [
    'reportIdentifier' => 'TestUploadReport',
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
]);
$filePath = $I->grabFromDatabase('tawasulReportArchiveEntry', 'filePath', [
    'tawasulReportArchiveEntryID' => $tawasulReportArchiveEntryID,
]);
$I->deleteFromDatabase('tawasulReportArchiveEntry', ['tawasulReportArchiveEntryID' => $tawasulReportArchiveEntryID]);
$I->deleteFile('../uploads/reports/'.$filePath);
