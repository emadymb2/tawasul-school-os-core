<?php
/**
 * @covers modules/TawasulMarkbook/markbook_view.php
 * @covers modules/TawasulMarkbook/markbook_edit_targets.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('view personalised attainment targets page');
$I->loginAsAdmin();

// Navigate to Markbook View
$I->amOnModulePage('Markbook', 'markbook_view.php');
$I->seeBreadcrumb('View Markbook');

$I->selectFromDropdown('tawasulCourseClassID', 2);
$I->click('Go', '#searchForm');

$tawasulCourseClassID = $I->grabValueFromURL('tawasulCourseClassID');

// Navigate to Set Targets page
$I->amOnModulePage('Markbook', 'markbook_edit_targets.php', array('tawasulCourseClassID' => $tawasulCourseClassID));
$I->seeBreadcrumb('Set Personalised Attainment Targets');

// Verify the page loads correctly
$I->see('Target Scale');
$I->see('Student');
$I->see('Attainment Target');
$I->seeElement('select[name="tawasulScaleIDTarget"]');

