<?php
/**
 * @covers modules/TawasulSchoolAdmin/formGroup_manage.php
 * @covers modules/TawasulSchoolAdmin/formGroup_manage_add.php
 * @covers modules/TawasulSchoolAdmin/formGroup_manage_edit.php
 * @covers modules/TawasulSchoolAdmin/formGroup_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add, edit and delete form groups');
$I->loginAsAdmin();
$I->amOnModulePage('School Admin', 'formGroup_manage.php');

// Add ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add');

$addFormValues = array(
    'name'       => 'Test 1',
    'nameShort'  => 'TR1',
    'attendance' => 'Y',
    'website'    => 'http://testing.test',
);

$I->selectFromDropdown('tawasulPersonIDTutor', 2);
$I->selectFromDropdown('tawasulPersonIDTutor2', 3);
$I->selectFromDropdown('tawasulPersonIDTutor3', 4);

$I->selectFromDropdown('tawasulPersonIDEA', -2);
$I->selectFromDropdown('tawasulPersonIDEA2', -3);
$I->selectFromDropdown('tawasulPersonIDEA3', -4);

$I->selectFromDropdown('tawasulSpaceID', 2);

$I->submitForm('#content form', $addFormValues, 'Submit');
$I->seeSuccessMessage();

$tawasulSchoolYearID = $I->grabValueFromURL('tawasulSchoolYearID');
$tawasulFormGroupID = $I->grabEditIDFromURL();

// Edit ------------------------------------------------
$I->amOnModulePage('School Admin', 'formGroup_manage_edit.php', array('tawasulFormGroupID' => $tawasulFormGroupID, 'tawasulSchoolYearID' => $tawasulSchoolYearID));
$I->seeBreadcrumb('Edit');

$I->seeInFormFields('#content form', $addFormValues);

$editFormValues = array(
    'name'       => 'Test 2',
    'nameShort'  => 'TR2',
    'attendance' => 'N',
    'website'    => '',
);

$I->submitForm('#content form', $editFormValues, 'Submit');
$I->seeSuccessMessage();

// Delete ------------------------------------------------
$I->amOnModulePage('School Admin', 'formGroup_manage_delete.php', array('tawasulFormGroupID' => $tawasulFormGroupID, 'tawasulSchoolYearID' => $tawasulSchoolYearID));

$I->click('Delete');
$I->seeSuccessMessage();
