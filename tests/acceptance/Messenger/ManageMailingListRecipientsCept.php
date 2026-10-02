<?php
/**
 * @covers modules/TawasulMessenger/mailingListRecipients_manage.php
 * @covers modules/TawasulMessenger/mailingListRecipients_manage_add.php
 * @covers modules/TawasulMessenger/mailingListRecipients_manage_edit.php
 * @covers modules/TawasulMessenger/mailingListRecipients_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('Manage mailing list recipients with full CRUD operations');
$I->loginAsAdmin();
$I->amOnModulePage('Messenger', 'mailingListRecipients_manage.php');
$I->seeBreadcrumb('Manage Mailing List Recipients');

// Add a new recipient
$I->click('Add', 'a');
$I->seeBreadcrumb('Add');
$I->fillField('surname', 'Test');
$I->fillField('preferredName', 'Recipient');
$I->fillField('email', 'test@example.com');
$I->click('Submit');
$I->seeSuccessMessage();

// Edit the recipient
$tawasulMessengerMailingListRecipientID = $I->grabEditIDFromURL();
$I->amOnModulePage('Messenger', 'mailingListRecipients_manage_edit.php', ['tawasulMessengerMailingListRecipientID' => $tawasulMessengerMailingListRecipientID]);
$I->seeBreadcrumb('Edit');
$I->seeInField('email', 'test@example.com');
$I->fillField('preferredName', 'Updated');
$I->click('Submit');
$I->seeSuccessMessage();

// Delete the recipient
$I->amOnModulePage('Messenger', 'mailingListRecipients_manage_delete.php', ['tawasulMessengerMailingListRecipientID' => $tawasulMessengerMailingListRecipientID]);
$I->click('Delete');
$I->seeSuccessMessage();
