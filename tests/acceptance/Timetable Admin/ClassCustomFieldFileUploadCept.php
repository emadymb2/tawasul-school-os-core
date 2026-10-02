<?php
/**
 * @covers modules/TawasulTimetableAdmin/course_manage_class_add.php
 * @covers modules/TawasulTimetableAdmin/course_manage_class_addProcess.php
 * @covers src/Forms/CustomFieldHandler.php
 * @covers src/Forms/Input/CustomField.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('test file upload on a custom field for a class');
$I->loginAsAdmin();

// Create a custom field of type 'file' for Class context
$tawasulCustomFieldID = $I->haveInDatabase('tawasulCustomField', [
    'context'               => 'Class',
    'name'                  => 'Test Class File Upload',
    'active'                => 'Y',
    'description'           => 'Acceptance test file upload custom field',
    'type'                  => 'file',
    'options'               => '',
    'required'              => 'N',
    'hidden'                => 'N',
    'heading'               => 'Basic Details',
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

// First create a course to add a class to
$I->amOnModulePage('Timetable Admin', 'course_manage.php');
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Course');

$I->selectFromDropdown('tawasulDepartmentID', 1);
$I->submitForm('#content form', [
    'name'      => 'Class CF Test Course',
    'nameShort' => 'CLCF',
    'orderBy'   => '1',
], 'Submit');
$I->seeSuccessMessage();

$tawasulCourseID = $I->grabEditIDFromURL();
$tawasulSchoolYearID = $I->grabValueFromURL('tawasulSchoolYearID');

// Add a class with the custom field file upload
$I->amOnModulePage('Timetable Admin', 'course_manage_class_add.php', [
    'tawasulCourseID'     => $tawasulCourseID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
]);
$I->seeBreadcrumb('Add Class');

// Verify the custom field file input is present
$I->seeElement('input[type="file"][name="'.$fieldName.'"]');

$I->attachFile('input[type="file"][name="'.$fieldName.'"]', 'attachment.txt');
$I->submitForm('#content form', [
    'name'       => 'CF-1',
    'nameShort'  => 'CF-1',
    'reportable' => 'Y',
    'attendance' => 'Y',
], 'Submit');
$I->seeSuccessMessage();

$tawasulCourseClassID = $I->grabEditIDFromURL();

// Verify the uploaded file path is stored in the fields JSON column
$fieldsJson = $I->grabFromDatabase('tawasulCourseClass', 'fields', ['tawasulCourseClassID' => $tawasulCourseClassID]);
$fields = json_decode($fieldsJson, true);
$I->assertNotEmpty($fields[$fieldID] ?? '', 'Custom field file upload path should be stored in fields JSON');

$file = $fields[$fieldID];

// Cleanup - delete class then course
$I->amOnModulePage('Timetable Admin', 'course_manage_class_delete.php', [
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulCourseID'      => $tawasulCourseID,
    'tawasulSchoolYearID'  => $tawasulSchoolYearID,
]);
$I->click('Delete');
$I->seeSuccessMessage();

$I->amOnModulePage('Timetable Admin', 'course_manage_delete.php', [
    'tawasulCourseID'     => $tawasulCourseID,
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'search'             => '',
]);
$I->click('Delete');
$I->seeSuccessMessage();

$I->deleteFromDatabase('tawasulCustomField', ['tawasulCustomFieldID' => $tawasulCustomFieldID]);

if (!empty($file)) {
    $I->deleteFile('../'.$file);
}
