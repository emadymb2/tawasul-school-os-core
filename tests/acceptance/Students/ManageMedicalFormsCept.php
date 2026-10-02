<?php
/**
 * @covers modules/TawasulStudents/medicalForm_manage.php
 * @covers modules/TawasulStudents/medicalForm_manage_add.php
 * @covers modules/TawasulStudents/medicalForm_manage_edit.php
 * @covers modules/TawasulStudents/medicalForm_manage_delete.php
 * @covers modules/TawasulStudents/medicalForm_manage_condition_add.php
 * @covers modules/TawasulStudents/medicalForm_manage_condition_edit.php
 * @covers modules/TawasulStudents/medicalForm_manage_condition_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage medical forms with nested conditions');
$I->loginAsAdmin();
$I->amOnModulePage('Students', 'medicalForm_manage.php');
$I->seeBreadcrumb('Manage Medical Forms');

// Search Test -----------------------------------------

$I->fillField('search', 'test');
$I->submitForm('#filter', []);
$I->dontSeeErrors();

$I->deleteFromDatabase('tawasulPersonMedical', ['tawasulPersonID' => '0000002746']);

// Add Medical Form ------------------------------------

$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Medical Form');

$I->selectFromDropdown('tawasulPersonID', 1);

$formValues = array(
    'longTermMedication' => 'Y',
    'longTermMedicationDetails' => 'Test medication details',
    'comment' => 'Test medical comment',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$tawasulPersonMedicalID = $I->grabEditIDFromURL();

// Edit Medical Form -----------------------------------

$I->amOnModulePage('Students', 'medicalForm_manage_edit.php', array('tawasulPersonMedicalID' => $tawasulPersonMedicalID));
$I->seeBreadcrumb('Edit Medical Form');

$I->seeInFormFields('#content form', array(
    'longTermMedication' => 'Y',
    'longTermMedicationDetails' => 'Test medication details',
    'comment' => 'Test medical comment',
));

$formValues = array(
    'longTermMedication' => 'N',
    'comment' => 'Updated medical comment',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

// Add Medical Condition -------------------------------

$I->amOnModulePage('Students', 'medicalForm_manage_edit.php', array('tawasulPersonMedicalID' => $tawasulPersonMedicalID));

$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Condition');

$I->selectFromDropdown('name', 1);
$I->selectFromDropdown('tawasulAlertLevelID', 1);

$conditionValues = array(
    'triggers' => 'Test triggers',
    'reaction' => 'Test reaction',
    'response' => 'Test response',
    'medication' => 'Test medication',
    'comment' => 'Test condition comment',
);

$I->attachFile('attachment', 'attachment.txt');
$I->submitForm('#content form', $conditionValues, 'Submit');
$I->seeSuccessMessage();

$tawasulPersonMedicalConditionID = $I->grabEditIDFromURL();
$file = $I->grabFromDatabase('tawasulPersonMedicalCondition', 'attachment', ['tawasulPersonMedicalConditionID' => $tawasulPersonMedicalConditionID]);
$I->assertNotEmpty($file);

// Edit Medical Condition ------------------------------

$I->amOnModulePage('Students', 'medicalForm_manage_condition_edit.php', array(
    'tawasulPersonMedicalID' => $tawasulPersonMedicalID,
    'tawasulPersonMedicalConditionID' => $tawasulPersonMedicalConditionID
));
$I->seeBreadcrumb('Edit Condition');

$I->seeInFormFields('#content form', array(
    'triggers' => 'Test triggers',
    'reaction' => 'Test reaction',
    'response' => 'Test response',
    'medication' => 'Test medication',
    'comment' => 'Test condition comment',
));

$conditionValues = array(
    'triggers' => 'Updated triggers',
    'reaction' => 'Updated reaction',
);

$I->fillField('attachment', '');
$I->submitForm('#content form', $conditionValues, 'Submit');
$I->seeSuccessMessage();

$tawasulPersonMedicalConditionID = $I->grabValueFromURL('tawasulPersonMedicalConditionID');
$I->seeInDatabase('tawasulPersonMedicalCondition', ['tawasulPersonMedicalConditionID' => $tawasulPersonMedicalConditionID, 'attachment' => '']);

// Edit Medical Condition - File Upload ----------------

$I->amOnModulePage('Students', 'medicalForm_manage_condition_edit.php', array(
    'tawasulPersonMedicalID' => $tawasulPersonMedicalID,
    'tawasulPersonMedicalConditionID' => $tawasulPersonMedicalConditionID
));

$I->attachFile('input[type="file"][name="attachment"]', 'attachment2.png');
$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

$file2 = $I->grabFromDatabase('tawasulPersonMedicalCondition', 'attachment', ['tawasulPersonMedicalConditionID' => $tawasulPersonMedicalConditionID]);
$I->assertNotEmpty($file2);

// Delete Medical Condition ----------------------------

$I->amOnModulePage('Students', 'medicalForm_manage_condition_delete.php', array(
    'tawasulPersonMedicalID' => $tawasulPersonMedicalID,
    'tawasulPersonMedicalConditionID' => $tawasulPersonMedicalConditionID
));

$I->click('Delete');
$I->seeSuccessMessage();

// Delete Medical Form ---------------------------------

$I->amOnModulePage('Students', 'medicalForm_manage_delete.php', array('tawasulPersonMedicalID' => $tawasulPersonMedicalID));

$I->click('Delete');
$I->seeSuccessMessage();

