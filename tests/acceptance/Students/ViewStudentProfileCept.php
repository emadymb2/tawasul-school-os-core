<?php
/**
 * @covers modules/TawasulStudents/student_view.php
 * @covers modules/TawasulStudents/student_view_details.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View student profile');
$I->loginAsAdmin();

$I->amOnModulePage('Students', 'student_view.php');
$I->seeBreadcrumb('View Student Profiles');

// Database Seed  ------------------------------

$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['username' => 'testingstudent']);
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);
$tawasulFormGroupID = $I->grabFromDatabase('tawasulFormGroup', 'tawasulFormGroupID');
$tawasulYearGroupID = $I->grabFromDatabase('tawasulYearGroup', 'tawasulYearGroupID');

$tawasulStudentEnrolmentID = $I->haveInDatabase('tawasulStudentEnrolment', [
    'tawasulPersonID'     => $tawasulPersonID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulFormGroupID'  => $tawasulFormGroupID,
    'tawasulYearGroupID'  => $tawasulYearGroupID,
]);


// Test View Page  ------------------------------

$I->amOnModulePage('Students', 'student_view_details.php', [
    'tawasulPersonID' => $tawasulPersonID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID
]);
$I->seeBreadcrumb('View Student Profiles');

// Database Cleanup  ------------------------------

$I->deleteFromDatabase('tawasulStudentEnrolment', ['tawasulStudentEnrolmentID' => $tawasulStudentEnrolmentID]);
