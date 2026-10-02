<?php
/**
 * @covers modules/TawasulReports/reporting_write.php
 * @covers modules/TawasulReports/reporting_writeProcess.php
 * @covers modules/TawasulReports/reporting_write_byStudent.php
 * @covers modules/TawasulReports/reporting_write_byStudentProcess.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check reporting write with image file upload');
$I->loginAsAdmin();

// Setup: get current school year and a year group with enrolled students
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);
$tawasulYearGroupID = $I->grabFromDatabase('tawasulStudentEnrolment', 'tawasulYearGroupID', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
]);

// Get a student enrolled in this year group
$tawasulPersonIDStudent = $I->grabFromDatabase('tawasulStudentEnrolment', 'tawasulPersonID', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulYearGroupID'  => $tawasulYearGroupID,
]);

// Create an Image criteria type
$tawasulReportingCriteriaTypeID = $I->haveInDatabase('tawasulReportingCriteriaType', [
    'name'      => 'Test Image Upload',
    'valueType' => 'Image',
    'active'    => 'Y',
]);

// Create a reporting cycle spanning today
$tawasulReportingCycleID = $I->haveInDatabase('tawasulReportingCycle', [
    'tawasulSchoolYearID'  => $tawasulSchoolYearID,
    'name'                => 'Test Upload Cycle',
    'nameShort'           => 'TUC',
    'sequenceNumber'      => 99,
    'dateStart'           => date('Y-m-d', strtotime('-1 day')),
    'dateEnd'             => date('Y-m-d', strtotime('+30 days')),
    'tawasulYearGroupIDList' => $tawasulYearGroupID,
]);

// Create a reporting scope (Year Group)
$tawasulReportingScopeID = $I->haveInDatabase('tawasulReportingScope', [
    'tawasulReportingCycleID' => $tawasulReportingCycleID,
    'scopeType'              => 'Year Group',
    'name'                   => 'Test Upload Scope',
]);

// Create reporting access (role-based for admin role 001)
$tawasulReportingAccessID = $I->haveInDatabase('tawasulReportingAccess', [
    'tawasulReportingCycleID'   => $tawasulReportingCycleID,
    'tawasulReportingScopeIDList' => $tawasulReportingScopeID,
    'tawasulRoleIDList'         => '001',
    'accessType'               => 'Role',
    'dateStart'                => date('Y-m-d', strtotime('-1 day')),
    'dateEnd'                  => date('Y-m-d', strtotime('+30 days')),
    'canWrite'                 => 'Y',
    'canProofRead'             => 'N',
]);

// Create a Per Group criteria (for reporting_write.php)
$tawasulReportingCriteriaID_group = $I->haveInDatabase('tawasulReportingCriteria', [
    'tawasulReportingCycleID'       => $tawasulReportingCycleID,
    'tawasulReportingScopeID'       => $tawasulReportingScopeID,
    'tawasulReportingCriteriaTypeID' => $tawasulReportingCriteriaTypeID,
    'tawasulYearGroupID'            => $tawasulYearGroupID,
    'target'                       => 'Per Group',
    'name'                         => 'Group Image Upload',
    'sequenceNumber'               => 1,
]);

// Create a Per Student criteria (for reporting_write_byStudent.php)
$tawasulReportingCriteriaID_student = $I->haveInDatabase('tawasulReportingCriteria', [
    'tawasulReportingCycleID'       => $tawasulReportingCycleID,
    'tawasulReportingScopeID'       => $tawasulReportingScopeID,
    'tawasulReportingCriteriaTypeID' => $tawasulReportingCriteriaTypeID,
    'tawasulYearGroupID'            => $tawasulYearGroupID,
    'target'                       => 'Per Student',
    'name'                         => 'Student Image Upload',
    'sequenceNumber'               => 1,
]);

// Test 1: Per Group — reporting_write.php --------------------------------
$I->amOnModulePage('Reports', 'reporting_write.php', [
    'tawasulSchoolYearID'    => $tawasulSchoolYearID,
    'tawasulReportingCycleID' => $tawasulReportingCycleID,
    'tawasulReportingScopeID' => $tawasulReportingScopeID,
    'scopeTypeID'           => $tawasulYearGroupID,
]);
$I->seeBreadcrumb('Write Reports');

// Criteria IDs are zero-padded to 12 digits in the form field names
$groupCriteriaField = 'file'.str_pad($tawasulReportingCriteriaID_group, 12, '0', STR_PAD_LEFT);
$studentCriteriaField = 'file'.str_pad($tawasulReportingCriteriaID_student, 12, '0', STR_PAD_LEFT);

$I->attachFile($groupCriteriaField, 'attachment.jpg');
$I->click('Submit');
$I->seeSuccessMessage();

// Verify the value was saved
$groupFile = $I->grabFromDatabase('tawasulReportingValue', 'value', [
    'tawasulReportingCriteriaID' => $tawasulReportingCriteriaID_group,
]);
$I->assertNotEmpty($groupFile);

// Test 2: Per Student — reporting_write_byStudent.php --------------------------------
$I->amOnModulePage('Reports', 'reporting_write_byStudent.php', [
    'tawasulSchoolYearID'     => $tawasulSchoolYearID,
    'tawasulReportingCycleID' => $tawasulReportingCycleID,
    'tawasulReportingScopeID' => $tawasulReportingScopeID,
    'scopeTypeID'            => $tawasulYearGroupID,
    'tawasulPersonIDStudent'  => $tawasulPersonIDStudent,
]);
$I->seeBreadcrumb('By Student');

$I->attachFile($studentCriteriaField, 'attachment.jpg');
$I->click('Save');
$I->seeSuccessMessage();

// Verify the value was saved
$studentFile = $I->grabFromDatabase('tawasulReportingValue', 'value', [
    'tawasulReportingCriteriaID' => $tawasulReportingCriteriaID_student,
    'tawasulPersonIDStudent'     => $tawasulPersonIDStudent,
]);
$I->assertNotEmpty($studentFile);

// Cleanup --------------------------------
$I->deleteFromDatabase('tawasulReportingValue', [
    'tawasulReportingCycleID' => $tawasulReportingCycleID,
]);
$I->deleteFromDatabase('tawasulReportingProgress', [
    'tawasulReportingScopeID' => $tawasulReportingScopeID,
]);
$I->deleteFromDatabase('tawasulReportingCriteria', [
    'tawasulReportingScopeID' => $tawasulReportingScopeID,
]);
$I->deleteFromDatabase('tawasulReportingAccess', [
    'tawasulReportingAccessID' => $tawasulReportingAccessID,
]);
$I->deleteFromDatabase('tawasulReportingScope', [
    'tawasulReportingScopeID' => $tawasulReportingScopeID,
]);
$I->deleteFromDatabase('tawasulReportingCycle', [
    'tawasulReportingCycleID' => $tawasulReportingCycleID,
]);
$I->deleteFromDatabase('tawasulReportingCriteriaType', [
    'tawasulReportingCriteriaTypeID' => $tawasulReportingCriteriaTypeID,
]);

if (!empty($groupFile)) {
    $I->deleteFile('../'.$groupFile);
}
if (!empty($studentFile)) {
    $I->deleteFile('../'.$studentFile);
}
