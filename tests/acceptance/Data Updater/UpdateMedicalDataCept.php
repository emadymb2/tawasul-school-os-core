<?php 
/**
 * @covers modules/TawasulDataUpdater/data_medical.php
 * @covers modules/TawasulDataUpdater/data_medical_manage_edit.php
 * @covers modules/TawasulDataUpdater/data_medical_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('submit and approve a medical data update');
$I->loginAsAdmin();

$I->amOnModulePage('Data Updater', 'data_medical.php');

// Select ------------------------------------------------
$I->seeBreadcrumb('Update Medical Data');

$I->selectFromDropdown('tawasulPersonID', 2);
$I->click('Submit');

// Cleanup ------------------------------------------------

$tawasulPersonID = $I->grabValueFromURL('tawasulPersonID');
$tawasulPersonMedicalID = $I->grabFromDatabase('tawasulPersonMedical', 'tawasulPersonMedicalID', ['tawasulPersonID' => $tawasulPersonID]);

$I->deleteFromDatabase('tawasulPersonMedicalCondition', ['tawasulPersonMedicalID' => $tawasulPersonMedicalID]);
$I->deleteFromDatabase('tawasulPersonMedical', ['tawasulPersonID' => $tawasulPersonID]);


// Update ------------------------------------------------
$I->see('Update Data');

$editFormValues = array(
    'longTermMedication'        => 'Y',
    'longTermMedicationDetails' => 'Test ' . date('Y-m-d'),
);

// Add a new medical condition with attachment
$I->checkOption('addCondition');
$I->selectOption('name', 'Asthma');
$I->selectOption('tawasulAlertLevelID', '001');
$I->fillField('triggers', 'Test triggers');
$I->attachFile('attachment', 'attachment.txt');
$I->submitForm('#content form[method="post"]', $editFormValues, 'Submit');

// Confirm ------------------------------------------------
$I->seeSuccessMessage();

$tawasulPersonID = $I->grabValueFromURL('tawasulPersonID');

// Verify the attachment was stored
$tawasulPersonMedicalUpdateID = $I->grabFromDatabase('tawasulPersonMedicalUpdate', 'tawasulPersonMedicalUpdateID', ['tawasulPersonID' => $tawasulPersonID, 'status' => 'Pending']);
$file = $I->grabFromDatabase('tawasulPersonMedicalConditionUpdate', 'attachment', ['tawasulPersonMedicalUpdateID' => $tawasulPersonMedicalUpdateID, 'name' => 'Asthma']);
$I->assertNotEmpty($file);


$I->amOnModulePage('Data Updater', 'data_medical.php', ['tawasulPersonID' => $tawasulPersonID]);
$I->seeInFormFields('#content form[method="post"]', $editFormValues);

$tawasulPersonMedicalUpdateID = $I->grabValueFrom("input[type='hidden'][name='existing']");

// Accept ------------------------------------------------
$I->amOnModulePage('Data Updater', 'data_medical_manage_edit.php', array('tawasulPersonMedicalUpdateID' => $tawasulPersonMedicalUpdateID));
$I->seeBreadcrumb('Edit Request');

$I->see('Y', 'td');
$I->see('Test', 'td');

$I->click('Submit');
$I->seeSuccessMessage();

$tawasulPersonMedicalUpdateID = $I->grabValueFromURL('tawasulPersonMedicalUpdateID');

// Delete ------------------------------------------------
$I->amOnModulePage('Data Updater', 'data_medical_manage_delete.php', array('tawasulPersonMedicalUpdateID' => $tawasulPersonMedicalUpdateID));

$I->click('Delete');
$I->seeSuccessMessage();


// Select ------------------------------------------------
$I->amOnModulePage('Data Updater', 'data_medical.php');
$I->seeBreadcrumb('Update Medical Data');

$I->selectFromDropdown('tawasulPersonID', 2);
$I->click('Submit');

// Update ------------------------------------------------
$I->see('Update Data');

$editFormValues = array(
    'longTermMedication'        => 'N',
    'longTermMedicationDetails' => 'Test2',
);

$I->submitForm('#content form[method="post"]', $editFormValues, 'Submit');

// Confirm ------------------------------------------------
$I->seeSuccessMessage();

$tawasulPersonID = $I->grabValueFromURL('tawasulPersonID');

$I->amOnModulePage('Data Updater', 'data_medical.php', ['tawasulPersonID' => $tawasulPersonID]);
$I->seeInFormFields('#content form[method="post"]', $editFormValues);

$tawasulPersonMedicalUpdateID = $I->grabValueFrom("input[type='hidden'][name='existing']");

// Accept ------------------------------------------------
$I->amOnModulePage('Data Updater', 'data_medical_manage_edit.php', array('tawasulPersonMedicalUpdateID' => $tawasulPersonMedicalUpdateID));
$I->seeBreadcrumb('Edit Request');

$I->see('N', 'td');
$I->see('Test2', 'td');

$I->click('Submit');
$I->seeSuccessMessage();

$tawasulPersonMedicalUpdateID = $I->grabValueFromURL('tawasulPersonMedicalUpdateID');

// Delete ------------------------------------------------
$I->amOnModulePage('Data Updater', 'data_medical_manage_delete.php', array('tawasulPersonMedicalUpdateID' => $tawasulPersonMedicalUpdateID));

$I->click('Delete');
$I->seeSuccessMessage();

// Cleanup ------------------------------------------------

$tawasulPersonMedicalID = $I->grabFromDatabase('tawasulPersonMedical', 'tawasulPersonMedicalID', ['tawasulPersonID' => $tawasulPersonID]);

$I->deleteFromDatabase('tawasulPersonMedicalCondition', ['tawasulPersonMedicalID' => $tawasulPersonMedicalID]);
$I->deleteFromDatabase('tawasulPersonMedical', ['tawasulPersonID' => $tawasulPersonID]);

$I->deleteFile('../'.$file);

