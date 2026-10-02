<?php
/**
 * @covers modules/TawasulStaff/staff_manage.php
 * @covers modules/TawasulStaff/staff_manage_add.php
 * @covers modules/TawasulStaff/staff_manage_edit.php
 * @covers modules/TawasulStaff/staff_manage_delete.php
 * @covers modules/TawasulStaff/staff_manage_edit_contract_add.php
 * @covers modules/TawasulStaff/staff_manage_edit_contract_edit.php
 * @covers modules/TawasulStaff/staff_manage_edit_facility_add.php
 * @covers modules/TawasulStaff/staff_manage_edit_facility_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage staff with full CRUD and nested contract/facility management');
$I->loginAsAdmin();
$I->amOnModulePage('Staff', 'staff_manage.php');
$I->seeBreadcrumb('Manage Staff');

// Basic Check -----------------------------------------

$I->dontSeeErrors();

// Search Test -----------------------------------------

$I->fillField('search', 'test');
$I->submitForm('#searchForm', []);
$I->dontSeeErrors();

// Add Staff -------------------------------------------

$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Staff');

$I->selectFromDropdown('tawasulPersonID', 1);
$I->selectFromDropdown('type', 1);
$I->fillField('jobTitle', 'Test Teacher');

$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

$tawasulStaffID = $I->grabEditIDFromURL();


// Edit Staff ------------------------------------------

$I->amOnModulePage('Staff', 'staff_manage_edit.php', ['tawasulStaffID' => $tawasulStaffID]);
$I->seeBreadcrumb('Edit Staff');

$I->seeInField('jobTitle', 'Test Teacher');
$I->fillField('jobTitle', 'Updated Test Teacher');

$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

// Add Contract ----------------------------------------

$I->amOnModulePage('Staff', 'staff_manage_edit_contract_add.php', ['tawasulStaffID' => $tawasulStaffID]);
$I->seeBreadcrumb('Add Contract');

$I->fillField('title', 'Test Contract');
$I->selectFromDropdown('status', 1);
$I->fillField('dateStart', date('Y-m-d'));

$I->attachFile('file1', 'attachment.txt');
$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

$tawasulStaffContractID = $I->grabEditIDFromURL();
$file = $I->grabFromDatabase('tawasulStaffContract', 'contractUpload', ['tawasulStaffContractID' => $tawasulStaffContractID]);
$I->assertNotEmpty($file);

// Edit Contract ---------------------------------------

$I->amOnModulePage('Staff', 'staff_manage_edit_contract_edit.php', [
    'tawasulStaffID' => $tawasulStaffID,
    'tawasulStaffContractID' => $tawasulStaffContractID
]);
$I->seeBreadcrumb('Edit');

$I->fillField('title', 'Updated Test Contract');
$I->fillField('contractUpload', '');
$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

$tawasulStaffContractID = $I->grabValueFromURL('tawasulStaffContractID');
$I->seeInDatabase('tawasulStaffContract', ['tawasulStaffContractID' => $tawasulStaffContractID, 'contractUpload' => '']);

// Edit Contract - File Upload -------------------------

$I->amOnModulePage('Staff', 'staff_manage_edit_contract_edit.php', [
    'tawasulStaffID' => $tawasulStaffID,
    'tawasulStaffContractID' => $tawasulStaffContractID
]);

$I->fillField('title', 'Updated Test Contract');
$I->attachFile('file1', 'attachment.txt');
$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

$file2 = $I->grabFromDatabase('tawasulStaffContract', 'contractUpload', ['tawasulStaffContractID' => $tawasulStaffContractID]);
$I->assertNotEmpty($file2);

// Add Facility ----------------------------------------

$tawasulPersonID = $I->grabFromDatabase('tawasulStaff', 'tawasulPersonID', ['tawasulStaffID' => $tawasulStaffID]);

$I->amOnModulePage('Staff', 'staff_manage_edit_facility_add.php', ['tawasulStaffID' => $tawasulStaffID, 'tawasulPersonID' => $tawasulPersonID]);

$I->seeBreadcrumb('Add Facility');

$I->selectFromDropdown('tawasulSpaceID', 1);

$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

$tawasulSpacePersonID = $I->grabEditIDFromURL();

// Delete Facility -------------------------------------

$I->amOnModulePage('Staff', 'staff_manage_edit_facility_delete.php', [
    'tawasulStaffID' => $tawasulStaffID,
    'tawasulSpacePersonID' => $tawasulSpacePersonID
]);

$I->click('Delete');
$I->seeSuccessMessage();

// Delete Staff ----------------------------------------

$I->amOnModulePage('Staff', 'staff_manage_delete.php', ['tawasulStaffID' => $tawasulStaffID]);

$I->click('Delete');
$I->seeSuccessMessage();
