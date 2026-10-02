<?php
/**
 * @covers modules/TawasulFormalAssessment/externalAssessment_manage_details_add.php
 * @covers modules/TawasulFormalAssessment/externalAssessment_manage_details_edit.php
 * @covers modules/TawasulFormalAssessment/externalAssessment_manage_details_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage external assessment details');
$I->loginAsAdmin();

// Get a student ID from the database
$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full']);

// Create test data directly in database
$tawasulExternalAssessmentID = $I->grabFromDatabase('tawasulExternalAssessment', 'tawasulExternalAssessmentID', ['active' => 'Y']);

// Enable file upload for this assessment type
$I->updateInDatabase('tawasulExternalAssessment', ['allowFileUpload' => 'Y'], ['tawasulExternalAssessmentID' => $tawasulExternalAssessmentID]);

$tawasulExternalAssessmentStudentID = $I->haveInDatabase('tawasulExternalAssessmentStudent', [
    'tawasulPersonID' => $tawasulPersonID,
    'tawasulExternalAssessmentID' => $tawasulExternalAssessmentID,
    'date' => '2024-01-15',
    'attachment' => '',
]);

// Edit ------------------------------------------------
$I->amOnModulePage('Formal Assessment', 'externalAssessment_manage_details_edit.php', [
    'tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID,
    'tawasulPersonID' => $tawasulPersonID
]);
$I->seeBreadcrumb('Edit Assessment');

$I->seeInField('date', '2024-01-15');

$I->attachFile('file', 'attachment.txt');

$formValues = [
    'date' => '2024-02-20',
];

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$file = $I->grabFromDatabase('tawasulExternalAssessmentStudent', 'attachment', ['tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID]);
$I->assertNotEmpty($file);

// Edit again to remove attachment ------------------------------------------------
$I->amOnModulePage('Formal Assessment', 'externalAssessment_manage_details_edit.php', [
    'tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID,
    'tawasulPersonID' => $tawasulPersonID
]);

$I->fillField('attachment', '');
$I->submitForm('#content form', ['date' => '2024-02-20'], 'Submit');
$I->seeSuccessMessage();

$I->seeInDatabase('tawasulExternalAssessmentStudent', ['tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID, 'attachment' => '']);

// Add - File Upload ------------------------------------------------
$I->amOnModulePage('Formal Assessment', 'externalAssessment_manage_details_add.php', [
    'tawasulExternalAssessmentID' => $tawasulExternalAssessmentID,
    'tawasulPersonID' => $tawasulPersonID,
    'step' => 2,
]);
$I->seeBreadcrumb('Add Assessment');

$I->attachFile('file', 'attachment2.png');
$I->submitForm('#content form', ['date' => '2024-03-15'], 'Submit');
$I->seeSuccessMessage();

$tawasulExternalAssessmentStudentID2 = $I->grabEditIDFromURL();
$file2 = $I->grabFromDatabase('tawasulExternalAssessmentStudent', 'attachment', ['tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID2]);
$I->assertNotEmpty($file2);

// Delete second record ------------------------------------------------
$I->amOnModulePage('Formal Assessment', 'externalAssessment_manage_details_delete.php', [
    'tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID2,
    'tawasulPersonID' => $tawasulPersonID
]);

$I->click('Delete');
$I->seeSuccessMessage();

// Delete ------------------------------------------------
$I->amOnModulePage('Formal Assessment', 'externalAssessment_manage_details_delete.php', [
    'tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID,
    'tawasulPersonID' => $tawasulPersonID
]);

$I->click('Delete');
$I->seeSuccessMessage();

// Cleanup ------------------------------------------------
$I->updateInDatabase('tawasulExternalAssessment', ['allowFileUpload' => 'N'], ['tawasulExternalAssessmentID' => $tawasulExternalAssessmentID]);
