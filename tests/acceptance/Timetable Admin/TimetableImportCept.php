<?php
/**
 * @covers modules/TawasulTimetableAdmin/tt_import.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check timetable import page');
$I->loginAsAdmin();

// Get an existing timetable to test import
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);
$tawasulTTID = $I->grabFromDatabase('tawasulTT', 'tawasulTTID', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);

$I->amOnModulePage('Timetable Admin', 'tt_import.php', [
    'tawasulTTID' => $tawasulTTID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID
]);
$I->seeBreadcrumb('Import Timetable Data');

// Basic Check - Step 1 (CSV Upload Form) ---------------

$I->dontSeeErrors();
$I->see('Step 1 - Select CSV Files');
$I->see('CSV File');
$I->see('Field Delimiter');
$I->see('String Enclosure');

// Note: We cannot test the actual import workflow without a valid CSV file
// and existing timetable structure. This test verifies the import page loads
// correctly and displays the upload form.
