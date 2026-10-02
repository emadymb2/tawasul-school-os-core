<?php
/**
 * @covers modules/TawasulActivities/report_participants.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view participants by activity report');
$I->loginAsAdmin();
$I->amOnModulePage('Activities', 'report_participants.php');
$I->seeBreadcrumb('Participants by Activity');

// Select an activity
$I->selectFromDropdown('tawasulActivityID', 1);
$I->submitForm('#content form', []);
$I->seeInCurrentUrl('tawasulActivityID=');

// Test Print button
$I->click('Print');
