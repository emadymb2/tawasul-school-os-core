<?php
/**
 * @covers modules/TawasulMarkbook/markbook_view_rubric.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view markbook rubric');
$I->loginAsAdmin();

// Get a course class ID from the database
$tawasulCourseClassID = $I->grabFromDatabase('tawasulCourseClass', 'tawasulCourseClassID', []);

if (empty($tawasulCourseClassID)) {
    $I->comment('No course classes found, skipping rubric test');
    return;
}

// Get a markbook column with a rubric
$tawasulMarkbookColumnID = $I->grabFromDatabase('tawasulMarkbookColumn', 'tawasulMarkbookColumnID', [
    'tawasulCourseClassID' => $tawasulCourseClassID
]);

if (empty($tawasulMarkbookColumnID)) {
    $I->comment('No markbook columns found, skipping rubric test');
    return;
}

// Get the rubric ID from the column
$tawasulRubricID = $I->grabFromDatabase('tawasulMarkbookColumn', 'tawasulRubricIDAttainment', [
    'tawasulMarkbookColumnID' => $tawasulMarkbookColumnID
]);

if (empty($tawasulRubricID)) {
    $I->comment('No rubric found for this column, skipping rubric test');
    return;
}

// Get a student from the class
$tawasulPersonID = $I->grabFromDatabase('tawasulCourseClassPerson', 'tawasulPersonID', [
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'role' => 'Student'
]);

if (empty($tawasulPersonID)) {
    $I->comment('No students found in this class, skipping rubric test');
    return;
}

// Test Rubric View ------------------------------------

$I->amOnPage('/fullscreen.php?q=/modules/TawasulMarkbook/markbook_view_rubric.php&tawasulRubricID='.$tawasulRubricID.'&tawasulCourseClassID='.$tawasulCourseClassID.'&tawasulMarkbookColumnID='.$tawasulMarkbookColumnID.'&tawasulPersonID='.$tawasulPersonID.'&mark=FALSE&type=attainment&width=1100&height=550');
$I->dontSeeErrors();
