<?php
/**
 * @covers modules/TawasulReports/progress_byDepartment.php
 * @covers modules/TawasulReports/progress_byPerson.php
 * @covers modules/TawasulReports/progress_byProofReading.php
 * @covers modules/TawasulReports/progress_byReportingCycle.php
 * @covers modules/TawasulReports/progress_studentNameConflicts.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check progress report pages');
$I->loginAsAdmin();

// Create test reporting cycle
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);
$tawasulReportingCycleID = $I->haveInDatabase('tawasulReportingCycle', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'name' => 'Test Reporting Cycle',
    'nameShort' => 'Test',
    'sequenceNumber' => 1,
    'dateStart' => date('Y-m-d'),
    'dateEnd' => date('Y-m-d', strtotime('+30 days')),
]);

// Progress by Department -----------------------------------

$I->amOnModulePage('Reports', 'progress_byDepartment.php');
$I->seeBreadcrumb('Progress by Department');
$I->dontSeeErrors();

// Filter Test
$I->selectFromDropdown('tawasulReportingCycleID', 1);
$I->submitForm('#archiveByReport', []);
$I->dontSeeErrors();

// Progress by Person ---------------------------------------

$I->amOnModulePage('Reports', 'progress_byPerson.php');
$I->seeBreadcrumb('Progress by Person');
$I->dontSeeErrors();

// Filter Test
$I->selectFromDropdown('tawasulReportingCycleID', 1);
$I->submitForm('#archiveByReport', []);
$I->dontSeeErrors();

// Progress by Proof Reading --------------------------------

$I->amOnModulePage('Reports', 'progress_byProofReading.php');
$I->seeBreadcrumb('Proof Reading Progress');
$I->dontSeeErrors();

// Filter Test
$I->selectFromDropdown('tawasulReportingCycleID', 1);
$I->submitForm('#archiveByReport', []);
$I->dontSeeErrors();

// Progress by Reporting Cycle ------------------------------

$I->amOnModulePage('Reports', 'progress_byReportingCycle.php');
$I->seeBreadcrumb('Progress by Reporting Cycle');
$I->dontSeeErrors();

// Filter Test
$I->selectFromDropdown('tawasulReportingCycleID', 1);
$I->submitForm('#archiveByReport', []);
$I->dontSeeErrors();

// Student Name Conflicts -----------------------------------

$I->amOnModulePage('Reports', 'progress_studentNameConflicts.php');
$I->seeBreadcrumb('Student Name Conflicts');
$I->dontSeeErrors();

// Filter Test
$I->selectFromDropdown('tawasulReportingCycleID', 1);
$I->submitForm('#archiveByReport', []);
$I->dontSeeErrors();

// Clean up test data ---------------------------------------

$I->deleteFromDatabase('tawasulReportingCycle', ['tawasulReportingCycleID' => $tawasulReportingCycleID]);
