<?php
/**
 * @covers modules/TawasulTimetable/tt_manage_subscription.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('Manage timetable subscription');
$I->loginAsAdmin();

$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full']);

$I->amOnModulePage('Timetable', 'tt_manage_subscription.php', ['tawasulPersonID' => $tawasulPersonID]);
