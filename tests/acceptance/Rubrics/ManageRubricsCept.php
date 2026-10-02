<?php
/**
 * @covers modules/TawasulRubrics/rubrics.php
 * @covers modules/TawasulRubrics/rubrics_add.php
 * @covers modules/TawasulRubrics/rubrics_edit.php
 * @covers modules/TawasulRubrics/rubrics_delete.php
 * @covers modules/TawasulRubrics/rubrics_duplicate.php
 * @covers modules/TawasulRubrics/rubrics_edit_editRowsColumns.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add, edit, duplicate and delete a rubric');
$I->loginAsAdmin();
$I->amOnModulePage('Rubrics', 'rubrics.php');
$I->seeBreadcrumb('Manage Rubrics');

// Add ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Rubric');

$I->selectFromDropdown('scope', 1);
$I->selectFromDropdown('tawasulDepartmentID', 1);

$formValues = array(
    'name' => 'Test Rubric',
    'active' => 'Y',
    'category' => 'Test Category',
    'description' => 'Test rubric description',
    'rows' => '2',
    'columns' => '3',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$tawasulRubricID = $I->grabValueFromURL('tawasulRubricID');

// Edit ------------------------------------------------
$I->amOnModulePage('Rubrics', 'rubrics_edit.php', array('tawasulRubricID' => $tawasulRubricID));
$I->seeBreadcrumb('Edit Rubric');

$I->seeInFormFields('#content form', array(
    'name' => 'Test Rubric',
    'active' => 'Y',
));

$formValues = array(
    'name' => 'Updated Test Rubric',
    'active' => 'N',
    'category' => 'Updated Category',
    'description' => 'Updated description',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

// Edit Rows & Columns --------------------------------
$I->click('Edit Rows & Columns');
$I->seeBreadcrumb('Edit Rubric Rows & Columns');

// Update row titles and colors
$I->fillField('rowTitle[0]', 'Updated Row 1');
$I->fillField('rowTitle[1]', 'Updated Row 2');

// Update column titles
$I->fillField('columnTitle[0]', 'Level 1');
$I->fillField('columnTitle[1]', 'Level 2');
$I->fillField('columnTitle[2]', 'Level 3');

$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

// Duplicate -------------------------------------------
$I->amOnModulePage('Rubrics', 'rubrics_duplicate.php', array('tawasulRubricID' => $tawasulRubricID));
$I->seeBreadcrumb('Duplicate Rubric');

$I->selectFromDropdown('scope', 1);
$I->selectFromDropdown('tawasulDepartmentID', 1);

$formValues = array(
    'name' => 'Duplicated Test Rubric',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

// Delete Original -------------------------------------
$I->amOnModulePage('Rubrics', 'rubrics_delete.php', array('tawasulRubricID' => $tawasulRubricID));

$I->click('Delete');
$I->seeSuccessMessage();