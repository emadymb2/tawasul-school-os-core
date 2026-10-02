<?php
/**
 * @covers modules/TawasulDepartments/department.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view a specific department');
$I->loginAsAdmin();

// Get a department ID from the database
$tawasulDepartmentID = $I->grabFromDatabase('tawasulDepartment', 'tawasulDepartmentID', []);

$I->amOnModulePage('Departments', 'department.php', ['tawasulDepartmentID' => $tawasulDepartmentID]);
$I->seeBreadcrumb('Departments');
$I->see('Staff', 'h2');
