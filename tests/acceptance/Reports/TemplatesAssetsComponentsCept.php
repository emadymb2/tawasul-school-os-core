<?php
/**
 * @covers modules/TawasulReports/templates_assets_components_delete.php
 * @covers modules/TawasulReports/templates_assets_components_duplicate.php
 * @covers modules/TawasulReports/templates_assets_components_edit.php
 * @covers modules/TawasulReports/templates_assets_components_help.php
 * @covers modules/TawasulReports/templates_assets_components_preview.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check templates assets components pages');
$I->loginAsAdmin();

// Create test data
$tawasulReportTemplateID = $I->haveInDatabase('tawasulReportTemplate', [
    'name' => 'Test Component Template',
    'context' => 'Student Enrolment',
]);

$tawasulReportPrototypeSectionID = $I->haveInDatabase('tawasulReportPrototypeSection', [
    'type' => 'Core',
    'name' => 'Test Component',
    'templateFile' => 'reports/misc/text.twig.html'
]);


// Templates Assets Components Edit --------------------------

$I->amOnModulePage('Reports', 'templates_assets_components_edit.php', [
    'tawasulReportPrototypeSectionID' => $tawasulReportPrototypeSectionID,
]);
$I->seeBreadcrumb('Edit');
$I->dontSeeErrors();

// Templates Assets Components Preview -----------------------

$I->amOnModulePage('Reports', 'templates_assets_components_preview.php', [
    'tawasulReportPrototypeSectionID' => $tawasulReportPrototypeSectionID,
]);
$I->dontSeeErrors();

// Templates Assets Components Help --------------------------

$I->amOnModulePage('Reports', 'templates_assets_components_help.php', [
    'tawasulReportPrototypeSectionID' => $tawasulReportPrototypeSectionID,
]);
$I->dontSeeErrors();

// Templates Assets Components Duplicate ---------------------

$I->amOnModulePage('Reports', 'templates_assets_components_duplicate.php', [
    'tawasulReportPrototypeSectionID' => $tawasulReportPrototypeSectionID,
]);
$I->seeBreadcrumb('Duplicate');
$I->dontSeeErrors();

// Templates Assets Components Delete ------------------------

$I->amOnModulePage('Reports', 'templates_assets_components_delete.php', [
    'tawasulReportPrototypeSectionID' => $tawasulReportPrototypeSectionID,
]);
$I->dontSeeErrors();

// Clean up test data ----------------------------------------

$I->deleteFromDatabase('tawasulReportPrototypeSection', ['tawasulReportPrototypeSectionID' => $tawasulReportPrototypeSectionID]);
$I->deleteFromDatabase('tawasulReportTemplate', ['tawasulReportTemplateID' => $tawasulReportTemplateID]);
