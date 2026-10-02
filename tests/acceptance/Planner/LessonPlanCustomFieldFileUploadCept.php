<?php
/**
 * @covers modules/TawasulPlanner/planner_edit.php
 * @covers modules/TawasulPlanner/planner_editProcess.php
 * @covers src/Forms/CustomFieldHandler.php
 * @covers src/Forms/Input/CustomField.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('test file upload on a custom field for a lesson plan');
$I->loginAsAdmin();

// Create a custom field of type 'file' for Lesson Plan context
$tawasulCustomFieldID = $I->haveInDatabase('tawasulCustomField', [
    'context'               => 'Lesson Plan',
    'name'                  => 'Test Lesson Plan File Upload',
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

// Grab an existing planner entry to edit
$tawasulPlannerEntryID = $I->grabFromDatabase('tawasulPlannerEntry', 'tawasulPlannerEntryID', []);
$tawasulCourseClassID = $I->grabFromDatabase('tawasulPlannerEntry', 'tawasulCourseClassID', [
    'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
]);

if (!$tawasulPlannerEntryID) {
    // No planner entry exists, clean up and skip
    $I->deleteFromDatabase('tawasulCustomField', ['tawasulCustomFieldID' => $tawasulCustomFieldID]);
    $I->comment('Skipping: No planner entry found');
    return;
}

$I->amOnModulePage('Planner', 'planner_edit.php', [
    'viewBy'               => 'class',
    'tawasulCourseClassID'  => $tawasulCourseClassID,
    'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
]);
$I->seeBreadcrumb('Edit');

// Verify the custom field file input is present
$I->seeElement('input[type="file"][name="'.$fieldName.'"]');

// Upload a file to the custom field
$I->attachFile('input[type="file"][name="'.$fieldName.'"]', 'attachment.txt');
$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

// Verify the uploaded file path is stored in the fields JSON column
$fieldsJson = $I->grabFromDatabase('tawasulPlannerEntry', 'fields', ['tawasulPlannerEntryID' => $tawasulPlannerEntryID]);
$fields = json_decode($fieldsJson, true);
$I->assertNotEmpty($fields[$fieldID] ?? '', 'Custom field file upload path should be stored in fields JSON');

$file = $fields[$fieldID];

// Cleanup
$I->deleteFromDatabase('tawasulCustomField', ['tawasulCustomFieldID' => $tawasulCustomFieldID]);

// Clear the fields value back to empty
$I->updateInDatabase('tawasulPlannerEntry', ['fields' => ''], ['tawasulPlannerEntryID' => $tawasulPlannerEntryID]);

if (!empty($file)) {
    $I->deleteFile('../'.$file);
}
