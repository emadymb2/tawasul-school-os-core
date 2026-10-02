<?php
/**
 * @covers modules/TawasulReports/reporting_cycles_manage.php
 * @covers modules/TawasulReports/reporting_cycles_manage_add.php
 * @covers modules/TawasulReports/reporting_cycles_manage_duplicate.php
 * @covers modules/TawasulReports/reporting_cycles_manage_edit.php
 * @covers modules/TawasulReports/reporting_cycles_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('Manage reporting cycles with full CRUD operations');
$I->loginAsAdmin();
$I->amOnModulePage('Reports', 'reporting_cycles_manage.php');
$I->seeBreadcrumb('Manage Reporting Cycles');

// Add a new reporting cycle
$I->click('Add');
$I->seeBreadcrumb('Add');
$I->fillField('name', 'Test Reporting Cycle');
$I->fillField('nameShort', 'Test');
$I->fillField('dateStart', date('Y-m-d'));
$I->fillField('dateEnd', date('Y-m-d'));

$I->click('Submit');
$I->seeSuccessMessage();

// Edit the reporting cycle
$tawasulReportingCycleID = $I->grabEditIDFromURL();
$I->amOnModulePage('Reports', 'reporting_cycles_manage_edit.php', ['tawasulReportingCycleID' => $tawasulReportingCycleID]);
$I->seeBreadcrumb('Edit');
$I->seeInField('name', 'Test Reporting Cycle');
$I->fillField('name', 'Updated Reporting Cycle');
$I->click('Submit');
$I->seeSuccessMessage();

// Delete the reporting cycle
$I->amOnModulePage('Reports', 'reporting_cycles_manage_delete.php', ['tawasulReportingCycleID' => $tawasulReportingCycleID]);
$I->click('Delete');
$I->seeSuccessMessage();

// Test Duplicate Reporting Cycle ---------------------------

// Create a new cycle to duplicate
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);
$tawasulReportingCycleID = $I->haveInDatabase('tawasulReportingCycle', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'name' => 'Cycle to Duplicate',
    'nameShort' => 'Dup',
    'sequenceNumber' => 1,
    'dateStart' => date('Y-m-d'),
    'dateEnd' => date('Y-m-d', strtotime('+30 days')),
]);

$I->amOnModulePage('Reports', 'reporting_cycles_manage_duplicate.php', [
    'tawasulReportingCycleID' => $tawasulReportingCycleID,
]);
$I->seeBreadcrumb('Duplicate Reporting Cycle');
$I->dontSeeErrors();

// Clean up
$I->deleteFromDatabase('tawasulReportingCycle', ['tawasulReportingCycleID' => $tawasulReportingCycleID]);
