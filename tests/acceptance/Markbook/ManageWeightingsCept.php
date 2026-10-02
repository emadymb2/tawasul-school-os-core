<?php
/**
 * @covers modules/TawasulSchoolAdmin/markbookSettings.php
 * @covers modules/TawasulMarkbook/markbook_view.php
 * @covers modules/TawasulMarkbook/weighting_manage.php
 * @covers modules/TawasulMarkbook/weighting_manage_add.php
 * @covers modules/TawasulMarkbook/weighting_manage_edit.php
 * @covers modules/TawasulMarkbook/weighting_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage markbook weightings');
$I->loginAsAdmin();

// Enable Column Weighting Setting ---------------------------------
$I->amOnModulePage('School Admin', 'markbookSettings.php');
$originalMarkbookSettings = $I->grabAllFormValues();

$newMarkbookSettings = array_replace($originalMarkbookSettings, array(
    'enableColumnWeighting' => 'Y',
));

$I->submitForm('#content form', $newMarkbookSettings, 'Submit');
$I->seeSuccessMessage();

// Navigate to Markbook View
$I->amOnModulePage('Markbook', 'markbook_view.php');
$I->seeBreadcrumb('View Markbook');

$I->selectFromDropdown('tawasulCourseClassID', 2);
$I->click('Go', '#searchForm');

$tawasulCourseClassID = $I->grabValueFromURL('tawasulCourseClassID');

// Navigate to Manage Weightings
$I->amOnModulePage('Markbook', 'weighting_manage.php', array('tawasulCourseClassID' => $tawasulCourseClassID));
$I->seeBreadcrumb('Weightings');

// Add Weighting ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Weighting');

$formValues = array(
    'type'                     => 'Test Weighting',
    'description'              => 'This is a test weighting.',
    'weighting'                => '50.00',
    'calculate'                => 'term',
    'reportable'               => 'Y',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$tawasulMarkbookWeightID = $I->grabEditIDFromURL();

// Edit ------------------------------------------------
$I->amOnModulePage('Markbook', 'weighting_manage_edit.php', array(
    'tawasulMarkbookWeightID' => $tawasulMarkbookWeightID,
    'tawasulCourseClassID' => $tawasulCourseClassID
));
$I->seeBreadcrumb('Edit Weighting');

$I->seeInFormFields('#content form', array(
    'description' => 'This is a test weighting.',
    'calculate' => 'term',
));

$editFormValues = array(
    'type'                     => 'Updated Weighting',
    'description'              => 'This is an updated weighting.',
    'weighting'                => '60',
    'calculate'                => 'year',
    'reportable'               => 'N',
);

$I->submitForm('#content form', $editFormValues, 'Submit');
$I->seeSuccessMessage();

// Delete ------------------------------------------------
$I->amOnModulePage('Markbook', 'weighting_manage_delete.php', array(
    'tawasulMarkbookWeightID' => $tawasulMarkbookWeightID,
    'tawasulCourseClassID' => $tawasulCourseClassID
));

$I->click('Delete');
$I->seeSuccessMessage();

// Restore Original Settings -----------------------------------
$I->amOnModulePage('School Admin', 'markbookSettings.php');
$I->submitForm('#content form', $originalMarkbookSettings, 'Submit');
$I->seeSuccessMessage();

