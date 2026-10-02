<?php
/**
 * @covers modules/TawasulStaff/absences_approval.php
 * @covers modules/TawasulStaff/absences_approval_action.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('approve staff absences');
$I->loginAsAdmin();
$I->amOnModulePage('Staff', 'absences_approval.php');
$I->seeBreadcrumb('Approve Staff Absences');

// Basic Check -----------------------------------------

$I->dontSeeErrors();

// Create test absence requiring approval --------------

$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full']);

// Create an absence type that requires approval
$tawasulStaffAbsenceTypeID = $I->haveInDatabase('tawasulStaffAbsenceType', [
    'name' => 'Test Approval Type',
    'nameShort' => 'TAT',
    'requiresApproval' => 'Y',
    'active' => 'Y',
    'reasons' => '',
    'sequenceNumber' => 99,
]);

$tawasulStaffAbsenceID = $I->haveInDatabase('tawasulStaffAbsence', [
    'tawasulPersonID' => $tawasulPersonID,
    'tawasulStaffAbsenceTypeID' => $tawasulStaffAbsenceTypeID,
    'tawasulSchoolYearID' => $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']),
    'tawasulPersonIDApproval' => $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['username' => 'testingadmin']),
    'tawasulPersonIDCreator' => $tawasulPersonID,
    'status' => 'Pending Approval',
    'coverageRequired' => 'N',
    'comment' => 'Test absence for approval',
]);

$tawasulStaffAbsenceDateID = $I->haveInDatabase('tawasulStaffAbsenceDate', [
    'tawasulStaffAbsenceID' => $tawasulStaffAbsenceID,
    'date' => date('Y-m-d', strtotime('+1 day')),
    'allDay' => 'Y',
]);

// Test Approval Action --------------------------------

$I->amOnModulePage('Staff', 'absences_approval_action.php', [
    'tawasulStaffAbsenceID' => $tawasulStaffAbsenceID,
    'status' => 'Approved'
]);

// Check page loads (may show access denied if approval person doesn't match, but shouldn't crash)
$I->dontSeeErrors();

$I->selectOption('status', 'Approved');
$I->click('Submit');
$I->seeSuccessMessage();

// Clean up test data ----------------------------------

$I->amOnModulePage('Staff', 'absences_manage_delete.php', ['tawasulStaffAbsenceID' => $tawasulStaffAbsenceID]);
$I->click('Delete');
$I->seeSuccessMessage();
