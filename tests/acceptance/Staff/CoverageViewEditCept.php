<?php
/**
 * @covers modules/TawasulStaff/coverage_view_edit.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('edit coverage from my coverage view');
$I->loginAsAdmin();

// Enable internal coverage ---------------------------
$I->amOnModulePage('User Admin', 'staffSettings.php');
$originalFormValues = $I->grabAllFormValues();

$newFormValues = array(
    'coverageInternal' => 'Y',
);

$I->submitForm('#content form', $newFormValues, 'Submit');

// Create test coverage data ---------------------------

$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['username' => 'testingadmin']);

$tawasulStaffCoverageID = $I->haveInDatabase('tawasulStaffCoverage', [
    'tawasulPersonID' => $tawasulPersonID,
    'tawasulSchoolYearID' => $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']),
    'tawasulPersonIDCoverage' => $tawasulPersonID,
    'tawasulPersonIDStatus' => $tawasulPersonID,
    'status' => 'Accepted',
]);

$tawasulStaffCoverageDateID = $I->haveInDatabase('tawasulStaffCoverageDate', [
    'tawasulStaffCoverageID' => $tawasulStaffCoverageID,
    'date' => date('Y-m-d', strtotime('+1 day')),
    'allDay' => 'Y',
    'timeStart' => '09:00:00',
    'timeEnd' => '15:00:00',
]);

// Edit Coverage ---------------------------------------

$I->amOnModulePage('Staff', 'coverage_view_edit.php', ['tawasulStaffCoverageID' => $tawasulStaffCoverageID]);
$I->seeBreadcrumb('Edit Coverage');

$I->selectOption('attachmentType', 'File');
$I->attachFile('file', 'attachment.txt');
$I->fillField('notesStatus', 'Updated coverage notes from view');
$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

$file = $I->grabFromDatabase('tawasulStaffCoverage', 'attachmentContent', ['tawasulStaffCoverageID' => $tawasulStaffCoverageID]);
$I->assertNotEmpty($file);

// Clean up test data ----------------------------------

$I->amOnModulePage('Staff', 'coverage_manage_delete.php', ['tawasulStaffCoverageID' => $tawasulStaffCoverageID]);
$I->click('Delete');
$I->seeSuccessMessage();

// Restore original settings ---------------------------
$I->amOnModulePage('User Admin', 'staffSettings.php');
$I->submitForm('#content form', $originalFormValues, 'Submit');
$I->seeSuccessMessage();

// Cleanup ------------------------------------------------
$I->deleteFile('../'.$file);
