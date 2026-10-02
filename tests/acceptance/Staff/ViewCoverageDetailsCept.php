<?php
/**
 * @covers modules/TawasulStaff/coverage_view_details.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View coverage details');
$I->loginAsAdmin();

// Get an existing staff coverage ID
$tawasulStaffCoverageID = $I->grabFromDatabase('tawasulStaffCoverage', 'tawasulStaffCoverageID', []);

$I->amOnModulePage('Staff', 'coverage_view_details.php', ['tawasulStaffCoverageID' => $tawasulStaffCoverageID]);
$I->seeBreadcrumb('Coverage');
