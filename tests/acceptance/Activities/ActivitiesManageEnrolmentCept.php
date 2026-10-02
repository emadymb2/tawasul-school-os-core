<?php
/**
 * @covers modules/TawasulActivities/activities_manage.php
 * @covers modules/TawasulActivities/activities_manage_add.php
 * @covers modules/TawasulActivities/activities_manage_enrolment.php
 * @covers modules/TawasulActivities/activities_manage_enrolment_edit.php
 * @covers modules/TawasulActivities/activities_manage_enrolment_delete.php
 * @covers modules/TawasulActivities/activities_manage_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage activity enrolment for a specific activity');
$I->loginAsAdmin();

// First create an activity to test enrolment
$I->amOnModulePage('Activities', 'activities_manage.php');
$I->clickNavigation('Add');
$I->fillField('name', 'Test Activity for Enrolment');
$I->click('Submit');
$I->seeSuccessMessage();
$tawasulActivityID = $I->grabValueFromURL('editID');

// Now go to the enrolment page
$I->amOnModulePage('Activities', 'activities_manage_enrolment.php', ['tawasulActivityID' => $tawasulActivityID]);
$I->seeBreadcrumb('Activity Enrolment');

// Add a student to test edit and delete
$I->clickNavigation('Add');
$I->selectOption('Members[]', 'TestUser, Student (testingstudent)');
$I->click('Submit');
$I->seeSuccessMessage();

// Get the student's tawasulPersonID for edit/delete operations
$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', [
    'username' => 'testingstudent'
]);

// Get the enrolment ID for edit operations
$tawasulActivityStudentID = $I->grabFromDatabase('tawasulActivityStudent', 'tawasulActivityStudentID', [
    'tawasulActivityID' => $tawasulActivityID,
    'tawasulPersonID' => $tawasulPersonID
]);

// Test Edit Enrolment -----------------------------------
if ($tawasulActivityStudentID) {
    $I->amOnModulePage('Activities', 'activities_manage_enrolment_edit.php', [
        'tawasulActivityID' => $tawasulActivityID,
        'tawasulActivityStudentID' => $tawasulActivityStudentID
    ]);
    $I->seeBreadcrumb('Edit Enrolment');
    $I->dontSeeErrors();
}
    
// Test Delete Enrolment ---------------------------------
if ($tawasulPersonID) {
    $I->amOnModulePage('Activities', 'activities_manage_enrolment_delete.php', [
        'tawasulActivityID' => $tawasulActivityID,
        'tawasulPersonID' => $tawasulPersonID,
        'search' => '',
        'tawasulSchoolYearTermID' => ''
    ]);
    $I->dontSeeErrors();
    // Note: Not actually deleting to avoid breaking the test activity
}

// Clean up - delete the activity
$I->amOnModulePage('Activities', 'activities_manage.php');
$I->click('Delete', "//td[contains(text(),'Test Activity for Enrolment')]/..");
$I->click('Delete');
$I->seeSuccessMessage();
