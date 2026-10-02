<?php
/**
 * @covers modules/TawasulSystemAdmin/file_upload.php
 * @covers modules/TawasulSystemAdmin/file_uploadPreview.php
 * @covers modules/TawasulSystemAdmin/file_uploadProcess.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('upload a ZIP of user photos');
$I->loginAsAdmin();

$I->amOnModulePage('System Admin', 'file_upload.php');
$I->seeBreadcrumb('Upload Photos & Files');

// Basic Check -----------------------------------------

$I->dontSeeErrors();

// Save original photo for cleanup
$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['username' => 'testingadmin']);
$originalPhoto = $I->grabFromDatabase('tawasulPerson', 'image_240', ['tawasulPersonID' => $tawasulPersonID]);

// Step 1 - Upload ZIP ---------------------------------

$I->attachFile('file', 'test_photos.zip');
$I->selectOption('type', 'userPhotos');

$I->submitForm('#content form', [], 'Submit');

// Step 2 - Preview and confirm ------------------------

$I->seeBreadcrumb('Step 2');
$I->dontSeeErrors();
$I->see('testingadmin');

$I->submitForm('#content form', [], 'Submit');

// Step 3 - Verify success -----------------------------

$I->seeBreadcrumb('Step 3');
$I->see('Import successful', '.success');

// Verify the photo was updated in DB
$newPhoto = $I->grabFromDatabase('tawasulPerson', 'image_240', ['tawasulPersonID' => $tawasulPersonID]);
$I->assertNotEmpty($newPhoto);

// Restore original photo and cleanup
$I->updateInDatabase('tawasulPerson', ['image_240' => $originalPhoto], ['tawasulPersonID' => $tawasulPersonID]);
$I->deleteFile('../'.$newPhoto);
