<?php
/**
 * @covers modules/TawasulReports/reporting_criteria_manage.php
 * @covers modules/TawasulReports/reporting_criteria_manage_add.php
 * @covers modules/TawasulReports/reporting_criteria_manage_addMultiple.php
 * @covers modules/TawasulReports/reporting_criteria_manage_edit.php
 * @covers modules/TawasulReports/reporting_criteria_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('Manage criteria with full CRUD operations');
$I->loginAsAdmin();
// Create test data
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$tawasulReportingCycleID = $I->haveInDatabase('tawasulReportingCycle', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'name' => 'Test Criteria Cycle',
    'nameShort' => 'TCC',
    'sequenceNumber' => 1,
    'dateStart' => date('Y-m-d'),
    'dateEnd' => date('Y-m-d', strtotime('+30 days')),
]);

$tawasulYearGroupID = $I->grabFromDatabase('tawasulYearGroup', 'tawasulYearGroupID', []);

$tawasulReportingScopeID = $I->haveInDatabase('tawasulReportingScope', [
    'tawasulReportingCycleID' => $tawasulReportingCycleID,
    'scopeType' => 'Year Group',
    'name' => 'Test Criteria Scope',
]);

$I->amOnModulePage('Reports', 'reporting_criteria_manage.php', [
    'tawasulReportingCycleID' => $tawasulReportingCycleID,
    'tawasulReportingScopeID' => $tawasulReportingScopeID,
]);
$I->seeBreadcrumb('Manage Criteria');

// Create test criteria directly in database
$tawasulReportingCriteriaTypeID = $I->grabFromDatabase('tawasulReportingCriteriaType', 'tawasulReportingCriteriaTypeID', []);

$tawasulReportingCriteriaID = $I->haveInDatabase('tawasulReportingCriteria', [
    'tawasulReportingCycleID' => $tawasulReportingCycleID,
    'tawasulReportingScopeID' => $tawasulReportingScopeID,
    'tawasulReportingCriteriaTypeID' => $tawasulReportingCriteriaTypeID,
    'target' => 'Per Student',
    'name' => 'Test Criteria',
    'sequenceNumber' => 1,
]);

// Test Add page ------------------------------------------

$I->clickNavigation('Add');
$I->seeBreadcrumb('Add');
$I->dontSeeErrors();
// Test Edit page -----------------------------------------

$I->amOnModulePage('Reports', 'reporting_criteria_manage_edit.php', [
    'tawasulReportingCycleID' => $tawasulReportingCycleID,
    'tawasulReportingScopeID' => $tawasulReportingScopeID,
    'tawasulReportingCriteriaID' => $tawasulReportingCriteriaID,
]);
$I->seeBreadcrumb('Edit');
$I->seeInField('name', 'Test Criteria');
$I->fillField('name', 'Updated Criteria');
$I->click('Submit');
$I->seeSuccessMessage();

// Test Delete page ---------------------------------------

$I->amOnModulePage('Reports', 'reporting_criteria_manage_delete.php', [
    'tawasulReportingCycleID' => $tawasulReportingCycleID,
    'tawasulReportingScopeID' => $tawasulReportingScopeID,
    'tawasulReportingCriteriaID' => $tawasulReportingCriteriaID,
]);
$I->dontSeeErrors();

// Clean up test data directly since delete page has a bug
$I->deleteFromDatabase('tawasulReportingCriteria', ['tawasulReportingCriteriaID' => $tawasulReportingCriteriaID]);

// Test Add Multiple Criteria -------------------------------

$I->amOnModulePage('Reports', 'reporting_criteria_manage_addMultiple.php', [
    'tawasulReportingCycleID' => $tawasulReportingCycleID,
    'tawasulReportingScopeID' => $tawasulReportingScopeID,
]);
$I->seeBreadcrumb('Add Multiple Criteria');
$I->dontSeeErrors();

// Clean up test data ----------------------------------------

$I->deleteFromDatabase('tawasulReportingScope', ['tawasulReportingScopeID' => $tawasulReportingScopeID]);
$I->deleteFromDatabase('tawasulReportingCycle', ['tawasulReportingCycleID' => $tawasulReportingCycleID]);
