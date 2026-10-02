<?php
/**
 * @covers modules/TawasulMessenger/messenger_emailReceiptConfirm.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('confirm email receipt');
$I->loginAsAdmin();

// Create test message and receipt --------------------

$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full']);

$tawasulMessengerID = $I->haveInDatabase('tawasulMessenger', [
    'tawasulPersonID' => $tawasulPersonID,
    'subject' => 'Test Receipt Message',
    'body' => 'This is a test message for receipt testing.',
    'messageWall' => 'N',
    'email' => 'Y',
    'emailReport' => '',
    'emailReceipt' => 'Y',
    'sms' => 'N',
    'smsReport' => '',
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => 'Sent',
]);

$receiptKey = bin2hex(random_bytes(16));

$tawasulMessengerReceiptID = $I->haveInDatabase('tawasulMessengerReceipt', [
    'tawasulMessengerID' => $tawasulMessengerID,
    'tawasulPersonID' => $tawasulPersonID,
    'contactType' => 'Email',
    'contactDetail' => 'test@example.com',
    'key' => $receiptKey,
    'confirmed' => 'N',
    'sent' => 'Y',
    'targetType' => 'Role',
    'targetID' => '001',
]);

// Test Email Receipt Confirmation --------------------

$I->amOnModulePage('Messenger', 'messenger_emailReceiptConfirm.php', [
    'key' => 'test',
    'tawasulPersonID' => $tawasulPersonID,
    'tawasulMessengerID' => $tawasulMessengerID,
]);

$I->see('Test Email');
$I->see('Thank you for confirming receipt and reading of this email');

// Clean up --------------------------------------------

$I->amOnModulePage('Messenger', 'messenger_manage_delete.php', [
    'tawasulMessengerID' => $tawasulMessengerID,
]);

$I->click('Delete');
$I->seeSuccessMessage();
