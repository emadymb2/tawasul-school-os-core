<?php
/**
 * @covers modules/TawasulStaff/coverage_planner_assign.php
 * @covers modules/TawasulStaff/coverage_planner_copy.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('test coverage planner actions');
$I->loginAsAdmin();

// Enable internal coverage ---------------------------
$I->amOnModulePage('User Admin', 'staffSettings.php');
$originalFormValues = $I->grabAllFormValues();

$newFormValues = array(
    'coverageInternal' => 'Y',
);

$I->submitForm('#content form', $newFormValues, 'Submit');

// Create test coverage data ---------------------------

$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full']);
$tawasulPersonIDCoverage = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full']);

$tawasulStaffCoverageID = $I->haveInDatabase('tawasulStaffCoverage', [
    'tawasulPersonID' => $tawasulPersonID,
    'tawasulSchoolYearID' => $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']),
    'tawasulPersonIDCoverage' => null,
    'tawasulPersonIDStatus' => $tawasulPersonID,
    'status' => 'Requested',
]);

$tawasulStaffCoverageDateID = $I->haveInDatabase('tawasulStaffCoverageDate', [
    'tawasulStaffCoverageID' => $tawasulStaffCoverageID,
    'date' => date('Y-m-d', strtotime('+1 day')),
    'allDay' => 'Y',
    'timeStart' => '09:00:00',
    'timeEnd' => '15:00:00',
]);

// Test Assign Action ----------------------------------

$I->amOnModulePage('Staff', 'coverage_planner_assign.php', [
    'tawasulStaffCoverageDateID' => $tawasulStaffCoverageDateID
]);

$I->dontSeeErrors();

// Test Copy Action ------------------------------------

$I->amOnModulePage('Staff', 'coverage_planner_copy.php', [
    'date' => date('Y-m-d', strtotime('+1 day'))
]);

$I->dontSeeErrors();

// Clean up test data ----------------------------------

$I->amOnModulePage('Staff', 'coverage_manage_delete.php', ['tawasulStaffCoverageID' => $tawasulStaffCoverageID]);
$I->click('Delete');
$I->seeSuccessMessage();

// Restore original settings ---------------------------
$I->amOnModulePage('User Admin', 'staffSettings.php');
$I->submitForm('#content form', $originalFormValues, 'Submit');
$I->seeSuccessMessage();
