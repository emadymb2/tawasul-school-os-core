<?php
/**
 * @covers modules/TawasulIndividualNeeds/investigations_submit_detail.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View investigation details');
$I->loginAsAdmin();

// Get an existing investigation
$tawasulINInvestigationID = $I->grabFromDatabase('tawasulINInvestigation', 'tawasulINInvestigationID', []);

$I->amOnModulePage('Individual Needs', 'investigations_submit_detail.php', ['tawasulINInvestigationID' => $tawasulINInvestigationID]);
$I->seeBreadcrumb('Submit Contribution');
