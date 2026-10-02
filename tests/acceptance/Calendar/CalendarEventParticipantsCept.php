<?php
/**
 * @covers modules/TawasulCalendar/calendar_event_participants.php
 * @covers modules/TawasulCalendar/calendar_event_participants_add.php
 * @covers modules/TawasulCalendar/calendar_event_participants_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage calendar event participants');
$I->loginAsAdmin();

// Create a calendar first if none exists
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);
$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['username' => 'admin']);

$tawasulCalendarID = $I->haveInDatabase('tawasulCalendar', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'name' => 'Test Calendar for Participants',
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
    'name' => 'Test Event for Participants',
    'status' => 'Confirmed',
    'dateStart' => date('Y-m-d'),
    'dateEnd' => date('Y-m-d'),
    'allDay' => 'Y',
];

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$tawasulCalendarEventID = $I->grabEditIDFromURL();

// Navigate to Participants Page -----------------------

$I->amOnModulePage('Calendar', 'calendar_event_participants.php', [
    'tawasulCalendarEventID' => $tawasulCalendarEventID
]);
$I->seeBreadcrumb('Edit Participants');
$I->dontSeeErrors();

// Test Add Participants Page --------------------------

$I->clickNavigation('Add Participants');
$I->seeBreadcrumb('Add Participants');
$I->dontSeeErrors();

// Create a participant directly in the database for delete test
$tawasulPersonIDParticipant = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', []);

$tawasulCalendarEventPersonID = $I->haveInDatabase('tawasulCalendarEventPerson', [
    'tawasulCalendarEventID' => $tawasulCalendarEventID,
    'tawasulPersonID' => $tawasulPersonIDParticipant,
    'role' => 'Attendee',
    'tawasulPersonIDCreated' => $tawasulPersonID,
    'tawasulPersonIDModified' => $tawasulPersonID,
]);

// Test Delete Participant -----------------------------

$I->amOnModulePage('Calendar', 'calendar_event_participants_delete.php', [
    'tawasulCalendarEventID' => $tawasulCalendarEventID,
    'tawasulCalendarEventPersonID' => $tawasulCalendarEventPersonID,
    'tawasulPersonID' => $tawasulPersonIDParticipant
]);

// $I->fillField('confirm', 'Delete');
$I->click('Yes');
$I->seeSuccessMessage();

// Clean up - delete the event
$I->amOnModulePage('Calendar', 'calendar_event_delete.php', [
    'tawasulCalendarEventID' => $tawasulCalendarEventID
]);

$I->fillField('confirm', 'Delete');
$I->click('Yes');
$I->seeSuccessMessage();
