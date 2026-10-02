<?php
/**
 * @covers modules/TawasulAdmissions/studentEnrolment_manage_edit.php
 * @covers modules/TawasulAdmissions/studentEnrolment_manage_editProcess.php
 * @covers src/Forms/CustomFieldHandler.php
 * @covers src/Forms/Input/CustomField.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('test file upload on a custom field for a student enrolment');
$I->loginAsAdmin();

// Create a custom field of type 'file' for Student Enrolment context
$tawasulCustomFieldID = $I->haveInDatabase('tawasulCustomField', [
    'context'               => 'Student Enrolment',
    'name'                  => 'Test Enrolment File Upload',
    'active'                => 'Y',
    'description'           => 'Acceptance test file upload custom field',
    'type'                  => 'file',
    'options'               => '',
    'required'              => 'N',
    'hidden'                => 'N',
    'heading'               => 'Basic Information',
    'sequenceNumber'        => 999,
    'activePersonStudent'   => 0,
    'activePersonStaff'     => 0,
    'activePersonParent'    => 0,
    'activePersonOther'     => 0,
    'activeApplicationForm' => 0,
    'activeDataUpdater'     => 0,
    'activePublicRegistration' => 0,
]);

$fieldID = str_pad($tawasulCustomFieldID, 4, '0', STR_PAD_LEFT);
$fieldName = 'custom'.$fieldID.'File';

// Grab an existing student enrolment to edit
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);
$tawasulStudentEnrolmentID = $I->grabFromDatabase('tawasulStudentEnrolment', 'tawasulStudentEnrolmentID', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);

$I->amOnModulePage('Admissions', 'studentEnrolment_manage_edit.php', [
    'tawasulStudentEnrolmentID' => $tawasulStudentEnrolmentID,
    'tawasulSchoolYearID'       => $tawasulSchoolYearID,
]);
$I->seeBreadcrumb('Edit Student Enrolment');

// Verify the custom field file input is present
$I->seeElement('input[type="file"][name="'.$fieldName.'"]');

// Upload a file to the custom field
$I->attachFile('input[type="file"][name="'.$fieldName.'"]', 'attachment.txt');
$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

// Verify the uploaded file path is stored in the fields JSON column
$fieldsJson = $I->grabFromDatabase('tawasulStudentEnrolment', 'fields', ['tawasulStudentEnrolmentID' => $tawasulStudentEnrolmentID]);
$fields = json_decode($fieldsJson, true);
$I->assertNotEmpty($fields[$fieldID] ?? '', 'Custom field file upload path should be stored in fields JSON');

$file = $fields[$fieldID];

// Cleanup
$I->deleteFromDatabase('tawasulCustomField', ['tawasulCustomFieldID' => $tawasulCustomFieldID]);

$I->updateInDatabase('tawasulStudentEnrolment', ['fields' => ''], ['tawasulStudentEnrolmentID' => $tawasulStudentEnrolmentID]);

if (!empty($file)) {
    $I->deleteFile('../'.$file);
}
