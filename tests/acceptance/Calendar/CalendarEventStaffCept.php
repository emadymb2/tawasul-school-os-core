<?php
/**
 * @covers modules/TawasulCalendar/calendar_event_editStaff_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage calendar event staff');
$I->loginAsAdmin();

// Create a calendar first if none exists
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);
$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['username' => 'admin']);

$tawasulCalendarID = $I->haveInDatabase('tawasulCalendar', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'name' => 'Test Calendar for Staff',
    'color' => '#3A6CA8',
    'public' => 'Y',
    'sequenceNumber' => 1,
]);

// Add calendar editor permission
$I->haveInDatabase('tawasulCalendarEditor', [
    'tawasulCalendarID' => $tawasulCalendarID,
    'tawasulPersonID' => $tawasulPersonID,
]);

// Create a test event first
$I->amOnModulePage('Calendar', 'calendar_event_manage.php');
$I->seeBreadcrumb('Manage Events');

$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Event');

$I->selectFromDropdown('tawasulCalendarID', 1);
$I->selectFromDropdown('tawasulCalendarEventTypeID', 1);

$formValues = [
    'name' => 'Test Event for Staff',
    'status' => 'Confirmed',
    'dateStart' => date('Y-m-d'),
    'dateEnd' => date('Y-m-d'),
    'allDay' => 'Y',
];

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$tawasulCalendarEventID = $I->grabEditIDFromURL();

// Add staff to the event
$I->amOnModulePage('Calendar', 'calendar_event_edit.php', [
    'tawasulCalendarEventID' => $tawasulCalendarEventID
]);
$I->seeBreadcrumb('Edit Event');

$I->selectFromDropdown('staff', 1);
$I->selectFromDropdown('role', 1);

$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

// Get the staff person ID from the database
$tawasulCalendarEventPersonID = $I->grabFromDatabase('tawasulCalendarEventPerson', 'tawasulCalendarEventPersonID', [
    'tawasulCalendarEventID' => $tawasulCalendarEventID
]);

// Test Delete Staff Action ----------------------------

$I->amOnModulePage('Calendar', 'calendar_event_editStaff_delete.php', [
    'tawasulCalendarEventID' => $tawasulCalendarEventID,
    'tawasulCalendarEventPersonID' => $tawasulCalendarEventPersonID
]);

$I->click('Delete');
$I->seeSuccessMessage();

// Clean up - delete the event
$I->amOnModulePage('Calendar', 'calendar_event_delete.php', [
    'tawasulCalendarEventID' => $tawasulCalendarEventID
]);

$I->fillField('confirm', 'Delete');
$I->click('Yes');
$I->seeSuccessMessage();
