<?php
/**
 * @covers modules/TawasulPlanner/planner_unitOverview.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check Unit Overview');
$I->loginAsAdmin();

// Get a planner entry
$tawasulPlannerEntryID = $I->grabFromDatabase('tawasulPlannerEntry', 'tawasulPlannerEntryID', []);

$I->amOnModulePage('Planner', 'planner_unitOverview.php', [
    'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
    'viewBy' => 'date'
]);
$I->seeBreadcrumb('Unit Overview');

// Basic Check -----------------------------------------

$I->dontSeeErrors();
