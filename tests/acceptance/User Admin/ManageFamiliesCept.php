<?php
/**
 * @covers modules/TawasulUserAdmin/family_manage.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('Manage families');
$I->loginAsAdmin();
$I->amOnModulePage('User Admin', 'family_manage.php');
$I->seeBreadcrumb('Manage Families');
