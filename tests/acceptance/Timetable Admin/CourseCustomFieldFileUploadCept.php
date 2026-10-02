<?php
/**
 * @covers modules/TawasulTimetableAdmin/course_manage_add.php
 * @covers modules/TawasulTimetableAdmin/course_manage_addProcess.php
 * @covers src/Forms/CustomFieldHandler.php
 * @covers src/Forms/Input/CustomField.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('test file upload on a custom field for a course');
$I->loginAsAdmin();

// Create a custom field of type 'file' for Course context
$tawasulCustomFieldID = $I->haveInDatabase('tawasulCustomField', [
    'context'               => 'Course',
    'name'                  => 'Test Course File Upload',
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

// Add a course with the custom field file upload
$I->amOnModulePage('Timetable Admin', 'course_manage.php');
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Course');

$I->selectFromDropdown('tawasulDepartmentID', 1);

// Verify the custom field file input is present
$I->seeElement('input[type="file"][name="'.$fieldName.'"]');

$I->attachFile('input[type="file"][name="'.$fieldName.'"]', 'attachment.txt');
$I->submitForm('#content form', [
    'name'      => 'Custom Field Test Course',
    'nameShort' => 'CFTEST',
    'orderBy'   => '1',
], 'Submit');
$I->seeSuccessMessage();

$tawasulCourseID = $I->grabEditIDFromURL();
$tawasulSchoolYearID = $I->grabValueFromURL('tawasulSchoolYearID');

// Verify the uploaded file path is stored in the fields JSON column
$fieldsJson = $I->grabFromDatabase('tawasulCourse', 'fields', ['tawasulCourseID' => $tawasulCourseID]);
$fields = json_decode($fieldsJson, true);
$I->assertNotEmpty($fields[$fieldID] ?? '', 'Custom field file upload path should be stored in fields JSON');

$file = $fields[$fieldID];

// Cleanup
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
