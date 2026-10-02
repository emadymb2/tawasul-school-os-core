<?php
/**
 * @covers modules/TawasulSystemAdmin/module_manage_uninstall.php
 * @covers modules/TawasulSystemAdmin/module_manage.php
 * @covers modules/TawasulSystemAdmin/module_manage_edit.php
 * @covers modules/TawasulSystemAdmin/module_manage_update.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check Module Management');
$I->loginAsAdmin();

// Test Module Manage Page -----------------------------

$I->amOnModulePage('System Admin', 'module_manage.php');
$I->seeBreadcrumb('Manage Modules');
$I->dontSeeErrors();

// Get a module ID
$tawasulModuleID = $I->grabFromDatabase('tawasulModule', 'tawasulModuleID', ['type' => 'Core']);

// Test Module Edit Page -------------------------------

$I->amOnModulePage('System Admin', 'module_manage_edit.php', [
    'tawasulModuleID' => $tawasulModuleID,
]);
$I->seeBreadcrumb('Edit Module');
$I->dontSeeErrors();

// Test Module Update Page -----------------------------

$I->amOnModulePage('System Admin', 'module_manage_update.php', [
    'tawasulModuleID' => $tawasulModuleID,
]);
$I->dontSeeErrors();

// Test Uninstall Module Page --------------------------

$tawasulModuleID = $I->grabFromDatabase('tawasulModule', 'tawasulModuleID', ['type' => 'Additional']);

$I->amOnModulePage('System Admin', 'module_manage_uninstall.php', [
    'tawasulModuleID' => $tawasulModuleID,
]);
$I->seeBreadcrumb('Uninstall Module');
$I->dontSeeErrors();
