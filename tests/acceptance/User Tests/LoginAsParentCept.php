<?php 

$I = new AcceptanceTester($scenario);
$I->wantTo('login to TawasulOS as a parent');
$I->loginAsParent();

// Logged In
$I->see('Logout', 'a');
