<?php
/**
 * @covers modules/TawasulStaff/absences_view_cancel.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check Cancel Absence');
$I->loginAsAdmin();

// Create test data for absence
$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full']);
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);
$tawasulStaffAbsenceTypeID = $I->grabFromDatabase('tawasulStaffAbsenceType', 'tawasulStaffAbsenceTypeID', []);

// Create a future absence
$futureDate = date('Y-m-d', strtotime('+7 days'));
$tawasulStaffAbsenceID = $I->haveInDatabase('tawasulStaffAbsence', [
    'tawasulStaffAbsenceTypeID' => $tawasulStaffAbsenceTypeID,
    'tawasulPersonID' => $tawasulPersonID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'status' => 'Approved',
    'coverageRequired' => 'N',
    'tawasulPersonIDCreator' => $tawasulPersonID,
]);

// Create absence date
$I->haveInDatabase('tawasulStaffAbsenceDate', [
    'tawasulStaffAbsenceID' => $tawasulStaffAbsenceID,
    'date' => $futureDate,
    'allDay' => 'Y',
]);

$I->amOnModulePage('Staff', 'absences_view_cancel.php', [
    'tawasulStaffAbsenceID' => $tawasulStaffAbsenceID,
]);
$I->seeBreadcrumb('Cancel Absence');

// Basic Check -----------------------------------------

$I->dontSeeErrors();
