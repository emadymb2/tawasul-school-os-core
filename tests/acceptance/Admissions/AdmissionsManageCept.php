<?php
/**
 * @covers modules/TawasulAdmissions/admissions_manage.php
 * @covers modules/TawasulAdmissions/admissions_manage_edit.php
 * @covers modules/TawasulAdmissions/admissions_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage admissions accounts');
$I->loginAsAdmin();
$I->amOnModulePage('Admissions', 'admissions_manage.php');

// Note: This action does not have an add page, so we'll test edit and delete
// on an existing admissions account. You may need to create test data first.

$tawasulAdmissionsAccountID = $I->haveInDatabase('tawasulAdmissionsAccount', ['email' => 'test.admissions@example.com']);

// Edit ------------------------------------------------
$I->amOnModulePage('Admissions', 'admissions_manage_edit.php', array('tawasulAdmissionsAccountID' => $tawasulAdmissionsAccountID));
$I->seeBreadcrumb('Edit Account');

$I->seeInField('email', 'test.admissions@example.com');

$formValues = array(
    'email' => 'test2.admissions@example.com',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');

// Delete ------------------------------------------------
$I->amOnModulePage('Admissions', 'admissions_manage_delete.php', array('tawasulAdmissionsAccountID' => $tawasulAdmissionsAccountID));

$I->click('Delete');
$I->see('Your request was completed successfully.', '.success');
