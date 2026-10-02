<?php
/**
 * @covers modules/TawasulStaff/staff_manage_edit.php
 * @covers modules/TawasulStaff/staff_manage_editProcess.php
 * @covers src/Forms/CustomFieldHandler.php
 * @covers src/Forms/Input/CustomField.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('test file upload on a custom field for a staff member');
$I->loginAsAdmin();

// Create a custom field of type 'file' for Staff context
$tawasulCustomFieldID = $I->haveInDatabase('tawasulCustomField', [
    'context'               => 'Staff',
    'name'                  => 'Test Staff File Upload',
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

// Grab an existing staff record to edit
$tawasulStaffID = $I->grabFromDatabase('tawasulStaff', 'tawasulStaffID', ['type' => 'Teaching']);

$I->amOnModulePage('Staff', 'staff_manage_edit.php', ['tawasulStaffID' => $tawasulStaffID]);
$I->seeBreadcrumb('Edit Staff');

// Verify the custom field file input is present
$I->seeElement('input[type="file"][name="'.$fieldName.'"]');

// Upload a file to the custom field
$I->attachFile('input[type="file"][name="'.$fieldName.'"]', 'attachment.txt');
$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

// Verify the uploaded file path is stored in the fields JSON column
$fieldsJson = $I->grabFromDatabase('tawasulStaff', 'fields', ['tawasulStaffID' => $tawasulStaffID]);
$fields = json_decode($fieldsJson, true);
$I->assertNotEmpty($fields[$fieldID] ?? '', 'Custom field file upload path should be stored in fields JSON');

$file = $fields[$fieldID];

// Cleanup
$I->deleteFromDatabase('tawasulCustomField', ['tawasulCustomFieldID' => $tawasulCustomFieldID]);

// Clear the fields value back to empty
$I->updateInDatabase('tawasulStaff', ['fields' => ''], ['tawasulStaffID' => $tawasulStaffID]);

if (!empty($file)) {
    $I->deleteFile('../'.$file);
}
