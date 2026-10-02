<?php
/**
 * @covers modules/TawasulSchoolAdmin/externalAssessments_manage.php
 * @covers modules/TawasulSchoolAdmin/externalAssessments_manage_add.php
 * @covers modules/TawasulSchoolAdmin/externalAssessments_manage_edit.php
 * @covers modules/TawasulSchoolAdmin/externalAssessments_manage_edit_field_add.php
 * @covers modules/TawasulSchoolAdmin/externalAssessments_manage_edit_field_edit.php
 * @covers modules/TawasulSchoolAdmin/externalAssessments_manage_edit_field_delete.php
 * @covers modules/TawasulSchoolAdmin/externalAssessments_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add, edit and delete something');
$I->loginAsAdmin();
$I->amOnModulePage('School Admin', 'externalAssessments_manage.php');

// Add ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add External Assessment');

$addFormValues = array(
    'name'            => 'Test Assessment 1',
    'nameShort'       => 'TASS1',
    'description'     => 'For testing.',
    'active'          => 'Y',
    'allowFileUpload' => 'Y',
);

$I->submitForm('#content form', $addFormValues, 'Submit');
$I->seeSuccessMessage();

$tawasulExternalAssessmentID = $I->grabEditIDFromURL();

// Edit ------------------------------------------------
$I->amOnModulePage('School Admin', 'externalAssessments_manage_edit.php', array('tawasulExternalAssessmentID' => $tawasulExternalAssessmentID));
$I->seeBreadcrumb('Edit External Assessment');

$I->seeInFormFields('#content form', $addFormValues);

$editFormValues = array(
    'name'            => 'Test Assessment 2',
    'nameShort'       => 'TASS2',
    'description'     => 'Also for testing.',
    'active'          => 'N',
    'allowFileUpload' => 'N',
);

$I->submitForm('#content form', $editFormValues, 'Submit');
$I->seeSuccessMessage();

// Add Field --------------------------------------------
$I->amOnModulePage('School Admin', 'externalAssessments_manage_edit_field_add.php', array('tawasulExternalAssessmentID' => $tawasulExternalAssessmentID));
$I->seeBreadcrumb('Add Field');

$addFormValues = array(
    'name'     => 'Test Field 1',
    'category' => 'Test',
    'order'    => '1',
);
$I->selectFromDropdown('tawasulScaleID', 2);

$I->submitForm('#content form', $addFormValues, 'Submit');
$I->seeSuccessMessage();

$tawasulExternalAssessmentFieldID = $I->grabEditIDFromURL();

// Edit Field -----------------------------------------------
$I->amOnModulePage('School Admin', 'externalAssessments_manage_edit_field_edit.php', array(
    'tawasulExternalAssessmentID' => $tawasulExternalAssessmentID,
    'tawasulExternalAssessmentFieldID' => $tawasulExternalAssessmentFieldID
));
$I->seeBreadcrumb('Edit Field');

$I->seeInField('name', 'Test Field 1');

$editFieldFormValues = array(
    'name'     => 'Test Field Updated',
    'category' => 'Test Updated',
    'order'    => '2',
);

$I->submitForm('#content form', $editFieldFormValues, 'Submit');
$I->seeSuccessMessage();

// Delete Field ------------------------------------------
$I->amOnModulePage('School Admin', 'externalAssessments_manage_edit_field_delete.php', array('tawasulExternalAssessmentID' => $tawasulExternalAssessmentID, 'tawasulExternalAssessmentFieldID' => $tawasulExternalAssessmentFieldID));

$I->click('Delete');
$I->seeSuccessMessage();

// Delete ------------------------------------------------
$I->amOnModulePage('School Admin', 'externalAssessments_manage_delete.php', array('tawasulExternalAssessmentID' => $tawasulExternalAssessmentID));

$I->click('Delete');
$I->seeSuccessMessage();
