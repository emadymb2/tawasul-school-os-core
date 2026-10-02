<?php
/**
 * @covers modules/TawasulReports/templates_assets_fonts_edit.php
 * @covers modules/TawasulReports/templates_assets_fonts_preview.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check templates assets fonts management');
$I->loginAsAdmin();

// Create test font
$tawasulReportTemplateFontID = $I->haveInDatabase('tawasulReportTemplateFont', [
    'fontName' => 'Test Font',
    'fontFamily' => 'TestFont',
    'fontType' => 'R',
]);

// Templates Assets Fonts Edit -------------------------------

$I->amOnModulePage('Reports', 'templates_assets_fonts_edit.php', [
    'tawasulReportTemplateFontID' => $tawasulReportTemplateFontID,
]);
$I->seeBreadcrumb('Edit');
$I->dontSeeErrors();

// Templates Assets Fonts Preview ----------------------------

$I->amOnModulePage('Reports', 'templates_assets_fonts_preview.php', [
    'tawasulReportTemplateFontID' => $tawasulReportTemplateFontID,
]);
$I->dontSeeErrors();

// Clean up test data ----------------------------------------

$I->deleteFromDatabase('tawasulReportTemplateFont', ['tawasulReportTemplateFontID' => $tawasulReportTemplateFontID]);
