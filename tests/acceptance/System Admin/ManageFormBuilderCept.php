<?php
/**
 * @covers modules/TawasulSystemAdmin/formBuilder.php
 * @covers modules/TawasulSystemAdmin/formBuilder_add.php
 * @covers modules/TawasulSystemAdmin/formBuilder_edit.php
 * @covers modules/TawasulSystemAdmin/formBuilder_delete.php
 * @covers modules/TawasulSystemAdmin/formBuilder_duplicate.php
 * @covers modules/TawasulSystemAdmin/formBuilder_page_add.php
 * @covers modules/TawasulSystemAdmin/formBuilder_page_edit.php
 * @covers modules/TawasulSystemAdmin/formBuilder_page_delete.php
 * @covers modules/TawasulSystemAdmin/formBuilder_page_design.php
 * @covers modules/TawasulSystemAdmin/formBuilder_preview.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check Form Builder pages');
$I->loginAsAdmin();
$I->amOnModulePage('System Admin', 'formBuilder.php');
$I->seeBreadcrumb('Form Builder');

// Check Add Form Page ---------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Form');
$I->dontSeeErrors();

// Get an existing form to test other pages
$tawasulFormID = $I->grabFromDatabase('tawasulForm', 'tawasulFormID', []);

// Check Edit Form Page --------------------------------
$I->amOnModulePage('System Admin', 'formBuilder_edit.php', [
    'tawasulFormID' => $tawasulFormID
]);
$I->seeBreadcrumb('Edit Form');
$I->dontSeeErrors();

// Check Add Page --------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Page');
$I->dontSeeErrors();

// Get an existing page
$tawasulFormPageID = $I->grabFromDatabase('tawasulFormPage', 'tawasulFormPageID', ['tawasulFormID' => $tawasulFormID]);

// Check Edit Page -------------------------------------
$I->amOnModulePage('System Admin', 'formBuilder_page_edit.php', [
    'tawasulFormID' => $tawasulFormID,
    'tawasulFormPageID' => $tawasulFormPageID
]);
$I->seeBreadcrumb('Edit Page');
$I->dontSeeErrors();

// Check Design Page -----------------------------------
$I->amOnModulePage('System Admin', 'formBuilder_page_design.php', [
    'tawasulFormID' => $tawasulFormID,
    'tawasulFormPageID' => $tawasulFormPageID
]);
$I->dontSeeErrors();

// Check Preview Page ----------------------------------
$I->amOnModulePage('System Admin', 'formBuilder_preview.php', [
    'tawasulFormID' => $tawasulFormID
]);
$I->dontSeeErrors();

// Check Duplicate Page --------------------------------
$I->amOnModulePage('System Admin', 'formBuilder_duplicate.php', [
    'tawasulFormID' => $tawasulFormID
]);
$I->seeBreadcrumb('Duplicate');
$I->dontSeeErrors();

// Check Delete Page -----------------------------------
$I->amOnModulePage('System Admin', 'formBuilder_page_delete.php', [
    'tawasulFormID' => $tawasulFormID,
    'tawasulFormPageID' => $tawasulFormPageID
]);
$I->dontSeeErrors();
