<?php
/**
 * @covers modules/TawasulFormalAssessment/externalAssessment_details.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check External Assessment Details');
$I->loginAsAdmin();

// Get a student ID from the database
$tawasulPersonID = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['status' => 'Full']);

$I->amOnModulePage('Formal Assessment', 'externalAssessment_details.php', [
    'tawasulPersonID' => $tawasulPersonID
]);
$I->seeBreadcrumb('Student Details');

// Basic Check -----------------------------------------

$I->dontSeeErrors();
