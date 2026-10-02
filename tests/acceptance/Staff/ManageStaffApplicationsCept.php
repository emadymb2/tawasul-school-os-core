<?php
/**
 * @covers modules/TawasulStaff/applicationForm_manage.php
 * @covers modules/TawasulStaff/applicationForm_manage_accept.php
 * @covers modules/TawasulStaff/applicationForm_manage_edit.php
 * @covers modules/TawasulStaff/applicationForm_manage_delete.php
 * @covers modules/TawasulStaff/applicationForm_manage_reject.php
 * @covers modules/TawasulStaff/applicationForm_manageProcessBulk.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage staff applications with accept, edit, reject, and delete operations');
$I->loginAsAdmin();
$I->amOnModulePage('Staff', 'applicationForm_manage.php');
$I->seeBreadcrumb('Manage Applications');

// Basic Check -----------------------------------------

$I->dontSeeErrors();

// Search Test -----------------------------------------

$I->fillField('search', 'test');
$I->submitForm('#action', []);
$I->dontSeeErrors();

$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['username' => 'testingadmin']);

// Create test application data ------------------------

$tawasulStaffJobOpeningID = $I->haveInDatabase('tawasulStaffJobOpening', [
    'type' => 'Teaching',
    'jobTitle' => 'Test Job Opening',
    'dateOpen' => date('Y-m-d'),
    'active' => 'Y',
    'description' => '',
    'tawasulPersonIDCreator' => $tawasulPersonID,
]);

$tawasulStaffApplicationFormID = $I->haveInDatabase('tawasulStaffApplicationForm', [
    'tawasulStaffJobOpeningID' => $tawasulStaffJobOpeningID,
    'surname' => 'TestApplicant',
    'firstName' => 'Test',
    'preferredName' => 'Test',
    'officialName' => 'Test Applicant',
    'gender' => 'M',
    'dob' => '1990-01-01',
    'email' => 'test.applicant@example.com',
    'homeAddress' => '123 Test Street',
    'homeAddressDistrict' => 'Test District',
    'homeAddressCountry' => 'Antarctica',
    'phone1' => '1234567890',
    'languageFirst' => 'English',
    'status' => 'Pending',
    'priority' => '0',
    'timestamp' => date('Y-m-d H:i:s'),
    'milestones' => '',
    'notes' => '',
    'questions' => '',
    'fields' => '',
    'referenceEmail1' => 'ref1@example.com',
    'referenceEmail2' => 'ref2@example.com',
    'tawasulPersonID' => null
]);


// Edit Application ------------------------------------

$I->amOnModulePage('Staff', 'applicationForm_manage_edit.php', ['tawasulStaffApplicationFormID' => $tawasulStaffApplicationFormID]);
$I->seeBreadcrumb('Edit Form');

$I->seeInField('surname', 'TestApplicant');
$I->fillField('notes', 'Test notes for application');
$I->selectOption('priority', '0');


$I->attachFile('file0', 'attachment.txt');
$I->submitForm('#content form', ['tawasulPersonID' => null, 'tawasulStaffJobOpeningID' => $tawasulStaffJobOpeningID], 'Submit');
$I->seeSuccessMessage();

// Verify file was uploaded to DB
$filePath = $I->grabFromDatabase('tawasulStaffApplicationFormFile', 'path', [
    'tawasulStaffApplicationFormID' => $tawasulStaffApplicationFormID,
    'name'                         => 'Curriculum Vitae',
]);
$I->assertNotEmpty($filePath);

// // Accept Application ----------------------------------

$I->amOnModulePage('Staff', 'applicationForm_manage_accept.php', ['tawasulStaffApplicationFormID' => $tawasulStaffApplicationFormID]);
$I->seeBreadcrumb('Accept Application');

// Check page loads (may show error if form has issues, but shouldn't crash)
$I->dontSeeErrors();

// // Create another test application for rejection -------

$tawasulStaffApplicationFormID2 = $I->haveInDatabase('tawasulStaffApplicationForm', [
    'tawasulStaffJobOpeningID' => $tawasulStaffJobOpeningID,
    'surname' => 'TestReject',
    'firstName' => 'Reject',
    'preferredName' => 'Reject',
    'officialName' => 'Reject Test',
    'gender' => 'F',
    'dob' => '1991-01-01',
    'email' => 'reject.test@example.com',
    'homeAddress' => '456 Reject Street',
    'homeAddressDistrict' => 'Reject District',
    'homeAddressCountry' => 'Antarctica',
    'phone1' => '0987654321',
    'languageFirst' => 'English',
    'status' => 'Pending',
    'priority' => '0',
    'timestamp' => date('Y-m-d H:i:s'),
    'milestones' => '',
    'notes' => '',
    'questions' => '',
    'fields' => '',
    'referenceEmail1' => 'ref1@example.com',
    'referenceEmail2' => 'ref2@example.com',
    'tawasulPersonID' => null
]);

// // Reject Application ----------------------------------

$I->amOnModulePage('Staff', 'applicationForm_manage_reject.php', ['tawasulStaffApplicationFormID' => $tawasulStaffApplicationFormID2]);
$I->seeBreadcrumb('Reject Application');

$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

// // Delete Application ----------------------------------

$I->amOnModulePage('Staff', 'applicationForm_manage_delete.php', ['tawasulStaffApplicationFormID' => $tawasulStaffApplicationFormID2]);

$I->click('Delete');
$I->seeSuccessMessage();

// Cleanup uploaded file and DB records ----------------

$I->deleteFromDatabase('tawasulStaffApplicationFormFile', ['tawasulStaffApplicationFormID' => $tawasulStaffApplicationFormID]);
$I->deleteFile('../'.$filePath);
