<?php
/**
 * @covers modules/TawasulStaff/absences_view_details.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View absence details');
$I->loginAsAdmin();

// Get an existing staff absence
$tawasulStaffAbsenceID = $I->grabFromDatabase('tawasulStaffAbsence', 'tawasulStaffAbsenceID', []);

$I->amOnModulePage('Staff', 'absences_view_details.php', ['tawasulStaffAbsenceID' => $tawasulStaffAbsenceID]);
