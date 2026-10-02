<?php
/**
 * @covers modules/TawasulStudents/report_students_IDCards.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check Student ID Cards');
$I->loginAsAdmin();

$I->amOnModulePage('Students', 'report_students_IDCards.php');
$I->seeBreadcrumb('Student ID Cards');

// Basic Check -----------------------------------------

$I->dontSeeErrors();

// File Upload Check ------------------------------------
$tawasulPersonID = $I->grabFromDatabase('tawasulStudentEnrolment', 'tawasulPersonID', ['tawasulSchoolYearID' => $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current'])]);

$I->amOnModulePage('Students', 'report_students_IDCards.php');
$I->attachFile('file', 'attachment.jpg');
$I->submitForm('#content form', ['tawasulPersonID' => [$tawasulPersonID]], 'Search');
$I->dontSeeErrors();
