<?php
/**
 * @covers modules/TawasulCalendar/calendar_manage.php
 * @covers modules/TawasulCalendar/calendar_manage_addEdit.php
 * @covers modules/TawasulCalendar/calendar_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add, edit and delete a calendar');
$I->loginAsAdmin();
$I->amOnModulePage('Calendar', 'calendar_manage.php');

// Add ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Calendar');

$tawasulSchoolYearID = $I->grabValueFromURL('tawasulSchoolYearID');

$formValues = array(
    'name'        => 'Test Calendar',
    'description' => 'Test calendar description',
    'color'       => '#FF0000',
    'public'      => 'N',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');

$tawasulCalendarID = $I->grabEditIDFromURL();

// Edit ------------------------------------------------
$I->amOnModulePage('Calendar', 'calendar_manage_addEdit.php', array(
    'tawasulCalendarID' => $tawasulCalendarID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID
));
$I->seeBreadcrumb('Edit Calendar');

$I->seeInField('name', 'Test Calendar');

$formValues = array(
    'name'        => 'Updated Test Calendar',
    'description' => 'Updated description',
    'public'      => 'Y',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');

// Delete ------------------------------------------------
$I->amOnModulePage('Calendar', 'calendar_manage_delete.php', array(
    'tawasulCalendarID' => $tawasulCalendarID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID
));

$I->fillField('confirm', 'Delete');
$I->click('Yes');
$I->see('Your request was completed successfully.', '.success');
