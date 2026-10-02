<?php
/**
 * @covers modules/TawasulStaff/coverage_request.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('Request coverage');
$I->loginAsAdmin();

// Get an existing staff absence
$tawasulStaffAbsenceID = $I->grabFromDatabase('tawasulStaffAbsence', 'tawasulStaffAbsenceID', []);

$I->amOnModulePage('Staff', 'coverage_request.php', ['tawasulStaffAbsenceID' => $tawasulStaffAbsenceID]);

