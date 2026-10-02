<?php
/**
 * @covers modules/TawasulActivities/activities_view.php
 * @covers modules/TawasulActivities/activities_view_full.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view activities as a student');
$I->loginAsStudent();
$I->amOnModulePage('Activities', 'activities_view.php');
$I->seeBreadcrumb('View Activities');

// Test search form
$I->submitForm('#searchForm', [
    'search' => 'Test',
]);
$I->seeInCurrentUrl('search=Test');

// Test Full View (if there are activities available) ----
$tawasulActivityID = $I->grabFromDatabase('tawasulActivity', 'tawasulActivityID', [
    'active' => 'Y'
]);

if ($tawasulActivityID) {
    $I->amOnModulePage('Activities', 'activities_view_full.php', [
        'tawasulActivityID' => $tawasulActivityID
    ]);
    $I->dontSeeErrors();
}
