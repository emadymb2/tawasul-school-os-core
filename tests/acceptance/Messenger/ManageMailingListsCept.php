<?php
/**
 * @covers modules/TawasulMessenger/mailingLists_manage.php
 * @covers modules/TawasulMessenger/mailingLists_manage_add.php
 * @covers modules/TawasulMessenger/mailingLists_manage_edit.php
 * @covers modules/TawasulMessenger/mailingLists_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('Manage mailing lists with full CRUD operations');
$I->loginAsAdmin();
$I->amOnModulePage('Messenger', 'mailingLists_manage.php');
$I->seeBreadcrumb('Manage Mailing Lists');

// Add a new mailing list
$I->click('Add', 'a');
$I->seeBreadcrumb('Add');
$I->fillField('name', 'Test Mailing List');
$I->fillField('active', 'Y');
$I->click('Submit');
$I->seeSuccessMessage();

// Edit the mailing list
$tawasulMessengerMailingListID = $I->grabEditIDFromURL();
$I->amOnModulePage('Messenger', 'mailingLists_manage_edit.php', ['tawasulMessengerMailingListID' => $tawasulMessengerMailingListID]);
$I->seeBreadcrumb('Edit');
$I->seeInField('name', 'Test Mailing List');
$I->fillField('name', 'Updated Mailing List');
$I->click('Submit');
$I->seeSuccessMessage();

// Delete the mailing list
$I->amOnModulePage('Messenger', 'mailingLists_manage_delete.php', ['tawasulMessengerMailingListID' => $tawasulMessengerMailingListID]);
$I->click('Delete');
$I->seeSuccessMessage();
