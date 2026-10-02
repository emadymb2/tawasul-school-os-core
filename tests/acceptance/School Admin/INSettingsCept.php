<?php 
/**
 * @covers modules/TawasulSchoolAdmin/inSettings.php
 * @covers modules/TawasulSchoolAdmin/inSettings_add.php
 * @covers modules/TawasulSchoolAdmin/inSettings_edit.php
 * @covers modules/TawasulSchoolAdmin/inSettings_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('update Individual Needs Settings and manage descriptors');
$I->loginAsAdmin();
$I->amOnModulePage('School Admin', 'inSettings.php');

// Grab Original Settings --------------------------------------

$originalFormValues = $I->grabAllFormValues();
$I->seeInFormFields('#content form', $originalFormValues);

// Make Changes ------------------------------------------------

$newFormValues = array(
    'targetsTemplate'            => '<div>Targets Test</div>',
    'teachingStrategiesTemplate' => '<span>Strategies Test</span>',
    'notesReviewTemplate'        => '<p>Notes Test</p>',
);

$I->submitForm('#content form', $newFormValues, 'Submit');

// Verify Results ----------------------------------------------

$I->see('Your request was completed successfully.', '.success');
$I->seeInFormFields('#content form', $newFormValues);

// Restore Original Settings -----------------------------------

$I->submitForm('#content form', $originalFormValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');
$I->seeInFormFields('#content form', $originalFormValues);

// Add IN Descriptor --------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Descriptor');

$addFormValues = array(
    'name'           => 'Test Descriptor',
    'nameShort'      => 'TD',
    'sequenceNumber' => '99',
    'description'    => 'Test descriptor description',
);

$I->submitForm('#content form', $addFormValues, 'Submit');
$I->seeSuccessMessage();

$tawasulINDescriptorID = $I->grabEditIDFromURL();

// Edit IN Descriptor -------------------------------------------
$I->amOnModulePage('School Admin', 'inSettings_edit.php', array(
    'tawasulINDescriptorID' => $tawasulINDescriptorID
));
$I->seeBreadcrumb('Edit Descriptor');

$I->seeInField('name', 'Test Descriptor');

$editFormValues = array(
    'name'           => 'Updated Descriptor',
    'nameShort'      => 'UD',
    'sequenceNumber' => '98',
    'description'    => 'Updated description',
);

$I->submitForm('#content form', $editFormValues, 'Submit');
$I->seeSuccessMessage();

// Delete IN Descriptor -----------------------------------------
$I->amOnModulePage('School Admin', 'inSettings_delete.php', array(
    'tawasulINDescriptorID' => $tawasulINDescriptorID
));

$I->click('Delete');
$I->seeSuccessMessage();

