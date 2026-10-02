<?php
/**
 * @covers modules/TawasulActivities/activities_my.php
 * @covers modules/TawasulActivities/activities_my_full.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view my activities as a student');
$I->loginAsStudent();
$I->amOnModulePage('Activities', 'activities_my.php');
$I->seeBreadcrumb('My Activities');

// Test Full View (if there are activities enrolled) -----
// Get an activity ID that the student is enrolled in
$tawasulActivityID = $I->grabFromDatabase('tawasulActivityStudent', 'tawasulActivityID', [
    'tawasulPersonID' => $_SESSION['tawasulPersonID'] ?? null
]);

if ($tawasulActivityID) {
    $I->amOnModulePage('Activities', 'activities_my_full.php', [
        'tawasulActivityID' => $tawasulActivityID
    ]);
    $I->dontSeeErrors();
}
