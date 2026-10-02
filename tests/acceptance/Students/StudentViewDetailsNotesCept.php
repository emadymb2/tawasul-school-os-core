<?php
/**
 * @covers modules/TawasulStudents/student_view_details_notes_add.php
 * @covers modules/TawasulStudents/student_view_details_notes_edit.php
 * @covers modules/TawasulStudents/student_view_details_notes_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage student notes');
$I->loginAsAdmin();

$tawasulActionID = $I->grabFromDatabase('tawasulAction', 'tawasulActionID', ['name' => 'View Student Profile_fullEditAllNotes']);
$tawasulPermissionID = $I->haveInDatabase('tawasulPermission', ['tawasulRoleID' => 1, 'tawasulActionID' => $tawasulActionID]);

// Get a student to work with
$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['tawasulRoleIDPrimary' => '003', 'status' => 'Full']);

// Add Student Note ------------------------------------

$I->amOnModulePage('Students', 'student_view_details_notes_add.php', [
    'tawasulPersonID' => $tawasulPersonID,
    'subpage' => 'Notes'
]);
$I->seeBreadcrumb('Add Student Note');

$I->selectFromDropdown('tawasulStudentNoteCategoryID', 1);

$formValues = array(
    'title' => 'Test Note Title',
    'note' => 'Test note content',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$tawasulStudentNoteID = $I->grabEditIDFromURL();

// Edit Student Note -----------------------------------

$I->amOnModulePage('Students', 'student_view_details_notes_edit.php', [
    'tawasulPersonID' => $tawasulPersonID,
    'subpage' => 'Notes',
    'tawasulStudentNoteID' => $tawasulStudentNoteID
]);
$I->seeBreadcrumb('Edit Student Note');

$I->seeInFormFields('#content form', array(
    'title' => 'Test Note Title',
));

$formValues = array(
    'title' => 'Updated Note Title',
    'note' => 'Updated note content',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

// Delete Student Note ---------------------------------

$I->amOnModulePage('Students', 'student_view_details_notes_delete.php', [
    'tawasulPersonID' => $tawasulPersonID,
    'subpage' => 'Notes',
    'tawasulStudentNoteID' => $tawasulStudentNoteID
]);

$I->click('Delete');
$I->seeSuccessMessage();


$I->deleteFromDatabase('tawasulPermission', ['permissionID' => $tawasulPermissionID]);
