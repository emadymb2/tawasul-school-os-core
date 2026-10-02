<?php
/**
 * @covers modules/TawasulPlanner/planner_view_full.php
 * @covers modules/TawasulPlanner/planner_view_full_post.php
 * @covers modules/TawasulPlanner/planner_view_full_submitProcess.php
 * @covers modules/TawasulPlanner/planner_view_full_submit_edit.php
 * @covers modules/TawasulPlanner/planner_view_full_submit_editProcess.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check View Lesson Plan with homework file upload');
$I->loginAsAdmin();

// Use a class from an existing planner entry (ensures valid course/class)
$tawasulCourseClassID = $I->grabFromDatabase('tawasulPlannerEntry', 'tawasulCourseClassID', []);

// Get a student from this class
$tawasulPersonIDStudent = $I->grabFromDatabase('tawasulCourseClassPerson', 'tawasulPersonID', [
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'role' => 'Student',
]);

// Enroll the test student (testingstudent = 0000002775) in this class
$testStudentID = '0000002775';
$tawasulCourseClassPersonID = $I->haveInDatabase('tawasulCourseClassPerson', [
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulPersonID'      => $testStudentID,
    'role'                => 'Student',
    'reportable'          => 'Y',
]);

// Create a planner entry with homework submission enabled (file type, due in the future)
$tawasulPlannerEntryID = $I->haveInDatabase('tawasulPlannerEntry', [
    'tawasulCourseClassID'                      => $tawasulCourseClassID,
    'date'                                     => date('Y-m-d'),
    'timeStart'                                => '08:00:00',
    'timeEnd'                                  => '09:00:00',
    'name'                                     => 'Upload Test Lesson',
    'summary'                                  => 'Test lesson for homework upload',
    'description'                              => 'Test lesson description',
    'teachersNotes'                            => '',
    'homework'                                 => 'Y',
    'homeworkDueDateTime'                      => date('Y-m-d', strtotime('+7 days')).' 23:59:00',
    'homeworkDetails'                          => 'Submit your homework file',
    'homeworkSubmission'                       => 'Y',
    'homeworkSubmissionDateOpen'               => date('Y-m-d', strtotime('-1 day')),
    'homeworkSubmissionDrafts'                 => '0',
    'homeworkSubmissionType'                   => 'File',
    'homeworkSubmissionRequired'               => 'Required',
    'homeworkCrowdAssess'                      => 'N',
    'homeworkCrowdAssessOtherTeachersRead'     => 'N',
    'homeworkCrowdAssessOtherParentsRead'      => 'N',
    'homeworkCrowdAssessClassmatesParentsRead' => 'N',
    'homeworkCrowdAssessSubmitterParentsRead'  => 'N',
    'homeworkCrowdAssessOtherStudentsRead'     => 'N',
    'homeworkCrowdAssessClassmatesRead'        => 'N',
    'viewableStudents'                         => 'Y',
    'viewableParents'                          => 'N',
    'tawasulPersonIDCreator'                    => '0000000001',
    'tawasulPersonIDLastEdit'                   => '0000000001',
]);

// Basic admin check --------------------------------
$I->amOnModulePage('Planner', 'planner_view_full.php', [
    'viewBy'               => 'class',
    'tawasulCourseClassID'  => $tawasulCourseClassID,
    'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
]);
$I->seeBreadcrumb('View Lesson Plan');

// Student Homework Submission --------------------------------
$I->amOnPage('/logout.php');
$I->loginAsStudent();

$I->amOnModulePage('Planner', 'planner_view_full.php', [
    'viewBy'               => 'class',
    'tawasulCourseClassID'  => $tawasulCourseClassID,
    'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
    'date'                 => date('Y-m-d'),
]);
$I->seeBreadcrumb('View Lesson Plan');

// Submit homework file
$I->selectOption('version', 'Final');
$I->attachFile('file', 'attachment.txt');
$I->click('Submit');
$I->seeSuccessMessage();

// Verify submission was saved
$submissionFile = $I->grabFromDatabase('tawasulPlannerEntryHomework', 'location', [
    'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
    'tawasulPersonID'       => $testStudentID,
]);
$I->assertNotEmpty($submissionFile);

$I->seeInDatabase('tawasulPlannerEntryHomework', [
    'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
    'tawasulPersonID'       => $testStudentID,
    'type'                 => 'File',
    'version'              => 'Final',
]);

// Submission Edit — Teacher adds submission on behalf of another student --------------------------------
$I->amOnPage('/logout.php');
$I->loginAsAdmin();

$I->amOnModulePage('Planner', 'planner_view_full_submit_edit.php', [
    'viewBy'               => 'class',
    'tawasulCourseClassID'  => $tawasulCourseClassID,
    'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
    'submission'           => 'false',
    'tawasulPersonID'       => $tawasulPersonIDStudent,
]);
$I->seeBreadcrumb('Add Submission');

$I->selectOption('type', 'File');
$I->selectOption('version', 'Final');
$I->attachFile('file', 'attachment.txt');
$I->selectOption('status', 'On Time');
$I->click('Submit');
$I->seeSuccessMessage();

// Verify teacher-added submission was saved
$editFile = $I->grabFromDatabase('tawasulPlannerEntryHomework', 'location', [
    'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
    'tawasulPersonID'       => $tawasulPersonIDStudent,
]);
$I->assertNotEmpty($editFile);

// Cleanup --------------------------------
$I->deleteFromDatabase('tawasulPlannerEntryHomework', [
    'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
]);
$I->deleteFromDatabase('tawasulPlannerEntry', [
    'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
]);
$I->deleteFromDatabase('tawasulCourseClassPerson', [
    'tawasulCourseClassPersonID' => $tawasulCourseClassPersonID,
]);

if (!empty($submissionFile)) {
    $I->deleteFile('../'.$submissionFile);
}
if (!empty($editFile)) {
    $I->deleteFile('../'.$editFile);
}
