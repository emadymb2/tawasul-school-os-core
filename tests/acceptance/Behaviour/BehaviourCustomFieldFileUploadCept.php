<?php
/**
 * @covers modules/TawasulBehaviour/behaviour_manage_add.php
 * @covers modules/TawasulBehaviour/behaviour_manage_addProcess.php
 * @covers src/Forms/CustomFieldHandler.php
 * @covers src/Forms/Input/CustomField.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('test file upload on a custom field for a behaviour record');
$I->loginAsAdmin();

// Create a custom field of type 'file' for Behaviour context
$tawasulCustomFieldID = $I->haveInDatabase('tawasulCustomField', [
    'context'               => 'Behaviour',
    'name'                  => 'Test Behaviour File Upload',
    'active'                => 'Y',
    'description'           => 'Acceptance test file upload custom field',
    'type'                  => 'file',
    'options'               => '',
    'required'              => 'N',
    'hidden'                => 'N',
    'heading'               => 'Step 1',
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

// Add a behaviour record with the custom field file upload
$I->amOnModulePage('Behaviour', 'behaviour_manage_add.php');
$I->seeBreadcrumb('Add');

$I->selectFromDropdown('tawasulPersonID', 1);
$I->selectFromDropdown('type', 1);
$I->selectFromDropdown('descriptor', 1);

// Verify the custom field file input is present
$I->seeElement('input[type="file"][name="'.$fieldName.'"]');

$I->attachFile('input[type="file"][name="'.$fieldName.'"]', 'attachment.txt');
$I->submitForm('#content form', [
    'date'    => date('Y-m-d'),
    'comment' => 'Custom field file upload test',
], 'Submit');
$I->seeSuccessMessage();

$tawasulBehaviourID = $I->grabEditIDFromURL();

// Verify the uploaded file path is stored in the fields JSON column
$fieldsJson = $I->grabFromDatabase('tawasulBehaviour', 'fields', ['tawasulBehaviourID' => $tawasulBehaviourID]);
$fields = json_decode($fieldsJson, true);
$I->assertNotEmpty($fields[$fieldID] ?? '', 'Custom field file upload path should be stored in fields JSON');

$file = $fields[$fieldID];

// Cleanup
$I->amOnModulePage('Behaviour', 'behaviour_manage_delete.php', ['tawasulBehaviourID' => $tawasulBehaviourID]);
$I->fillField('confirm', 'Delete');
$I->click('Yes');
$I->seeSuccessMessage();

$I->deleteFromDatabase('tawasulCustomField', ['tawasulCustomFieldID' => $tawasulCustomFieldID]);

if (!empty($file)) {
    $I->deleteFile('../'.$file);
}
