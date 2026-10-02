<?php
/**
 * @covers modules/TawasulTimetable/report_viewAvailableSpace_view.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View available space details');
$I->loginAsAdmin();

// Get a timetable day
$tawasulTTDayID = $I->grabFromDatabase('tawasulTTDay', 'tawasulTTDayID', []);

$I->amOnModulePage('Timetable', 'report_viewAvailableSpace_view.php', ['tawasulTTDayID' => $tawasulTTDayID]);
