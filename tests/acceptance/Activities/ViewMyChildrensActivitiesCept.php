<?php
/**
 * @covers modules/TawasulActivities/activities_view_myChildren.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View my children\'s activities');
$I->loginAsParent();

// Get the current school year
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$I->amOnModulePage('Activities', 'activities_view_myChildren.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);
$I->seeBreadcrumb('View Activities');
