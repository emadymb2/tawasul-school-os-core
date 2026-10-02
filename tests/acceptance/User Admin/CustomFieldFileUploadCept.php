<?php
/**
 * @covers modules/TawasulUserAdmin/user_manage_edit.php
 * @covers modules/TawasulUserAdmin/user_manage_editProcess.php
 * @covers src/Forms/CustomFieldHandler.php
 * @covers src/Forms/Input/CustomField.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('test file upload on a custom field for a user');
$I->loginAsAdmin();

// Create a custom field of type 'file' for User context, active for Students
$tawasulCustomFieldID = $I->haveInDatabase('tawasulCustomField', [
    'context'               => 'User',
    'name'                  => 'Test File Upload Field',
    'active'                => 'Y',
    'description'           => 'Acceptance test file upload custom field',
    'type'                  => 'file',
    'options'               => '',
    'required'              => 'N',
    'hidden'                => 'N',
    'heading'               => 'Miscellaneous',
    'sequenceNumber'        => 999,
    'activePersonStudent'   => 1,
    'activePersonStaff'     => 0,
    'activePersonParent'    => 0,
    'activePersonOther'     => 0,
    'activeApplicationForm' => 0,
    'activeDataUpdater'     => 0,
    'activePublicRegistration' => 0,
]);

// Zero-pad the custom field ID to 4 digits (matching TawasulOS's ZEROFILL)
$fieldID = str_pad($tawasulCustomFieldID, 4, '0', STR_PAD_LEFT);
$fieldName = 'custom'.$fieldID.'File';

// Create a test user with Student role
$I->amOnModulePage('User Admin', 'user_manage_add.php');
$I->seeBreadcrumb('Add User');

$I->fillField('username', 'testcustomfield');
$I->fillField('passwordNew', 'ZY6pfPBb!');
$I->fillField('passwordConfirm', 'ZY6pfPBb!');
$I->selectOption('tawasulRoleIDPrimary', 'Student');

$I->submitForm('#content form', [
    'surname'       => 'CustomFieldTest',
    'firstName'     => 'Upload',
    'preferredName' => 'Upload',
    'officialName'  => 'Upload CustomFieldTest',
    'gender'        => 'F',
    'status'        => 'Full',
    'canLogin'      => 'N',
]);
$I->seeSuccessMessage();

$tawasulPersonID = $I->grabEditIDFromURL();

// Navigate to edit page — custom field should appear for Student role
$I->amOnModulePage('User Admin', 'user_manage_edit.php', ['tawasulPersonID' => $tawasulPersonID, 'search' => '']);
$I->seeBreadcrumb('Edit User');

// Verify the custom field file input is present on the form
$I->seeElement('input[type="file"][name="'.$fieldName.'"]');

// Upload a file to the custom field
$I->attachFile('input[type="file"][name="'.$fieldName.'"]', 'attachment.txt');
$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

// Verify the uploaded file path is stored in the fields JSON column
$fieldsJson = $I->grabFromDatabase('tawasulPerson', 'fields', ['tawasulPersonID' => $tawasulPersonID]);
$fields = json_decode($fieldsJson, true);
$I->assertNotEmpty($fields[$fieldID] ?? '', 'Custom field file upload path should be stored in fields JSON');

$file = $fields[$fieldID];

// Cleanup ------------------------------------------------

// Delete the test user via the UI
$I->amOnModulePage('User Admin', 'user_manage_delete.php', ['tawasulPersonID' => $tawasulPersonID, 'search' => '']);
$I->click('Delete');
$I->seeSuccessMessage();

// Delete the custom field
$I->deleteFromDatabase('tawasulCustomField', ['tawasulCustomFieldID' => $tawasulCustomFieldID]);

// Delete the uploaded file
if (!empty($file)) {
    $I->deleteFile('../'.$file);
}
