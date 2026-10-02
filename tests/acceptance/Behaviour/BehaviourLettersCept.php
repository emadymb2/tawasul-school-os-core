<?php
/**
 * @covers modules/TawasulBehaviour/behaviour_letters.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check View Behaviour Letters');
$I->loginAsAdmin();

$I->amOnModulePage('Behaviour', 'behaviour_letters.php');

// Basic Check -----------------------------------------

$I->dontSeeErrors();

// Filter Test -----------------------------------------

$I->selectFromDropdown('tawasulPersonID', 1);
$I->submitForm('#filter', []);
$I->dontSeeErrors();
