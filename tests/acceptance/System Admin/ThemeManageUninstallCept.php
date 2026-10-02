<?php
/**
 * @covers modules/TawasulSystemAdmin/theme_manage_uninstall.php
 * @covers modules/TawasulSystemAdmin/theme_manage.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check Theme Management');
$I->loginAsAdmin();

// Test Theme Manage Page ------------------------------

$I->amOnModulePage('System Admin', 'theme_manage.php');
$I->seeBreadcrumb('Manage Themes');
$I->dontSeeErrors();

// Test Uninstall Theme Page ---------------------------

// Get an inactive theme ID (we won't actually uninstall it, just check the page loads)
$tawasulThemeID = $I->grabFromDatabase('tawasulTheme', 'tawasulThemeID', ['active' => 'N']);

if ($tawasulThemeID) {
    $I->amOnModulePage('System Admin', 'theme_manage_uninstall.php', [
        'tawasulThemeID' => $tawasulThemeID,
    ]);
    $I->seeBreadcrumb('Uninstall Theme');
    $I->dontSeeErrors();
} else {
    $I->comment('No inactive theme found to test uninstall page');
}
