<?php
/**
 * @covers modules/TawasulActivities/explore_category.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('Explore activity category');
$I->loginAsAdmin();

// Get an existing activity type
$tawasulActivityTypeID = $I->grabFromDatabase('tawasulActivityType', 'tawasulActivityTypeID', []);

$I->amOnModulePage('Activities', 'explore_category.php', ['tawasulActivityTypeID' => $tawasulActivityTypeID]);
$I->seeBreadcrumb('Explore Activities');
