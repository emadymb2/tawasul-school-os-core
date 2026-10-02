<?php
/**
 * @covers modules/TawasulCalendar/calendar_event_view.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check View Event');
$I->loginAsAdmin();

// Get an existing event ID from the database
$tawasulCalendarEventID = $I->grabFromDatabase('tawasulCalendarEvent', 'tawasulCalendarEventID', []);

$I->amOnModulePage('Calendar', 'calendar_event_view.php', [
    'tawasulCalendarEventID' => $tawasulCalendarEventID
]);
$I->seeBreadcrumb('View Event');

// Basic Check -----------------------------------------

$I->dontSeeErrors();
