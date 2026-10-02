<?php
/**
 * @covers modules/TawasulBehaviour/behaviour_view_details.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View behaviour details');
$I->loginAsAdmin();

$tawasulBehaviourID = $I->grabFromDatabase('tawasulBehaviour', 'tawasulBehaviourID', []);
$I->amOnModulePage('Behaviour', 'behaviour_view_details.php', ['tawasulBehaviourID' => $tawasulBehaviourID]);
