<?php
/**
 * @covers modules/TawasulAdmissions/applications_manage.php
 * @covers modules/TawasulAdmissions/applications_manage_add.php
 * @covers modules/TawasulAdmissions/applications_manage_edit.php
 * @covers modules/TawasulAdmissions/applications_manage_delete.php
 * @covers modules/TawasulAdmissions/applications_manage_accept.php
 * @covers modules/TawasulAdmissions/applications_manage_addSelect.php
 * @covers modules/TawasulAdmissions/applications_manage_reject.php
 * @covers modules/TawasulAdmissions/applications_manage_view.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage applications with full CRUD operations');
$I->loginAsAdmin();

$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$I->amOnModulePage('Admissions', 'applications_manage.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID
]);

// Basic Check -----------------------------------------

$I->dontSeeErrors();

// Test Add Select (form selection page) --------------

$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Application');
$I->dontSeeErrors();

// Test View Application -------------------------------

$I->updateInDatabase('tawasulForm', ['active' => 'Y', 'public' => 'Y'], ['name' => 'Sample Application Form']);

// Create test data for viewing
$tawasulFormID = $I->grabFromDatabase('tawasulForm', 'tawasulFormID', [
    'name' => 'Sample Application Form'
]);

$tawasulAdmissionsAccountID = $I->haveInDatabase('tawasulAdmissionsAccount', [
    'email' => 'testview@example.com',
    'accessID' => 'TESTVIEW' . time(),
    'timestampCreated' => date('Y-m-d H:i:s'),
]);

$identifier = 'TESTVIEW' . time();
$tawasulAdmissionsApplicationID = $I->haveInDatabase('tawasulAdmissionsApplication', [
    'tawasulFormID' => $tawasulFormID,
    'foreignTable' => 'tawasulAdmissionsAccount',
    'foreignTableID' => $tawasulAdmissionsAccountID,
    'identifier' => $identifier,
    'status' => 'Pending',
    'timestampCreated' => date('Y-m-d H:i:s'),
    'data' => json_encode(['surname' => 'Test', 'preferredName' => 'Student']),
]);

$I->amOnModulePage('Admissions', 'applications_manage_view.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulAdmissionsApplicationID' => $tawasulAdmissionsApplicationID
]);
$I->seeBreadcrumb('View & Print Application');
$I->dontSeeErrors();

// Test Reject Application -----------------------------

$I->amOnModulePage('Admissions', 'applications_manage_edit.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulAdmissionsApplicationID' => $tawasulAdmissionsApplicationID
]);
$I->seeBreadcrumb('Edit Application');
$I->dontSeeErrors();

$I->click('Submit');
$I->seeSuccessMessage();

// Test Reject Application -----------------------------

$I->amOnModulePage('Admissions', 'applications_manage_reject.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulAdmissionsApplicationID' => $tawasulAdmissionsApplicationID
]);
$I->seeBreadcrumb('Reject Application');
$I->dontSeeErrors();

// Test Accept Application -----------------------------

$I->amOnModulePage('Admissions', 'applications_manage_accept.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulAdmissionsApplicationID' => $tawasulAdmissionsApplicationID
]);
$I->seeBreadcrumb('Accept Application');
$I->dontSeeErrors();

$I->submitForm('#content form', [], 'Accept');
$I->see('Applicant has been successfully accepted');

// Cleanup ---------------------------------------------

$I->amOnModulePage('Admissions', 'admissions_manage_delete.php', [
    'tawasulAdmissionsAccountID' => $tawasulAdmissionsAccountID
]);
$I->click('Delete');
$I->seeSuccessMessage();
