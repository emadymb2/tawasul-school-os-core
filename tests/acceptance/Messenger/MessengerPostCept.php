<?php
/**
 * @covers modules/TawasulMessenger/messenger_post.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('create a new message');
$I->loginAsAdmin();

// Set temporary email for admin user ------------------

$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['username' => 'testingadmin']);
$originalEmail = $I->grabFromDatabase('tawasulPerson', 'email', ['tawasulPersonID' => $tawasulPersonID]);

$I->updateInDatabase('tawasulPerson', ['email' => 'testingadmin@example.com'], ['tawasulPersonID' => $tawasulPersonID]);

// Re-login to refresh session with new email ---------

$I->amOnPage('/logout.php');
$I->loginAsAdmin();

// Test New Message Page -------------------------------

$I->amOnModulePage('Messenger', 'messenger_post.php');
$I->seeBreadcrumb('New Message');

$I->dontSeeErrors();

// Restore original email ------------------------------

$I->updateInDatabase('tawasulPerson', ['email' => $originalEmail], ['tawasulPersonID' => $tawasulPersonID]);
