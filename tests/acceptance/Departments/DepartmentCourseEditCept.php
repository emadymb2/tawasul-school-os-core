<?php
/**
 * @covers modules/TawasulDepartments/department_course_edit.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('edit a department course');
$I->loginAsAdmin();

// Get admin person ID
$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['username' => 'testingadmin']);

// Try to find a department where admin is already a coordinator
$tawasulDepartmentID = $I->grabFromDatabase('tawasulDepartmentStaff', 'tawasulDepartmentID', [
    'tawasulPersonID' => $tawasulPersonID
]);

// If no department found, get any department and add admin as coordinator
if (empty($tawasulDepartmentID)) {
    $tawasulDepartmentID = $I->grabFromDatabase('tawasulDepartment', 'tawasulDepartmentID', []);
    
    // Insert directly using SQL to ensure it's committed
    $I->haveInDatabase('tawasulDepartmentStaff', [
        'tawasulDepartmentID' => $tawasulDepartmentID,
        'tawasulPersonID' => $tawasulPersonID,
        'role' => 'Coordinator',
    ]);
    
    // Force a page reload to ensure the database change is visible
    $I->amOnModulePage('Departments', 'departments.php');
}

// Get a course associated with this department
$tawasulCourseID = $I->grabFromDatabase('tawasulCourse', 'tawasulCourseID', [
    'tawasulDepartmentID' => $tawasulDepartmentID
]);

$I->amOnModulePage('Departments', 'department_course_edit.php', [
    'tawasulDepartmentID' => $tawasulDepartmentID,
    'tawasulCourseID' => $tawasulCourseID
]);
$I->seeBreadcrumb('Edit Course');

// Basic Check -----------------------------------------

$I->dontSeeErrors();

// Grab original values
$originalFormValues = $I->grabAllFormValues('#content form');

// Verify original values are displayed
$I->seeInFormFields('#content form', $originalFormValues);

// Edit Course Description -----------------------------

$formValues = [
    'description' => '<p>Updated course description for testing purposes.</p>',
];

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

// Restore original values -----------------------------

$I->amOnModulePage('Departments', 'department_course_edit.php', [
    'tawasulDepartmentID' => $tawasulDepartmentID,
    'tawasulCourseID' => $tawasulCourseID
]);

$I->submitForm('#content form', $originalFormValues, 'Submit');
$I->seeSuccessMessage();

// Clean up --------------------------------------------

$I->amOnModulePage('Departments', 'department_course.php', [
    'tawasulDepartmentID' => $tawasulDepartmentID,
    'tawasulCourseID' => $tawasulCourseID
]);
