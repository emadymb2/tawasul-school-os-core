<?php
/**
 * @covers modules/TawasulStaff/absences_manage.php
 * @covers modules/TawasulStaff/absences_manage_edit.php
 * @covers modules/TawasulStaff/absences_manage_edit_edit.php
 * @covers modules/TawasulStaff/absences_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage staff absences with edit and delete operations');
$I->loginAsAdmin();
$I->amOnModulePage('Staff', 'absences_manage.php');
$I->seeBreadcrumb('Manage Staff Absences');

// Basic Check -----------------------------------------

$I->dontSeeErrors();

// Create test absence data ----------------------------

$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full']);
$tawasulStaffAbsenceTypeID = $I->grabFromDatabase('tawasulStaffAbsenceType', 'tawasulStaffAbsenceTypeID', []);

$tawasulStaffAbsenceID = $I->haveInDatabase('tawasulStaffAbsence', [
    'tawasulPersonID' => $tawasulPersonID,
    'tawasulStaffAbsenceTypeID' => $tawasulStaffAbsenceTypeID,
    'tawasulSchoolYearID' => $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']),
    'tawasulPersonIDCreator' => $tawasulPersonID,
    'status' => 'Approved',
    'coverageRequired' => 'N',
    'comment' => 'Test absence',
]);

$tawasulStaffAbsenceDateID = $I->haveInDatabase('tawasulStaffAbsenceDate', [
    'tawasulStaffAbsenceID' => $tawasulStaffAbsenceID,
    'date' => date('Y-m-d'),
    'allDay' => 'Y',
]);

// Edit Absence ----------------------------------------

$I->amOnModulePage('Staff', 'absences_manage_edit.php', ['tawasulStaffAbsenceID' => $tawasulStaffAbsenceID]);
$I->seeBreadcrumb('Edit Absence');

$I->seeInField('comment', 'Test absence');

$I->fillField('comment', 'Updated test absence');
$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

// Edit Nested Date ------------------------------------

$I->amOnModulePage('Staff', 'absences_manage_edit_edit.php', [
    'tawasulStaffAbsenceID' => $tawasulStaffAbsenceID,
    'tawasulStaffAbsenceDateID' => $tawasulStaffAbsenceDateID
]);
$I->seeBreadcrumb('Edit');

$I->uncheckOption('allDay');
$I->fillField('timeStart', '09:00');
$I->fillField('timeEnd', '12:00');
$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

// Delete Absence --------------------------------------

$I->amOnModulePage('Staff', 'absences_manage_delete.php', ['tawasulStaffAbsenceID' => $tawasulStaffAbsenceID]);

$I->click('Delete');
$I->seeSuccessMessage();
