<?php
/**
 * @covers modules/TawasulTimetable/studentEnrolment_manage.php
 * @covers modules/TawasulTimetable/studentEnrolment_manage_edit.php
 * @covers modules/TawasulTimetable/studentEnrolment_manage_edit_edit.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage student enrolment in a course class');
$I->loginAsAdmin();

$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['username' => 'testingadmin']);
$tawasulActionID = $I->grabFromDatabase('tawasulAction', 'tawasulActionID', ['name' => 'Manage Student Enrolment']);
$tawasulPermissionID = $I->haveInDatabase('tawasulPermission', ['tawasulRoleID' => 1, 'tawasulActionID' => $tawasulActionID]);

$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

// Get a department
$tawasulDepartmentID = $I->grabFromDatabase('tawasulDepartment', 'tawasulDepartmentID', ['type' => 'Learning Area']);

$I->haveInDatabase('tawasulDepartmentStaff', [
    'tawasulDepartmentID' => $tawasulDepartmentID,
    'tawasulPersonID' => $tawasulPersonID,
    'role' => 'Coordinator',
]);

// Get a course in that department
$tawasulCourseID = $I->grabFromDatabase('tawasulCourse', 'tawasulCourseID', [
    'tawasulDepartmentID' => $tawasulDepartmentID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID
]);

// Get a class in that course
$tawasulCourseClassID = $I->grabFromDatabase('tawasulCourseClass', 'tawasulCourseClassID', [
    'tawasulCourseID' => $tawasulCourseID
]);

// Get a student enrolment
$tawasulPersonIDStudent = $I->grabFromDatabase('tawasulCourseClassPerson', 'tawasulPersonID', [
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'role' => 'Student'
]);

// Edit ------------------------------------------------
$I->amOnModulePage('Timetable', 'studentEnrolment_manage_edit.php', [
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulCourseID' => $tawasulCourseID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID
]);
$I->seeBreadcrumb('Edit');
$I->dontSeeErrors();

// Test nested Edit action (if student enrolment exists)
if ($tawasulPersonIDStudent) {
    $I->amOnModulePage('Timetable', 'studentEnrolment_manage_edit_edit.php', [
        'tawasulPersonID' => $tawasulPersonIDStudent,
        'tawasulCourseClassID' => $tawasulCourseClassID,
        'tawasulCourseID' => $tawasulCourseID,
        'tawasulSchoolYearID' => $tawasulSchoolYearID
    ]);
    $I->seeBreadcrumb('Edit');
    $I->dontSeeErrors();
}

$I->deleteFromDatabase('tawasulPermission', ['permissionID' => $tawasulPermissionID]);
