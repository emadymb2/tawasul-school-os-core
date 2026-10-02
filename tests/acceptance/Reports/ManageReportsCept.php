<?php
/**
 * @covers modules/TawasulReports/reports_manage.php
 * @covers modules/TawasulReports/reports_manage_add.php
 * @covers modules/TawasulReports/reports_manage_edit.php
 * @covers modules/TawasulReports/reports_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('Manage reports with full CRUD operations');
$I->loginAsAdmin();
$I->amOnModulePage('Reports', 'reports_manage.php');
$I->seeBreadcrumb('Manage Reports');

$tawasulReportTemplateID = $I->haveInDatabase('tawasulReportTemplate', [
    'name' => 'Test',
    'context' => 'Student Enrolment',
    'flags' => 001,
    'orientation' => 'P',
    'pageSize' => 'A4',
]);

// Add a new report
$I->click('Add', 'a');
$I->seeBreadcrumb('Add');
$I->fillField('name', 'Test Report');
$I->selectFromDropdown('tawasulReportTemplateID', 1);
$I->selectFromDropdown('tawasulReportArchiveID', 1);
$I->click('Submit');
$I->seeSuccessMessage();

// Edit the report
$tawasulReportID = $I->grabEditIDFromURL();
$I->amOnModulePage('Reports', 'reports_manage_edit.php', ['tawasulReportID' => $tawasulReportID]);
$I->seeBreadcrumb('Edit');
$I->seeInField('name', 'Test Report');
$I->fillField('name', 'Updated Report');
$I->click('Submit');
$I->seeSuccessMessage();

// Delete the report
$I->amOnModulePage('Reports', 'reports_manage_delete.php', ['tawasulReportID' => $tawasulReportID]);
$I->click('Delete');
$I->seeSuccessMessage();

// Remove test template
$I->deleteFromDatabase('tawasulReportTemplate', ['tawasulReportTemplateID' => $tawasulReportTemplateID]);
