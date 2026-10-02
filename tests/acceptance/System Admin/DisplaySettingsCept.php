<?php 
/**
 * @covers modules/TawasulSystemAdmin/displaySettings.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('update Display Settings');
$I->loginAsAdmin();
$I->amOnModulePage('System Admin', 'displaySettings.php');

// Grab Original Settings --------------------------------------

$originalFormValues = $I->grabAllFormValues();
$I->seeInFormFields('#content form', $originalFormValues);

// Make Changes ------------------------------------------------

$newFormValues = array(
    'mainMenuCategoryOrder' => 'Other,People,Learn,Assess,Admin',
);

$I->submitForm('#content form', $newFormValues, 'Submit');

// Verify Results ----------------------------------------------

$I->see('Your request was completed successfully.', '.success');
$I->seeInFormFields('#content form', $newFormValues);

// Restore Original Settings -----------------------------------

$I->submitForm('#content form', $originalFormValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');
$I->seeInFormFields('#content form', $originalFormValues);

// File Upload: Organisation Logo ------------------------------

$originalLogo = $I->grabFromDatabase('tawasulSetting', 'value', ['scope' => 'System', 'name' => 'organisationLogo']);

// Clear the logo setting so we can verify the upload sets a new value
$I->updateInDatabase('tawasulSetting', ['value' => ''], ['scope' => 'System', 'name' => 'organisationLogo']);

$I->amOnModulePage('System Admin', 'displaySettings.php');
$I->attachFile('organisationLogoFile', 'attachment.jpg');
$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

$newLogo = $I->grabFromDatabase('tawasulSetting', 'value', ['scope' => 'System', 'name' => 'organisationLogo']);
$I->assertNotEmpty($newLogo);
$I->assertStringContainsString('uploads/', $newLogo);

// Restore original logo setting
$I->updateInDatabase('tawasulSetting', ['value' => $originalLogo], ['scope' => 'System', 'name' => 'organisationLogo']);

// Cleanup uploaded file if different from original
if ($newLogo !== $originalLogo) {
    $I->deleteFile('../'.$newLogo);
}

// File Upload: Organisation Background -------------------------

$originalBg = $I->grabFromDatabase('tawasulSetting', 'value', ['scope' => 'System', 'name' => 'organisationBackground']);

$I->updateInDatabase('tawasulSetting', ['value' => ''], ['scope' => 'System', 'name' => 'organisationBackground']);

$I->amOnModulePage('System Admin', 'displaySettings.php');
$I->attachFile('organisationBackgroundFile', 'attachment2.png');
$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

$newBg = $I->grabFromDatabase('tawasulSetting', 'value', ['scope' => 'System', 'name' => 'organisationBackground']);
$I->assertNotEmpty($newBg);
$I->assertStringContainsString('uploads/', $newBg);

// Restore original background setting
$I->updateInDatabase('tawasulSetting', ['value' => $originalBg], ['scope' => 'System', 'name' => 'organisationBackground']);

// Cleanup uploaded file if different from original
if ($newBg !== $originalBg) {
    $I->deleteFile('../'.$newBg);
}
