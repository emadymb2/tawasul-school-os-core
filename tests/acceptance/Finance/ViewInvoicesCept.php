<?php
/**
 * @covers modules/TawasulFinance/invoices_view.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view invoices');
$I->loginAsParent();

$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

$I->amOnModulePage('Finance', 'invoices_view.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);
$I->seeBreadcrumb('View Invoices');
