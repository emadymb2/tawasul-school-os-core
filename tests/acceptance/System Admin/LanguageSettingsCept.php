<?php 
/**
 * @covers modules/TawasulSystemAdmin/i18n_manage.php
 * @covers modules/TawasulSystemAdmin/i18n_manage_install.php
 * @covers modules/TawasulSystemAdmin/i18n_manage_updateAll.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('update Language Settings');
$I->loginAsAdmin();
$I->amOnModulePage('System Admin', 'i18n_manage.php');

// Grab Original Settings --------------------------------------

$originalFormValues = $I->grabAllFormValues();
$I->seeInFormFields('#content form', $originalFormValues);

// Make Changes ------------------------------------------------

$I->selectOption('tawasuli18nID', '0001');
$I->submitForm('#content form', array(), 'Submit');

// Verify Results ----------------------------------------------

$I->see('Your request was completed successfully.', '.success');
$I->seeOptionIsSelected('tawasuli18nID', '0001');

// Restore Original Settings -----------------------------------

$I->submitForm('#content form', $originalFormValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');
$I->seeInFormFields('#content form', $originalFormValues);

// Test Install Page (DataTable action) -----------------------

$tawasuli18nID = $I->grabFromDatabase('tawasuli18n', 'tawasuli18nID', []);

$I->amOnModulePage('System Admin', 'i18n_manage_install.php', [
    'tawasuli18nID' => $tawasuli18nID
]);
$I->dontSeeErrors();

// Test Update All Page (DataTable action) --------------------

$I->amOnModulePage('System Admin', 'i18n_manage_updateAll.php');
$I->dontSeeErrors();
