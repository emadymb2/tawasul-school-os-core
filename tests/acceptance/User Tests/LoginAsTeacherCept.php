<?php 

$I = new AcceptanceTester($scenario);
$I->wantTo('login to TawasulOS as a teacher');
$I->loginAsTeacher();

// Logged In
$I->see('Staff Dashboard', 'h2');
