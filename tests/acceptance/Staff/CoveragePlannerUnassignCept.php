<?php
/**
 * @covers modules/TawasulStaff/coverage_planner_unassign.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check unassign coverage');
$I->loginAsAdmin();

// Create test data for coverage
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
    'coverageRequired' => 'Y',
    'tawasulPersonIDCreator' => $tawasulPersonID,
]);

// Create absence date
$I->haveInDatabase('tawasulStaffAbsenceDate', [
    'tawasulStaffAbsenceID' => $tawasulStaffAbsenceID,
    'date' => $futureDate,
    'allDay' => 'Y',
]);

// Create a coverage assignment
$tawasulStaffCoverageID = $I->haveInDatabase('tawasulStaffCoverage', [
    'tawasulStaffAbsenceID' => $tawasulStaffAbsenceID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulPersonID' => $tawasulPersonID,
    'status' => 'Accepted',
    'requestType' => 'Assigned',
    'tawasulPersonIDStatus' => $tawasulPersonID,
    'timestampStatus' => date('Y-m-d H:i:s'),
]);

$I->amOnModulePage('Staff', 'coverage_planner_unassign.php', [
    'tawasulStaffCoverageID' => $tawasulStaffCoverageID,
]);

// Basic Check -----------------------------------------

$I->dontSeeErrors();
