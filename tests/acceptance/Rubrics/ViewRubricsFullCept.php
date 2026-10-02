<?php
/**
 * @covers modules/TawasulRubrics/rubrics_view_full.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('View full rubric details');
$I->loginAsAdmin();

// Get an existing rubric ID
$tawasulRubricID = $I->grabFromDatabase('tawasulRubric', 'tawasulRubricID', []);

$I->amOnModulePage('Rubrics', 'rubrics_view_full.php', ['tawasulRubricID' => $tawasulRubricID]);

