<?php
/**
 * @covers modules/TawasulDepartments/department_course.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view a department course');
$I->loginAsAdmin();

// Get a course with a department
$tawasulDepartmentID = $I->grabFromDatabase('tawasulDepartment', 'tawasulDepartmentID', []);
$tawasulCourseID = $I->grabFromDatabase('tawasulCourse', 'tawasulCourseID', ['tawasulDepartmentID' => $tawasulDepartmentID]);

$I->amOnModulePage('Departments', 'department_course.php', [
    'tawasulDepartmentID' => $tawasulDepartmentID,
    'tawasulCourseID' => $tawasulCourseID
]);
$I->seeBreadcrumb('Departments');
$I->see('Units', 'h2');
