<?php
/**
 * @covers modules/TawasulTimetable/report_viewAvailableTeachers_view.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View available teachers details');
$I->loginAsAdmin();

// Get a timetable day
$tawasulTTDayID = $I->grabFromDatabase('tawasulTTDay', 'tawasulTTDayID', []);

$I->amOnModulePage('Timetable', 'report_viewAvailableTeachers_view.php', ['tawasulTTDayID' => $tawasulTTDayID]);
