<?php 

$I = new AcceptanceTester($scenario);
$I->wantTo('login to TawasulOS as support staff');
$I->loginAsSupport();

// Logged In
$I->see('Logout', 'a');
