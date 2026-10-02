<?php
/**
 * @covers modules/TawasulReports/templates_preview.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check templates preview page');
$I->loginAsAdmin();

// Create test template
$tawasulReportTemplateID = $I->haveInDatabase('tawasulReportTemplate', [
    'name' => 'Test Template',
    'context' => 'Student Enrolment',
]);

// Templates Preview -----------------------------------------

$I->amOnModulePage('Reports', 'templates_preview.php', [
    'tawasulReportTemplateID' => $tawasulReportTemplateID,
]);
$I->seeBreadcrumb('Preview');
$I->dontSeeErrors();

// Clean up test data ----------------------------------------

$I->deleteFromDatabase('tawasulReportTemplate', ['tawasulReportTemplateID' => $tawasulReportTemplateID]);
