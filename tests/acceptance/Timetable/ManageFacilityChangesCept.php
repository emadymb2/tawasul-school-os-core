<?php
/**
 * @covers modules/TawasulTimetable/spaceChange_manage.php
 * @covers modules/TawasulTimetable/spaceChange_manage_add.php
 * @covers modules/TawasulTimetable/spaceChange_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add and delete a facility change');
$I->loginAsAdmin();
$I->amOnModulePage('Timetable', 'spaceChange_manage.php');
$I->seeBreadcrumb('Manage Facility Changes');

// Search Test -----------------------------------------

$I->fillField('search', 'test');
$I->submitForm('#searchForm', []);
$I->dontSeeErrors();

// Add ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Facility Change');
$I->dontSeeErrors();

// Create test data directly in database
$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full']);
$tawasulCourseClassID = $I->grabFromDatabase('tawasulCourseClass', 'tawasulCourseClassID', []);
$tawasulSpaceID = $I->grabFromDatabase('tawasulSpace', 'tawasulSpaceID', []);

// Get a future timetable slot
$tawasulTTDayRowClassID = $I->grabFromDatabase('tawasulTTDayRowClass', 'tawasulTTDayRowClassID', [
    'tawasulCourseClassID' => $tawasulCourseClassID
]);

$tawasulTTSpaceChangeID = $I->haveInDatabase('tawasulTTSpaceChange', [
    'tawasulTTDayRowClassID' => $tawasulTTDayRowClassID,
    'tawasulSpaceID' => $tawasulSpaceID,
    'tawasulPersonID' => $tawasulPersonID,
    'date' => date('Y-m-d', strtotime('+1 day')),
]);

// Delete ------------------------------------------------
$I->amOnModulePage('Timetable', 'spaceChange_manage_delete.php', array(
    'tawasulTTSpaceChangeID' => $tawasulTTSpaceChangeID,
    'tawasulCourseClassID' => $tawasulCourseClassID
));

$I->click('Delete');
$I->seeSuccessMessage();
