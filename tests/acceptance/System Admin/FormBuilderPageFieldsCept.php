<?php
/**
 * @covers modules/TawasulSystemAdmin/formBuilder_page_edit_field_add.php
 * @covers modules/TawasulSystemAdmin/formBuilder_page_edit_field_edit.php
 * @covers modules/TawasulSystemAdmin/formBuilder_page_edit_field_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check Form Builder Page Fields');
$I->loginAsAdmin();

// Get an existing form and page
$tawasulFormID = $I->grabFromDatabase('tawasulForm', 'tawasulFormID', []);
$tawasulFormPageID = $I->grabFromDatabase('tawasulFormPage', 'tawasulFormPageID', ['tawasulFormID' => $tawasulFormID]);

// Check Add Field Page --------------------------------
$I->amOnModulePage('System Admin', 'formBuilder_page_edit_field_add.php', [
    'tawasulFormID' => $tawasulFormID,
    'tawasulFormPageID' => $tawasulFormPageID,
    'fieldGroup' => 'GenericFields'
]);
$I->dontSeeErrors();

// Get an existing field
$tawasulFormFieldID = $I->grabFromDatabase('tawasulFormField', 'tawasulFormFieldID', ['tawasulFormPageID' => $tawasulFormPageID]);

// Check Edit Field Page -------------------------------
$I->amOnModulePage('System Admin', 'formBuilder_page_edit_field_edit.php', [
    'tawasulFormID' => $tawasulFormID,
    'tawasulFormPageID' => $tawasulFormPageID,
    'tawasulFormFieldID' => $tawasulFormFieldID
]);
$I->seeBreadcrumb('Edit Field');
$I->dontSeeErrors();

// Check Delete Field Page -----------------------------
$I->amOnModulePage('System Admin', 'formBuilder_page_edit_field_delete.php', [
    'tawasulFormID' => $tawasulFormID,
    'tawasulFormPageID' => $tawasulFormPageID,
    'tawasulFormFieldID' => $tawasulFormFieldID
]);
$I->dontSeeErrors();
