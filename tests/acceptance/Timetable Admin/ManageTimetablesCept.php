<?php
/**
 * @covers modules/TawasulTimetableAdmin/tt.php
 * @covers modules/TawasulTimetableAdmin/tt_add.php
 * @covers modules/TawasulTimetableAdmin/tt_edit.php
 * @covers modules/TawasulTimetableAdmin/tt_edit_day_add.php
 * @covers modules/TawasulTimetableAdmin/tt_edit_day_edit.php
 * @covers modules/TawasulTimetableAdmin/tt_edit_day_edit_class.php
 * @covers modules/TawasulTimetableAdmin/tt_edit_day_edit_class_add.php
 * @covers modules/TawasulTimetableAdmin/tt_edit_day_edit_class_delete.php
 * @covers modules/TawasulTimetableAdmin/tt_edit_day_edit_class_edit.php
 * @covers modules/TawasulTimetableAdmin/tt_edit_day_edit_class_exception.php
 * @covers modules/TawasulTimetableAdmin/tt_edit_day_edit_class_exception_add.php
 * @covers modules/TawasulTimetableAdmin/tt_edit_day_edit_class_exception_delete.php
 * @covers modules/TawasulTimetableAdmin/tt_edit_day_delete.php
 * @covers modules/TawasulTimetableAdmin/tt_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage timetables with nested days and periods');
$I->loginAsAdmin();
$I->amOnModulePage('Timetable Admin', 'tt.php');
$I->seeBreadcrumb('Manage Timetables');

// Add Timetable -----------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Timetable');

$tawasulSchoolYearID = $I->grabValueFromURL('tawasulSchoolYearID');

$formValues = array(
    'name' => 'Test Timetable',
    'nameShort' => 'TT',
    'nameShortDisplay' => 'Day Of The Week',
    'active' => 'Y',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');

$tawasulTTIDTest = $I->grabEditIDFromURL();

// Edit Timetable (to access nested day management) -----
$I->amOnModulePage('Timetable Admin', 'tt_edit.php', array(
    'tawasulTTID' => $tawasulTTIDTest,
    'tawasulSchoolYearID' => $tawasulSchoolYearID
));
$I->seeBreadcrumb('Edit Timetable');
$I->dontSeeErrors();

// Delete Timetable --------------------------------------
$I->amOnModulePage('Timetable Admin', 'tt_delete.php', array(
    'tawasulTTID' => $tawasulTTIDTest,
    'tawasulSchoolYearID' => $tawasulSchoolYearID
));

// Edit Existing Timetable -------------------------------
$I->amOnModulePage('Timetable Admin', 'tt.php');
$I->seeBreadcrumb('Manage Timetables');

$I->click('Edit');
$I->seeInCurrentUrl('tt_edit.php');
$I->seeBreadcrumb('Edit Timetable');

$tawasulTTID = $I->grabValueFromURL('tawasulTTID');

// Test Add Day action -----------------------------------
$I->clickNavigation('Add');
$I->seeInCurrentUrl('tt_edit_day_add.php');
$I->dontSeeErrors();

// Go back and add a day properly ------------------------
$I->selectFromDropdown('tawasulTTColumnID', 1);

$dayFormValues = array(
    'tawasulTTID' => $tawasulTTID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'name' => 'Monday',
    'nameShort' => 'Mon',
    'color' => '#3A6CA8',
    'fontColor' => '#FFFFFF',
);

$I->submitForm('#content form', $dayFormValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');

$tawasulTTDayID = $I->grabEditIDFromURL();

// Edit Day --------------------------------------------
// Navigate directly to ensure we're editing the day we just created
$I->amOnModulePage('Timetable Admin', 'tt_edit_day_edit.php', array(
    'tawasulTTDayID' => $tawasulTTDayID,
    'tawasulTTID' => $tawasulTTID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID
));
$I->seeBreadcrumb('Edit Timetable Day');
$I->dontSeeErrors();

$formValues = $I->grabAllFormValues();

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeInFormFields('#content form', $formValues);

// Edit Period --------------------------------------------
$I->click('Edit', "#timetableDayRows");
$I->seeBreadcrumb('Classes in Period');
$I->dontSeeErrors();

// Add Class --------------------------------------------

$I->click('Add');
$I->seeBreadcrumb('Add Class to Period');
$I->dontSeeErrors();

$I->selectFromDropdown('tawasulCourseClassID', 1);
$I->selectFromDropdown('tawasulSpaceID', 1);

$I->submitForm('#content form', []);
$I->see('Your request was completed successfully.', '.success');

$tawasulTTDayRowClassID = $I->grabEditIDFromURL();
$tawasulCourseClassID = $I->grabValueFromURL('tawasulCourseClassID');
$tawasulTTColumnRowID = $I->grabValueFromURL('tawasulTTColumnRowID');

// Edit Class --------------------------------------------
$I->amOnModulePage('Timetable Admin', 'tt_edit_day_edit_class_edit.php', array(
    'tawasulTTDayID' => $tawasulTTDayID,
    'tawasulTTID' => $tawasulTTID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulTTColumnRowID' => $tawasulTTColumnRowID,
    'tawasulTTDayRowClassID' => $tawasulTTDayRowClassID,
    'tawasulCourseClassID' => $tawasulCourseClassID
));
$I->seeBreadcrumb('Edit Class in Period');
$I->dontSeeErrors();

// Change location
$I->selectFromDropdown('tawasulSpaceID', 2);
$I->submitForm('#content form', [], 'Submit');
$I->see('Your request was completed successfully.', '.success');

// Manage Class Exceptions -------------------------------
$I->amOnModulePage('Timetable Admin', 'tt_edit_day_edit_class_exception.php', array(
    'tawasulTTDayID' => $tawasulTTDayID,
    'tawasulTTID' => $tawasulTTID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulTTColumnRowID' => $tawasulTTColumnRowID,
    'tawasulTTDayRowClassID' => $tawasulTTDayRowClassID,
    'tawasulCourseClassID' => $tawasulCourseClassID
));
$I->seeBreadcrumb('Class List Exception');
$I->dontSeeErrors();

// Add Exception -----------------------------------------
$I->click('Add');
$I->seeBreadcrumb('Add Exception');
$I->dontSeeErrors();

// Select a participant to exclude
$I->selectFromDropdown('Members', 1);
$I->submitForm('#content form', [], 'Submit');
$I->see('Your request was completed successfully.', '.success');

$tawasulTTDayRowClassExceptionID = $I->grabEditIDFromURL();

// Delete Exception --------------------------------------
$I->amOnModulePage('Timetable Admin', 'tt_edit_day_edit_class_exception_delete.php', array(
    'tawasulTTDayID' => $tawasulTTDayID,
    'tawasulTTID' => $tawasulTTID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulTTColumnRowID' => $tawasulTTColumnRowID,
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulTTDayRowClassID' => $tawasulTTDayRowClassID,
    'tawasulTTDayRowClassExceptionID' => $tawasulTTDayRowClassExceptionID
));

$I->click('Delete');
$I->see('Your request was completed successfully.', '.success');

// Delete Class ------------------------------------------
$I->amOnModulePage('Timetable Admin', 'tt_edit_day_edit_class_delete.php', array(
    'tawasulTTDayID' => $tawasulTTDayID,
    'tawasulTTID' => $tawasulTTID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulTTColumnRowID' => $tawasulTTColumnRowID,
    'tawasulCourseClassID' => $tawasulCourseClassID
));

$I->click('Delete');
$I->see('Your request was completed successfully.', '.success');

// Delete Day --------------------------------------------
$I->amOnModulePage('Timetable Admin', 'tt_edit_day_delete.php', array(
    'tawasulTTDayID' => $tawasulTTDayID,
    'tawasulTTID' => $tawasulTTID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID
));

$I->click('Delete');
$I->see('Your request was completed successfully.', '.success');

// Delete Test Timetable --------------------------------------
$I->amOnModulePage('Timetable Admin', 'tt_delete.php', array(
    'tawasulTTID' => $tawasulTTIDTest,
    'tawasulSchoolYearID' => $tawasulSchoolYearID
));

$I->click('Delete');
$I->see('Your request was completed successfully.', '.success');
