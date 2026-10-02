<?php
/**
 * @covers modules/TawasulMarkbook/markbook_view.php
 * @covers modules/TawasulMarkbook/markbook_edit_addMulti.php
 * @covers modules/TawasulMarkbook/markbook_edit.php
 * @covers modules/TawasulMarkbook/markbook_edit_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add multiple markbook columns across classes');
$I->loginAsAdmin();

// Navigate to Markbook Edit page
$I->amOnModulePage('Markbook', 'markbook_view.php');
$I->seeBreadcrumb('View Markbook');

$I->selectFromDropdown('tawasulCourseClassID', 2);
$I->click('Go', '#searchForm');

$tawasulCourseClassID = $I->grabValueFromURL('tawasulCourseClassID');

// Add Multiple Columns ------------------------------------------------
$I->amOnModulePage('Markbook', 'markbook_edit_addMulti.php', array('tawasulCourseClassID' => $tawasulCourseClassID));
$I->seeBreadcrumb('Add Multiple Columns');

// Select multiple classes (including current class)
$I->selectFromDropdown('tawasulCourseClassIDMulti', 1);
$I->selectFromDropdown('tawasulCourseClassIDMulti', 2);

$formValues = array(
    'name'                     => 'Multi Test Column',
    'description'              => 'This is a multi-class test column.',
    'type'                     => 'Homework',
    'attainment'               => 'N',
    'effort'                   => 'N',
    'comment'                  => 'N',
    'uploadedResponse'         => 'N',
    'viewableStudents'         => 'N',
    'viewableParents'          => 'N',
);

$I->attachFile('file', 'attachment.txt');
$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

// Verify column was created by navigating back to markbook edit
$I->amOnModulePage('Markbook', 'markbook_edit.php', array('tawasulCourseClassID' => $tawasulCourseClassID));
$I->see('Multi Test Column');

// Get the column ID for cleanup
$tawasulMarkbookColumnID = $I->grabFromDatabase('tawasulMarkbookColumn', 'tawasulMarkbookColumnID', array('name' => 'Multi Test Column', 'tawasulCourseClassID' => $tawasulCourseClassID));

$file = $I->grabFromDatabase('tawasulMarkbookColumn', 'attachment', ['tawasulMarkbookColumnID' => $tawasulMarkbookColumnID]);
$I->assertNotEmpty($file);

// Clean up - Delete the created column
$I->amOnModulePage('Markbook', 'markbook_edit_delete.php', array(
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'tawasulMarkbookColumnID' => $tawasulMarkbookColumnID
));

$I->click('Delete');
$I->seeSuccessMessage();

// Cleanup ------------------------------------------------
$I->deleteFile('../'.$file);
