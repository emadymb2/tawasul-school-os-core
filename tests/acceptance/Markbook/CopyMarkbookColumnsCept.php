<?php
/**
 * @covers modules/TawasulMarkbook/markbook_view.php
 * @covers modules/TawasulMarkbook/markbook_edit_add.php
 * @covers modules/TawasulMarkbook/markbook_edit.php
 * @covers modules/TawasulMarkbook/markbook_edit_copy.php
 * @covers modules/TawasulMarkbook/markbook_edit_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view markbook column copy page');
$I->loginAsAdmin();

// Create a test column in the first class
$I->amOnModulePage('Markbook', 'markbook_view.php');
$I->seeBreadcrumb('View Markbook');

$I->selectFromDropdown('tawasulCourseClassID', 2);
$I->click('Go', '#searchForm');

$tawasulCourseClassID = $I->grabValueFromURL('tawasulCourseClassID');

// Add a column
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Column');

$formValues = array(
    'name'                     => 'Column to Copy',
    'description'              => 'This column will be copied.',
    'type'                     => 'Homework',
    'attainment'               => 'N',
    'effort'                   => 'N',
    'comment'                  => 'N',
    'uploadedResponse'         => 'N',
    'viewableStudents'         => 'N',
    'viewableParents'          => 'N',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$tawasulMarkbookColumnID = $I->grabEditIDFromURL();

// Navigate to Edit Markbook page
$I->amOnModulePage('Markbook', 'markbook_edit.php', array('tawasulCourseClassID' => $tawasulCourseClassID));

// Verify the copy form exists
$I->see('Copy Markbook Columns');
$I->seeElement('select[name="tawasulMarkbookCopyClassID"]');

// Test Copy Action ------------------------------------

// Select a different class to copy to
$I->selectFromDropdown('tawasulMarkbookCopyClassID', 1);

// Submit the copy form
$I->submitForm('#content form', [], 'Submit');

// Should redirect to markbook_edit_copy.php
$I->seeInCurrentUrl('markbook_edit_copy.php');
$I->dontSeeErrors();

// Clean up - Delete the column
$I->amOnModulePage('Markbook', 'markbook_edit_delete.php', array(
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulMarkbookColumnID' => $tawasulMarkbookColumnID
));

$I->click('Delete');
$I->seeSuccessMessage();

