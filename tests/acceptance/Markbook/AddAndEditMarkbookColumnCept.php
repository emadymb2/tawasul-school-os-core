<?php
/**
 * @covers modules/TawasulMarkbook/markbook_view.php
 * @covers modules/TawasulMarkbook/markbook_edit_add.php
 * @covers modules/TawasulMarkbook/markbook_edit_edit.php
 * @covers modules/TawasulMarkbook/markbook_edit_data.php
 * @covers modules/TawasulMarkbook/markbook_edit_delete.php
 * @covers modules/TawasulSchoolAdmin/markbookSettings.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('create a markbook column and enter data');
$I->loginAsAdmin();


// Change User Settings ---------------------------------
$I->amOnModulePage('School Admin', 'markbookSettings.php');
$originalMarkbookSettings = $I->grabAllFormValues();

$newMarkbookSettings = array_replace($originalMarkbookSettings, array(
    'enableEffort'          => 'Y',
    'enableRubrics'         => 'Y',
    'enableColumnWeighting' => 'Y',
    'enableRawAttainment'   => 'Y',
    'enableGroupByTerm'     => 'Y',
));

$I->submitForm('#content form', $newMarkbookSettings, 'Submit');
$I->seeSuccessMessage();
$I->seeInFormFields('#content form', $newMarkbookSettings);


// Select Markbook ------------------------------------------------
$I->amOnModulePage('Markbook', 'markbook_view.php');
$I->seeBreadcrumb('View Markbook');

$I->selectFromDropdown('tawasulCourseClassID', 2);
$I->click('Go', '#searchForm');


// Add Column ------------------------------------------------

$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Column');

$formValues = array(
    'name'                     => 'Test Column',
    'description'              => 'This is a test.',
    'type'                     => 'Homework',
    'attainment'               => 'N',
    'effort'                   => 'N',
    'comment'                  => 'N',
    'uploadedResponse'         => 'N',
    'viewableStudents'         => 'N',
    'viewableParents'          => 'N',
    'completeDate'             => '2001-01-01',
);

$I->attachFile('file', 'attachment.jpg');

$date = $I->grabAttributeFrom('#date', 'value');

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$tawasulMarkbookColumnID = $I->grabEditIDFromURL();
$tawasulCourseClassID = $I->grabValueFromURL('tawasulCourseClassID');

$file = $I->grabFromDatabase('tawasulMarkbookColumn', 'attachment', ['tawasulMarkbookColumnID' => $tawasulMarkbookColumnID]);
$I->assertNotEmpty($file);

// Edit ------------------------------------------------
$I->amOnModulePage('Markbook', 'markbook_edit_edit.php', array('tawasulMarkbookColumnID' => $tawasulMarkbookColumnID, 'tawasulCourseClassID' => $tawasulCourseClassID));
$I->seeBreadcrumb('Edit Column');

$I->seeInFormFields('#content form', $formValues);
$I->seeInField('date', $date);
$I->seeFieldIsNotEmpty('#attachment');

$editFormValues = array(
    'name'                     => 'Test Column!',
    'description'              => 'This is also a test.',
    'type'                     => 'Essay',
    'attainment'               => 'Y',
    'attainmentRawMax'         => '42.00',
    'attainmentWeighting'      => '2.00',
    'effort'                   => 'Y',
    'comment'                  => 'Y',
    'uploadedResponse'         => 'Y',
    'viewableStudents'         => 'Y',
    'viewableParents'          => 'Y',
    'completeDate'             => '2001-01-01',
);

$I->selectOption('tawasulScaleIDAttainment', '00004');
$I->selectOption('tawasulScaleIDEffort', '00009');

$I->selectOption('tawasulRubricIDAttainment', '00000238');
$I->selectOption('tawasulRubricIDEffort', '00000238');

$I->submitForm('#content form', $editFormValues, 'Submit');
$I->seeSuccessMessage();

$tawasulMarkbookColumnID = $I->grabValueFromURL('tawasulMarkbookColumnID');

// Verify Column ------------------------------------------------

$I->seeInFormFields('#content form', $editFormValues);

$I->seeOptionIsSelected('tawasulScaleIDAttainment', 'Percentage');
$I->seeOptionIsSelected('tawasulScaleIDEffort', 'Completion');
$I->seeFieldIsNotEmpty('#tawasulRubricIDAttainment');
$I->seeFieldIsNotEmpty('#tawasulRubricIDEffort');

$I->clickNavigation('Enter Data');

// Enter Data ------------------------------------------------

$I->seeBreadcrumb('Enter Marks');

$I->see('More info', 'a');

$I->fillField('1-attainmentValueRaw', '21');
$I->selectOption('1-attainmentValue', '80%');
$I->selectOption('1-effortValue', 'Late');
$I->fillField('comment1', 'Test comment.');
$I->attachFile('response1', 'attachment.jpg');

$I->click('Submit');

// Verify Data ------------------------------------------------

$I->seeInField('1-attainmentValueRaw', '21');
$I->seeOptionIsSelected('1-attainmentValue', '80%');
$I->seeOptionIsSelected('1-effortValue', 'Late');
$I->seeInField('comment1', 'Test comment.');
$I->seeFieldIsNotEmpty('#attachment1');

$I->seeInField('completeDate', '2001-01-01');

// Remove Attachment ------------------------------------------------
$I->amOnModulePage('Markbook', 'markbook_edit_edit.php', array('tawasulMarkbookColumnID' => $tawasulMarkbookColumnID, 'tawasulCourseClassID' => $tawasulCourseClassID));

$I->fillField('attachment', '');
$I->submitForm('#content form', $editFormValues, 'Submit');

$I->seeInDatabase('tawasulMarkbookColumn', ['tawasulMarkbookColumnID' => $tawasulMarkbookColumnID, 'attachment' => '']);

// Edit - File Upload ------------------------------------------------
$I->amOnModulePage('Markbook', 'markbook_edit_edit.php', array('tawasulMarkbookColumnID' => $tawasulMarkbookColumnID, 'tawasulCourseClassID' => $tawasulCourseClassID));

$I->attachFile('file', 'attachment2.png');
$I->submitForm('#content form', $editFormValues, 'Submit');
$I->seeSuccessMessage();

$file2 = $I->grabFromDatabase('tawasulMarkbookColumn', 'attachment', ['tawasulMarkbookColumnID' => $tawasulMarkbookColumnID]);
$I->assertNotEmpty($file2);

// Delete Markbook -----------------------------------------------

$urlParams = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulMarkbookColumnID' => $tawasulMarkbookColumnID);
$I->amOnModulePage('Markbook', 'markbook_edit_delete.php', $urlParams );

$I->click('Delete');
$I->seeSuccessMessage();

// Force Cleanup (for failed tests) ------------------------------

$I->deleteFromDatabase('tawasulMarkbookEntry', ['tawasulMarkbookColumnID' => $tawasulMarkbookColumnID]);

// Restore Original Settings -----------------------------------

$I->amOnModulePage('School Admin', 'markbookSettings.php');
$I->submitForm('#content form', $originalMarkbookSettings, 'Submit');
$I->seeSuccessMessage();
$I->seeInFormFields('#content form', $originalMarkbookSettings);
