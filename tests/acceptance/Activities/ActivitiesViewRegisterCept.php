<?php
/**
 * @covers modules/TawasulActivities/activities_view_register.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view activity register');
$I->loginAsAdmin();

// Get an active activity to test the register view
$tawasulActivityID = $I->grabFromDatabase('tawasulActivity', 'tawasulActivityID', [
    'active' => 'Y'
]);

if ($tawasulActivityID) {
    $I->amOnModulePage('Activities', 'activities_view_register.php', [
        'tawasulActivityID' => $tawasulActivityID
    ]);
    $I->dontSeeErrors();
}
