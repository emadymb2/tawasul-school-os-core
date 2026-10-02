<?php
/**
 * @covers modules/TawasulReports/templates_manage.php
 * @covers modules/TawasulReports/templates_manage_add.php
 * @covers modules/TawasulReports/templates_manage_duplicate.php
 * @covers modules/TawasulReports/templates_manage_edit.php
 * @covers modules/TawasulReports/templates_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('Use template builder with full CRUD operations');
$I->loginAsAdmin();
$I->amOnModulePage('Reports', 'templates_manage.php');
$I->seeBreadcrumb('Template Builder');

// Add a new template
$I->click('Add', 'a');
$I->seeBreadcrumb('Add');
$I->fillField('name', 'Test Template');
$I->selectFromDropdown('context', 1);
$I->selectFromDropdown('flags', 1);
$I->selectFromDropdown('orientation', 1);
$I->selectFromDropdown('pageSize', 1);
$I->click('Submit');
$I->seeSuccessMessage();

// Edit the template
$tawasulReportTemplateID = $I->grabEditIDFromURL();
$I->amOnModulePage('Reports', 'templates_manage_edit.php', ['tawasulReportTemplateID' => $tawasulReportTemplateID]);
$I->seeBreadcrumb('Edit');
$I->seeInField('name', 'Test Template');
$I->fillField('name', 'Updated Template');
$I->fillField('active', 'N');
$I->click('Submit');
$I->seeSuccessMessage();

// Delete the template
$I->amOnModulePage('Reports', 'templates_manage_delete.php', ['tawasulReportTemplateID' => $tawasulReportTemplateID]);
$I->click('Delete');
$I->seeSuccessMessage();

// Test Duplicate Template -----------------------------------

// Create a new template to duplicate
$tawasulReportTemplateID = $I->haveInDatabase('tawasulReportTemplate', [
    'name' => 'Template to Duplicate',
    'context' => 'Student Enrolment',
]);

$I->amOnModulePage('Reports', 'templates_manage_duplicate.php', [
    'tawasulReportTemplateID' => $tawasulReportTemplateID,
]);
$I->seeBreadcrumb('Duplicate');
$I->dontSeeErrors();

// Clean up
$I->deleteFromDatabase('tawasulReportTemplate', ['tawasulReportTemplateID' => $tawasulReportTemplateID]);
