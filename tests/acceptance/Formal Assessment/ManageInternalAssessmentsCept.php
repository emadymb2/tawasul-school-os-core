<?php
/**
 * @covers modules/TawasulFormalAssessment/internalAssessment_manage.php
 * @covers modules/TawasulFormalAssessment/internalAssessment_manage_add.php
 * @covers modules/TawasulFormalAssessment/internalAssessment_manage_edit.php
 * @covers modules/TawasulFormalAssessment/internalAssessment_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage internal assessment columns');
$I->loginAsAdmin();

// Get a course class ID
$tawasulCourseClassID = $I->grabFromDatabase('tawasulCourseClass', 'tawasulCourseClassID', []);

$I->amOnModulePage('Formal Assessment', 'internalAssessment_manage.php', [
    'tawasulCourseClassID' => $tawasulCourseClassID
]);
$I->seeBreadcrumb('Manage');

// Create test data directly in database
$tawasulInternalAssessmentColumnID = $I->haveInDatabase('tawasulInternalAssessmentColumn', [
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'name' => 'Test Assessment',
    'description' => 'Test Description',
    'type' => 'Test',
    'attachment' => '',
    'attainment' => 'N',
    'tawasulScaleIDAttainment' => null,
    'effort' => 'N',
    'tawasulScaleIDEffort' => null,
    'comment' => 'N',
    'uploadedResponse' => 'N',
    'completeDate' => '2024-06-30',
    'complete' => 'N',
    'viewableStudents' => 'Y',
    'viewableParents' => 'Y',
    'tawasulPersonIDCreator' => '0000000001',
    'tawasulPersonIDLastEdit' => '0000000001',
]);

// Edit ------------------------------------------------
$I->amOnModulePage('Formal Assessment', 'internalAssessment_manage_edit.php', [
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID
]);
$I->seeBreadcrumb('Edit Column');

$I->seeInField('name', 'Test Assessment');

// Select type if available
$I->selectFromDropdown('type', 1);

$I->attachFile('file', 'attachment.txt');

$formValues = [
    'name' => 'Updated Assessment',
    'description' => 'Updated Description',
    'attainment' => 'N',
    'effort' => 'N',
    'comment' => 'N',
    'uploadedResponse' => 'N',
    'viewableStudents' => 'Y',
    'viewableParents' => 'Y',
    'completeDate' => '2024-07-15',
    'complete' => 'Y',
];

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$file = $I->grabFromDatabase('tawasulInternalAssessmentColumn', 'attachment', ['tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID]);
$I->assertNotEmpty($file);

// Edit again to remove attachment ------------------------------------------------
$I->amOnModulePage('Formal Assessment', 'internalAssessment_manage_edit.php', [
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID
]);

$I->selectFromDropdown('type', 1);
$I->fillField('attachment', '');

$formValues = [
    'name' => 'Updated Assessment',
    'description' => 'Updated Description',
    'attainment' => 'N',
    'effort' => 'N',
    'comment' => 'N',
    'uploadedResponse' => 'N',
    'viewableStudents' => 'Y',
    'viewableParents' => 'Y',
    'completeDate' => '2024-07-15',
    'complete' => 'Y',
];

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$I->seeInDatabase('tawasulInternalAssessmentColumn', ['tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID, 'attachment' => '']);

// Edit - File Upload ------------------------------------------------
$I->amOnModulePage('Formal Assessment', 'internalAssessment_manage_edit.php', [
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID
]);

$I->selectFromDropdown('type', 1);
$I->attachFile('file', 'attachment2.png');

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$file2 = $I->grabFromDatabase('tawasulInternalAssessmentColumn', 'attachment', ['tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID]);
$I->assertNotEmpty($file2);

// Delete ------------------------------------------------
$I->amOnModulePage('Formal Assessment', 'internalAssessment_manage_delete.php', [
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID
]);

$I->click('Delete');
$I->seeSuccessMessage();
