<?php
/**
 * @covers modules/TawasulStaff/substitutes_manage.php
 * @covers modules/TawasulStaff/substitutes_manage_add.php
 * @covers modules/TawasulStaff/substitutes_manage_edit.php
 * @covers modules/TawasulStaff/substitutes_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add, edit and delete a substitute');
$I->loginAsAdmin();
$I->amOnModulePage('Staff', 'substitutes_manage.php');
$I->seeBreadcrumb('Manage Substitutes');

// Add ------------------------------------------------
$I->click('Add', 'a');
$I->seeBreadcrumb('Add');

$I->selectFromDropdown('tawasulPersonID', 2);

$formValues = array(
    'active' => 'Y',
    'type' => 'Internal Substitute',
    'details' => 'Test substitute details',
    'priority' => '1',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$tawasulSubstituteID = $I->grabEditIDFromURL();

// Edit ------------------------------------------------
$I->amOnModulePage('Staff', 'substitutes_manage_edit.php', array(
    'tawasulSubstituteID' => $tawasulSubstituteID
));
$I->seeBreadcrumb('Edit');

$I->seeInFormFields('#content form', array(
    'type' => 'Internal Substitute',
));

$formValues = array(
    'type' => 'External Substitute',
    'details' => 'Updated substitute details',
    'priority' => '2',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

// Delete ------------------------------------------------
$I->amOnModulePage('Staff', 'substitutes_manage_delete.php', array(
    'tawasulSubstituteID' => $tawasulSubstituteID
));

$I->click('Delete');
$I->seeSuccessMessage();
