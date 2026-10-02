<?php
/**
 * @covers modules/TawasulPlanner/resources_view_full.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View full resource details');
$I->loginAsAdmin();

// Get an existing resource ID
$tawasulResourceID = $I->grabFromDatabase('tawasulResource', 'tawasulResourceID', []);

$I->amOnModulePage('Planner', 'resources_view_full.php', ['tawasulResourceID' => $tawasulResourceID]);
