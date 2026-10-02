<?php
/**
 * @covers modules/TawasulTimetable/tt_view.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View timetable by person');
$I->loginAsAdmin();

$I->amOnModulePage('Timetable', 'tt.php');
$I->seeBreadcrumb('View Timetable by Person');

// Get a person with a timetable
$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full']);
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$I->amOnModulePage('Timetable', 'tt_view.php', [
    'tawasulPersonID' => $tawasulPersonID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID
]);
$I->seeBreadcrumb('View Timetable');
