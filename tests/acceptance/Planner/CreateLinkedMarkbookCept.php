<?php
/**
 * @covers modules/TawasulPlanner/planner_add.php
 * @covers modules/TawasulMarkbook/markbook_edit_add.php
 * @covers modules/TawasulMarkbook/markbook_edit_edit.php
 * @covers modules/TawasulMarkbook/markbook_edit_delete.php
 * @covers modules/TawasulPlanner/planner_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('create a lesson with a linked markbook column');
$I->loginAsAdmin();
$I->amOnModulePage('Planner', 'planner_add.php');

// Add Lesson ------------------------------------------------
$I->seeBreadcrumb('Add Lesson Plan');

$date = $I->grabAttributeFrom('#date', 'value');

$I->selectFromDropdown('tawasulCourseClassID', 2);
$I->fillField('name', 'Testing Markbook');
$I->fillField('timeStart', '09:00');
$I->fillField('timeEnd', '10:00');
$I->fillField('markbook', 'Y');
$I->click('Submit');

// Verify Linked Lesson ---------------------------------------

$I->see('Planner was successfully added', '.success');
$I->seeBreadcrumb('Add Column');

$tawasulPlannerEntryID = $I->grabValueFromURL('tawasulPlannerEntryID');
$I->seeInField('tawasulPlannerEntryID', str_pad($tawasulPlannerEntryID, 14, '0', STR_PAD_LEFT));
$I->seeInField('name', 'Testing Markbook');

// Add Column ------------------------------------------------

$I->fillField('description', 'Linked to Planner Lesson');
$I->selectFromDropdown('type', 2);
$I->seeInField('date', $date);
$I->fillField('attainment', 'Y');
$I->fillField('effort', 'N');
$I->fillField('viewableStudents', 'N');
$I->fillField('viewableParents', 'N');
$I->click('Submit');

// Verify Column ------------------------------------------------

$I->see('Your request was completed successfully.', '.success');
$I->click('here', 'a');
$I->seeBreadcrumb('Edit Column');

$tawasulMarkbookColumnID = $I->grabValueFromURL('tawasulMarkbookColumnID');
$tawasulCourseClassID = $I->grabValueFromURL('tawasulCourseClassID');

$I->seeInField('name', 'Testing Markbook');
$I->seeInField('description', 'Linked to Planner Lesson');
$I->seeInField('attainment', 'Y');
$I->seeInField('effort', 'N');
$I->seeInField('viewableStudents', 'N');
$I->seeInField('viewableParents', 'N');

// Delete Markbook -----------------------------------------------

$urlParams = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulMarkbookColumnID' => $tawasulMarkbookColumnID);
$I->amOnModulePage('Markbook', 'markbook_edit_delete.php', $urlParams );

$I->click('Delete');
$I->see('Your request was completed successfully.', '.success');

// Delete Planner ------------------------------------------------

$urlParams = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPlannerEntryID' => $tawasulPlannerEntryID, 'viewBy' => 'class');
$I->amOnModulePage('Planner', 'planner_delete.php', $urlParams );

$I->click('Delete');
$I->see('Your request was completed successfully.', '.success');

// Force Cleanup (for failed tests) ------------------------------

$I->deleteFromDatabase('tawasulMarkbookColumn', ['tawasulMarkbookColumnID' => $tawasulMarkbookColumnID]);
$I->deleteFromDatabase('tawasulPlannerEntry', ['tawasulPlannerEntryID' => $tawasulPlannerEntryID]);
