<?php
/**
 * @covers modules/TawasulTimetableAdmin/ttDates.php
 * @covers modules/TawasulTimetableAdmin/ttDates_edit.php
 * @covers modules/TawasulTimetableAdmin/ttDates_edit_add.php
 * @covers modules/TawasulTimetableAdmin/ttDates_edit_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage timetable dates with nested day management');
$I->loginAsAdmin();

// Get the current school year ID
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$I->amOnModulePage('Timetable Admin', 'ttDates.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID
]);
$I->seeBreadcrumb('Tie Days to Dates');

// Basic Check -----------------------------------------

$I->dontSeeErrors();

// Create test timetable and day for testing
$tawasulYearGroupID = $I->grabFromDatabase('tawasulYearGroup', 'tawasulYearGroupID', []);

$tawasulTTID = $I->haveInDatabase('tawasulTT', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'name' => 'Test Timetable',
    'nameShort' => 'TT',
    'nameShortDisplay' => 'Day Of The Week',
    'tawasulYearGroupIDList' => $tawasulYearGroupID,
    'active' => 'Y',
]);

$tawasulTTColumnID = $I->haveInDatabase('tawasulTTColumn', [
    'name' => 'Test Column',
    'nameShort' => 'TC',
]);

$tawasulTTDayID = $I->haveInDatabase('tawasulTTDay', [
    'tawasulTTID' => $tawasulTTID,
    'tawasulTTColumnID' => $tawasulTTColumnID,
    'name' => 'Test Day',
    'nameShort' => 'TD',
    'color' => '#3A6CA8',
    'fontColor' => '#FFFFFF',
]);

// Get a school day date for testing
// Find a date within the current school year term
$termFirstDay = $I->grabFromDatabase('tawasulSchoolYearTerm', 'firstDay', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID
]);

// Use the first day of the term as our test date
$dateStamp = strtotime($termFirstDay);

// Test Edit Days in Date ------------------------------

$I->amOnModulePage('Timetable Admin', 'ttDates_edit.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'dateStamp' => $dateStamp
]);
$I->seeBreadcrumb('Edit Days in Date');
$I->dontSeeErrors();

// Test Add Day to Date --------------------------------

$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Day to Date');
$I->dontSeeErrors();

// Select the timetable day to add
$I->selectFromDropdown('tawasulTTDayID', 1);
$I->submitForm('#content form', []);
$I->seeSuccessMessage();

// Use the tawasulTTDayID we created earlier for the delete test

// Test Delete Day from Date ---------------------------

$I->amOnModulePage('Timetable Admin', 'ttDates_edit_delete.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'dateStamp' => $dateStamp,
    'tawasulTTDayID' => $tawasulTTDayID
]);

$I->click('Delete');
$I->seeSuccessMessage();
