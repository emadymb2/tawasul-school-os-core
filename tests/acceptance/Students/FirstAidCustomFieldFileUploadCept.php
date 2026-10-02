<?php
/**
 * @covers modules/TawasulStudents/firstAidRecord_add.php
 * @covers modules/TawasulStudents/firstAidRecord_addProcess.php
 * @covers src/Forms/CustomFieldHandler.php
 * @covers src/Forms/Input/CustomField.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('test file upload on a custom field for a first aid record');
$I->loginAsAdmin();

// Create a custom field of type 'file' for First Aid context
$tawasulCustomFieldID = $I->haveInDatabase('tawasulCustomField', [
    'context'               => 'First Aid',
    'name'                  => 'Test First Aid File Upload',
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

// Add a first aid record with the custom field file upload
$I->amOnModulePage('Students', 'firstAidRecord_add.php');
$I->seeBreadcrumb('Add');

$I->selectFromDropdown('tawasulPersonID', 1);

// Verify the custom field file input is present
$I->seeElement('input[type="file"][name="'.$fieldName.'"]');

$I->attachFile('input[type="file"][name="'.$fieldName.'"]', 'attachment.txt');
$I->submitForm('#content form', [
    'date'        => date('d/m/Y'),
    'timeIn'      => '09:00',
    'description' => 'Custom field file upload test',
    'actionTaken' => 'Test action taken',
], 'Submit');
$I->seeSuccessMessage();

$tawasulFirstAidID = $I->grabEditIDFromURL();

// Verify the uploaded file path is stored in the fields JSON column
$fieldsJson = $I->grabFromDatabase('tawasulFirstAid', 'fields', ['tawasulFirstAidID' => $tawasulFirstAidID]);
$fields = json_decode($fieldsJson, true);
$I->assertNotEmpty($fields[$fieldID] ?? '', 'Custom field file upload path should be stored in fields JSON');

$file = $fields[$fieldID];

// Cleanup
$I->deleteFromDatabase('tawasulFirstAid', ['tawasulFirstAidID' => $tawasulFirstAidID]);
$I->deleteFromDatabase('tawasulCustomField', ['tawasulCustomFieldID' => $tawasulCustomFieldID]);

if (!empty($file)) {
    $I->deleteFile('../'.$file);
}
