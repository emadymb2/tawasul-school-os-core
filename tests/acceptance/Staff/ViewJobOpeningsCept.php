<?php
/**
 * @covers modules/TawasulStaff/applicationForm_jobOpenings_view.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View job openings');
$I->loginAsAdmin();

// Database Seed  ------------------------------

$tawasulStaffJobOpeningID = $I->haveInDatabase('tawasulStaffJobOpening', [
    'type'        => 'Teaching',
    'jobTitle'    => 'Test Job Title',
    'dateOpen'    => date('Y-m-d'),
    'active'      => 'Y',
    'description' => 'Test job description',
    'tawasulPersonIDCreator' => 1,
]);

// Test View Page  ------------------------------

$I->amOnModulePage('Staff', 'applicationForm_jobOpenings_view.php', ['tawasulStaffJobOpeningID' => $tawasulStaffJobOpeningID]);
$I->seeBreadcrumb('Application Form');


// Database Cleanup  ------------------------------

$I->deleteFromDatabase('tawasulStaffJobOpening', ['tawasulStaffJobOpeningID' => $tawasulStaffJobOpeningID]);
