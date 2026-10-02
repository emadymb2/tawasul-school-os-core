<?php
/**
 * @covers modules/TawasulSystemAdmin/notificationSettings.php
 * @covers modules/TawasulSystemAdmin/notificationSettings_manage_edit.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check notification settings');
$I->loginAsAdmin();

$I->amOnModulePage('System Admin', 'notificationSettings.php');
$I->seeBreadcrumb('Notification Settings');

// Basic Check -----------------------------------------

$I->dontSeeErrors();

// Test Edit Action (DataTable action) ----------------

$I->click('Edit');
$I->seeInCurrentUrl('notificationSettings_manage_edit.php');
$I->dontSeeErrors();
