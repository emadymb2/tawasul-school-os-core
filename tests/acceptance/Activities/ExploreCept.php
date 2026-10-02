<?php
/**
 * @covers modules/TawasulActivities/explore.php
 * @covers modules/TawasulActivities/explore_activity.php
 * @covers modules/TawasulActivities/explore_activity_signUp.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('explore activities and view activity details with sign-up');
$I->loginAsStudent();
$I->amOnModulePage('Activities', 'explore.php');
$I->seeBreadcrumb('Explore Activities');

// Test Activity View (if there are activities available) 
$tawasulActivityID = $I->grabFromDatabase('tawasulActivity', 'tawasulActivityID', [
    'active' => 'Y'
]);

if ($tawasulActivityID) {
    $I->amOnModulePage('Activities', 'explore_activity.php', [
        'tawasulActivityID' => $tawasulActivityID
    ]);
    $I->seeBreadcrumb('Activity');
    $I->dontSeeErrors();
    
    // Test Sign Up Action (if available) --------------------
    $I->amOnModulePage('Activities', 'explore_activity_signUp.php', [
        'tawasulActivityID' => $tawasulActivityID
    ]);
    $I->dontSeeErrors();
}
