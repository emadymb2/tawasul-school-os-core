<?php
/**
 * @covers modules/TawasulPlanner/units.php
 * @covers modules/TawasulPlanner/units_add.php
 * @covers modules/TawasulPlanner/units_edit.php
 * @covers modules/TawasulPlanner/units_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage planner units');
$I->loginAsAdmin();
$I->amOnModulePage('Planner', 'units.php');
$I->seeBreadcrumb('Unit Planner');
$I->dontSeeErrors();

// Get a course to work with
$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);
$tawasulCourseID = $I->grabFromDatabase('tawasulCourse', 'tawasulCourseID', ['tawasulSchoolYearID' => $tawasulSchoolYearID]);

if (empty($tawasulCourseID)) {
    $I->comment('No course found for current year, skipping unit test');
    return;
}

// Add ------------------------------------------------
$I->amOnModulePage('Planner', 'units_add.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulCourseID' => $tawasulCourseID,
]);
$I->seeBreadcrumb('Add Unit');

$addFormValues = [
    'name'        => 'Test Unit Upload',
    'description' => 'Unit for testing file upload.',
];

$I->attachFile('file', 'attachment.txt');
$I->submitForm('#content form', $addFormValues, 'Submit');
$I->see('success', '.success');

$tawasulUnitID = $I->grabValueFromURL('tawasulUnitID');
$file = $I->grabFromDatabase('tawasulUnit', 'attachment', ['tawasulUnitID' => $tawasulUnitID]);
$I->assertNotEmpty($file);

// Edit ------------------------------------------------
$I->amOnModulePage('Planner', 'units_edit.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulCourseID' => $tawasulCourseID,
    'tawasulUnitID' => $tawasulUnitID,
]);
$I->seeBreadcrumb('Edit Unit');

$I->fillField('attachment', '');
$I->submitForm('#content form', ['name' => 'Test Unit Upload Updated'], 'Submit');
$I->seeSuccessMessage();

$I->seeInDatabase('tawasulUnit', ['tawasulUnitID' => $tawasulUnitID, 'attachment' => '']);

// Edit - File Upload ------------------------------------------------
$I->amOnModulePage('Planner', 'units_edit.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulCourseID' => $tawasulCourseID,
    'tawasulUnitID' => $tawasulUnitID,
]);

$I->attachFile('file', 'attachment2.png');
$I->submitForm('#content form', ['name' => 'Test Unit Upload Updated'], 'Submit');
$I->seeSuccessMessage();

$file2 = $I->grabFromDatabase('tawasulUnit', 'attachment', ['tawasulUnitID' => $tawasulUnitID]);
$I->assertNotEmpty($file2);

// Delete ------------------------------------------------
$I->amOnModulePage('Planner', 'units_delete.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulCourseID' => $tawasulCourseID,
    'tawasulUnitID' => $tawasulUnitID,
]);

$I->click('Delete');
$I->seeSuccessMessage();
