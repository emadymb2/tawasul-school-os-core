<?php
/**
 * @covers modules/TawasulStaff/coverage_view.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View coverage');
$I->loginAsAdmin();

// Get an existing staff coverage
$tawasulStaffCoverageID = $I->grabFromDatabase('tawasulStaffCoverage', 'tawasulStaffCoverageID', []);

$I->amOnModulePage('Staff', 'coverage_view.php', ['tawasulStaffCoverageID' => $tawasulStaffCoverageID]);
$I->seeBreadcrumb('Open Requests');
