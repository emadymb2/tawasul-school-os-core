<?php
/**
 * @covers modules/TawasulAdmissions/applicationFormSelect.php
 * @covers modules/TawasulAdmissions/applicationForm.php
 * @covers modules/TawasulAdmissions/applicationForm_payFee.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check Admissions Welcome and Application Form');

$I->updateInDatabase('tawasulForm', ['active' => 'Y', 'public' => 'Y'], ['name' => 'Sample Application Form']);

// Basic Check -----------------------------------------
$I->amOnModulePage('Admissions', 'applicationFormSelect.php');

$I->see('Sample Application Form');
$I->dontSeeErrors();

$I->fillField('admissionsLoginEmail', 'testnew' . time() . '@example.com');
$I->click('Next');

$I->seeBreadcrumb('Application Form');
$I->dontSeeErrors();

// Test Application Form access link ----
$tawasulFormID = $I->grabValueFromURL('tawasulFormID');
$accessID = $I->grabValueFromURL('accessID');

$tawasulAdmissionsAccountID = $I->grabFromDatabase('tawasulAdmissionsAccount', 'tawasulAdmissionsAccountID', [
    'accessID' => $accessID
]);

$accessToken = $I->grabFromDatabase('tawasulAdmissionsAccount', 'accessToken', [
    'accessID' => $accessID
]);

// Test Application Form page
$I->amOnModulePage('Admissions', 'applicationForm.php', [
    'accessID' => $accessID,
    'tawasulFormID' => $tawasulFormID
]);
$I->seeBreadcrumb('Application Form');
$I->dontSeeErrors();

// Test Application Pay Fee page (requires identifier)
// Create a test application
$identifier = 'TEST' . time();
$tawasulAdmissionsApplicationID = $I->haveInDatabase('tawasulAdmissionsApplication', [
    'tawasulFormID' => $tawasulFormID,
    'foreignTable' => 'tawasulAdmissionsAccount',
    'foreignTableID' => $tawasulAdmissionsAccountID,
    'identifier' => $identifier,
    'status' => 'Incomplete',
    'timestampCreated' => date('Y-m-d H:i:s'),
]);

$I->amOnModulePage('Admissions', 'applicationForm_payFee.php', [
    'accessID' => $accessID,
    'tok' => $accessToken,
    'tawasulFormID' => $tawasulFormID,
    'identifier' => $identifier,
]);
$I->seeBreadcrumb('Application Fee');
$I->dontSeeErrors();


$I->deleteFromDatabase('tawasulAdmissionsAccount', ['tawasulAdmissionsAccountID' => $tawasulAdmissionsAccountID]);
$I->deleteFromDatabase('tawasulAdmissionsApplication', ['tawasulAdmissionsApplicationID' => $tawasulAdmissionsApplicationID]);

$I->updateInDatabase('tawasulForm', ['active' => 'N', 'public' => 'N'], ['name' => 'Sample Application Form']);
