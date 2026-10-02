<?php
/**
 * @covers modules/TawasulAdmissions/studentEnrolment_manage.php
 * @covers modules/TawasulAdmissions/studentEnrolment_manage_add.php
 * @covers modules/TawasulAdmissions/studentEnrolment_manage_edit.php
 * @covers modules/TawasulAdmissions/studentEnrolment_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add, edit and delete a student enrolment');
$I->loginAsAdmin();
$I->amOnModulePage('Admissions', 'studentEnrolment_manage.php');

// Add ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Student Enrolment');

$tawasulSchoolYearID = $I->grabValueFromURL('tawasulSchoolYearID');

$I->selectFromDropdown('tawasulYearGroupID', 1);
$I->selectFromDropdown('tawasulFormGroupID', 1);

$formValues = array(
    'tawasulPersonID'   => '0000002775',
    'rollOrder'        => '1',
    'autoEnrolStudent' => 'N',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');

$tawasulStudentEnrolmentID = $I->grabEditIDFromURL();

// Edit ------------------------------------------------
$I->amOnModulePage('Admissions', 'studentEnrolment_manage_edit.php', array('tawasulStudentEnrolmentID' => $tawasulStudentEnrolmentID, 'tawasulSchoolYearID' => $tawasulSchoolYearID));
$I->seeBreadcrumb('Edit Student Enrolment');

$I->seeInFormFields('#content form', array(
    'rollOrder' => '1',
));

$I->selectFromDropdown('tawasulYearGroupID', 2);
$I->selectFromDropdown('tawasulFormGroupID', 2);

$formValues = array(
    'rollOrder'           => '2',
    'autoEnrolStudent'    => 'Y',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->see('Your request was completed successfully.', '.success');

// Delete ------------------------------------------------
$I->amOnModulePage('Admissions', 'studentEnrolment_manage_delete.php', array('tawasulStudentEnrolmentID' => $tawasulStudentEnrolmentID, 'tawasulSchoolYearID' => $tawasulSchoolYearID));

$I->click('Delete');
$I->see('Your request was completed successfully.', '.success');
