<?php
/**
 * @covers modules/TawasulSystemAdmin/cacheManager.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View cache manager');
$I->loginAsAdmin();
$I->amOnModulePage('System Admin', 'cacheManager.php');
$I->seeBreadcrumb('Cache Manager');
