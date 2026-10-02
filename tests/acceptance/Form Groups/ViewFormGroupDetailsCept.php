<?php
/**
 * @covers modules/TawasulFormGroups/formGroups_details.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view form group details');
$I->loginAsAdmin();

$tawasulFormGroupID = $I->grabFromDatabase('tawasulFormGroup', 'tawasulFormGroupID', []);

$I->amOnModulePage('Form Groups', 'formGroups_details.php', ['tawasulFormGroupID' => $tawasulFormGroupID]);
$I->seeBreadcrumb('View Form Groups');
