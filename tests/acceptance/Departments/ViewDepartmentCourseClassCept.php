<?php
/**
 * @covers modules/TawasulDepartments/department_course_class.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View department course class');
$I->loginAsAdmin();

// Get a department, course, and class
$tawasulDepartmentID = $I->grabFromDatabase('tawasulDepartment', 'tawasulDepartmentID', []);
$tawasulCourseID = $I->grabFromDatabase('tawasulCourse', 'tawasulCourseID', ['tawasulDepartmentID' => $tawasulDepartmentID]);
$tawasulCourseClassID = $I->grabFromDatabase('tawasulCourseClass', 'tawasulCourseClassID', ['tawasulCourseID' => $tawasulCourseID]);

$I->amOnModulePage('Departments', 'department_course_class.php', [
    'tawasulDepartmentID' => $tawasulDepartmentID,
    'tawasulCourseID' => $tawasulCourseID,
    'tawasulCourseClassID' => $tawasulCourseClassID
]);
$I->dontSeeErrors();
