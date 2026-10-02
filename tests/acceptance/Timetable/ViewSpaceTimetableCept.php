<?php
/**
 * @covers modules/TawasulTimetable/tt_space_view.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View space timetable');
$I->loginAsAdmin();

// Get a space
$tawasulSpaceID = $I->grabFromDatabase('tawasulSpace', 'tawasulSpaceID', []);
$tawasulTTID = $I->grabFromDatabase('tawasulTT', 'tawasulTTID', []);

$I->amOnModulePage('Timetable', 'tt_space_view.php', [
    'tawasulSpaceID' => $tawasulSpaceID,
    'tawasulTTID' => $tawasulTTID
]);
$I->seeBreadcrumb('View Timetable by Facility');
