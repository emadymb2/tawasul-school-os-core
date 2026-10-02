<?php
/**
 * @covers modules/TawasulTimetableAdmin/tt_edit_byClass.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('edit timetable by class');
$I->loginAsAdmin();

// Get an existing timetable
$tawasulTTID = $I->grabFromDatabase('tawasulTT', 'tawasulTTID', []);
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulTT', 'tawasulSchoolYearID', ['tawasulTTID' => $tawasulTTID]);

$I->amOnModulePage('Timetable Admin', 'tt_edit_byClass.php', [
    'tawasulTTID' => $tawasulTTID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID
]);
$I->seeBreadcrumb('Edit Timetable by Class');

// Basic Check -----------------------------------------

$I->dontSeeErrors();

// Test Class Selection --------------------------------

$I->selectFromDropdown('tawasulCourseClassID', 1);
$I->click('Next');
$I->dontSeeErrors();

// Verify the form loaded with the selected class
$I->seeBreadcrumb('Edit Timetable by Class');
$I->see('This is an administrative tool to assist with timetable changes');

