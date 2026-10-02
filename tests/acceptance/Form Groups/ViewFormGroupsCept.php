<?php
/**
 * @covers modules/TawasulFormGroups/formGroups.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view form groups');
$I->loginAsAdmin();
$I->amOnModulePage('Form Groups', 'formGroups.php');
$I->seeBreadcrumb('View Form Groups');
