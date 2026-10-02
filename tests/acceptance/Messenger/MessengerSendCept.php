<?php
/**
 * @covers modules/TawasulMessenger/messenger_send.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('preview and send a message');
$I->loginAsAdmin();

// Create test message ---------------------------------

$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full']);

$tawasulMessengerID = $I->haveInDatabase('tawasulMessenger', [
    'tawasulPersonID' => $tawasulPersonID,
    'subject' => 'Test Send Message',
    'body' => 'This is a test message for send testing.',
    'messageWall' => 'Y',
    'messageWall_dateStart' => date('Y-m-d'),
    'messageWall_dateEnd' => date('Y-m-d', strtotime('+7 days')),
    'email' => 'N',
    'emailReport' => '',
    'sms' => 'N',
    'smsReport' => '',
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => 'Draft',
]);

// Preview & Send --------------------------------------

$I->amOnModulePage('Messenger', 'messenger_send.php', [
    'tawasulMessengerID' => $tawasulMessengerID,
]);
$I->seeBreadcrumb('Preview & Send');

$I->dontSeeErrors();

// Clean up --------------------------------------------

$I->amOnModulePage('Messenger', 'messenger_manage_delete.php', [
    'tawasulMessengerID' => $tawasulMessengerID,
]);

$I->click('Delete');
$I->seeSuccessMessage();
