<?php
/**
 * @covers modules/TawasulFormalAssessment/internalAssessment_write.php
 * @covers modules/TawasulFormalAssessment/internalAssessment_write_data.php
 * @covers modules/TawasulFormalAssessment/internalAssessment_write_dataProcess.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('Write internal assessments with file upload');
$I->loginAsAdmin();
$I->amOnModulePage('Formal Assessment', 'internalAssessment_write.php');
$I->seeBreadcrumb('Write Internal Assessments');

// Get a course class ID from an existing internal assessment column (ensures valid course with department)
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);
$tawasulCourseID = $I->grabFromDatabase('tawasulCourse', 'tawasulCourseID', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);
$tawasulCourseClassID = $I->grabFromDatabase('tawasulCourseClass', 'tawasulCourseClassID', ['tawasulCourseID' => $tawasulCourseID]);

// Create an internal assessment column with uploadedResponse enabled
$tawasulInternalAssessmentColumnID = $I->haveInDatabase('tawasulInternalAssessmentColumn', [
    'tawasulCourseClassID'    => $tawasulCourseClassID,
    'name'                   => 'Upload Test Column',
    'description'            => 'Testing file upload for student responses',
    'type'                   => 'Test',
    'attachment'             => '',
    'attainment'             => 'N',
    'tawasulScaleIDAttainment' => null,
    'effort'                 => 'N',
    'tawasulScaleIDEffort'    => null,
    'comment'                => 'Y',
    'uploadedResponse'       => 'Y',
    'completeDate'           => null,
    'complete'               => 'N',
    'viewableStudents'       => 'Y',
    'viewableParents'        => 'Y',
    'tawasulPersonIDCreator'  => '0000000001',
    'tawasulPersonIDLastEdit' => '0000000001',
]);

// Navigate to write data page
$I->amOnModulePage('Formal Assessment', 'internalAssessment_write_data.php', [
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID,
]);

$I->seeBreadcrumb('Enter Internal Assessment Results');

// Fill in the column-level attachment
$I->attachFile('file', 'attachment.txt');

// Fill in student response for the first student (count=1)
// The hidden field 1-tawasulPersonID maps count 1 to the actual student
$I->fillField('comment1', 'Test student comment.');
$I->attachFile('response1', 'attachment.txt');

$I->click('Submit');
$I->seeSuccessMessage();
$I->dontSeeErrors();

// Verify column-level attachment was saved
$columnFile = $I->grabFromDatabase('tawasulInternalAssessmentColumn', 'attachment', [
    'tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID,
]);
$I->assertNotEmpty($columnFile);

// Grab the first student's person ID from the entry that was created
$tawasulPersonIDStudent = $I->grabFromDatabase('tawasulInternalAssessmentEntry', 'tawasulPersonIDStudent', [
    'tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID,
    'comment' => 'Test student comment.',
]);

// Verify student response file was saved
$responseFile = $I->grabFromDatabase('tawasulInternalAssessmentEntry', 'response', [
    'tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID,
    'tawasulPersonIDStudent' => $tawasulPersonIDStudent,
]);
$I->assertNotEmpty($responseFile);

// Cleanup: delete all entries for this column, then the column itself
$I->deleteFromDatabase('tawasulInternalAssessmentEntry', [
    'tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID,
]);
$I->deleteFromDatabase('tawasulInternalAssessmentColumn', [
    'tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID,
]);

if (!empty($columnFile)) {
    $I->deleteFile('../'.$columnFile);
}
if (!empty($responseFile)) {
    $I->deleteFile('../'.$responseFile);
}
