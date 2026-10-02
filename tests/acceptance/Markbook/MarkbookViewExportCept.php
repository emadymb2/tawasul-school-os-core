<?php
/**
 * @covers modules/TawasulMarkbook/markbook_viewExportContents.php
 * @covers modules/TawasulMarkbook/markbook_viewExportAllContents.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('export markbook data');
$I->loginAsAdmin();

// Get a course class ID from the database
$tawasulCourseClassID = $I->grabFromDatabase('tawasulCourseClass', 'tawasulCourseClassID', []);

if (empty($tawasulCourseClassID)) {
    $I->comment('No course classes found, skipping export tests');
    return;
}

// Get a markbook column ID from the database
$tawasulMarkbookColumnID = $I->grabFromDatabase('tawasulMarkbookColumn', 'tawasulMarkbookColumnID', [
    'tawasulCourseClassID' => $tawasulCourseClassID
]);

// Test Export Single Column --------------------------

if (!empty($tawasulMarkbookColumnID)) {
    $I->amOnModulePage('Markbook', 'markbook_viewExport.php', [
        'tawasulMarkbookColumnID' => $tawasulMarkbookColumnID,
        'tawasulCourseClassID' => $tawasulCourseClassID,
        'return' => 'markbook_view.php'
    ]);
    
    // This page loads the export contents
    $I->dontSeeErrors();
}

// Test Export All Columns ----------------------------

$I->amOnModulePage('Markbook', 'markbook_viewExportAll.php', [
    'tawasulCourseClassID' => $tawasulCourseClassID,
    'return' => 'markbook_view.php'
]);

// This page loads the export all contents
$I->dontSeeErrors();

