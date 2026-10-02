<?php
/**
 * @covers modules/TawasulPlanner/planner.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View planner');
$I->loginAsAdmin();

// Get a course class
$tawasulCourseClassID = $I->grabFromDatabase('tawasulCourseClass', 'tawasulCourseClassID', []);

$I->amOnModulePage('Planner', 'planner.php', ['tawasulCourseClassID' => $tawasulCourseClassID]);
$I->seeBreadcrumb('Planner');
