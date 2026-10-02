<?php
/**
 * @covers modules/TawasulActivities/activities_categories.php
 * @covers modules/TawasulActivities/activities_categories_add.php
 * @covers modules/TawasulActivities/activities_categories_edit.php
 * @covers modules/TawasulActivities/activities_categories_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('manage activity categories');
$I->loginAsAdmin();
$I->amOnModulePage('Activities', 'activities_categories.php');
$I->seeBreadcrumb('Manage Categories');

// Add ------------------------------------------------
$I->clickNavigation('Add');
$I->seeBreadcrumb('Add Category');

$uniqueID = uniqid();
$I->fillField('name', 'Test Category ' . $uniqueID);
$I->fillField('nameShort', 'TC' . substr($uniqueID, -6));
$I->attachFile('backgroundImageFile', 'attachment.jpg');
$I->submitForm('#content form', []);

$I->seeSuccessMessage();

$tawasulActivityCategoryID = $I->grabEditIDFromURL(); 
$file = $I->grabFromDatabase('tawasulActivityCategory', 'backgroundImage', ['tawasulActivityCategoryID' => $tawasulActivityCategoryID]);
$I->assertNotEmpty($file);

// Edit  - File Delete ------------------------------------
$I->amOnModulePage('Activities', 'activities_categories.php');
$I->click('Edit', "//td[contains(text(),'Test Category " . $uniqueID . "')]/..");
$I->seeBreadcrumb('Edit Category');

$I->fillField('name', 'Test Category Edited ' . $uniqueID);
$I->fillField('nameShort', 'TCE' . substr($uniqueID, -5));
$I->fillField('backgroundImage', '');
$I->submitForm('#content form', []);

$I->seeSuccessMessage();

$tawasulActivityCategoryID = $I->grabValueFromURL('tawasulActivityCategoryID'); 
$I->seeInDatabase('tawasulActivityCategory', ['tawasulActivityCategoryID' => $tawasulActivityCategoryID, 'backgroundImage' => '']);


// Edit - File Upload ------------------------------------
$I->amOnModulePage('Activities', 'activities_categories_edit.php', ['tawasulActivityCategoryID' => $tawasulActivityCategoryID]);

$I->attachFile('backgroundImageFile', 'attachment2.png');
$I->submitForm('#content form', []);

$I->seeSuccessMessage();
$file2 = $I->grabFromDatabase('tawasulActivityCategory', 'backgroundImage', ['tawasulActivityCategoryID' => $tawasulActivityCategoryID]);
$I->assertNotEmpty($file2);

// Delete ------------------------------------------------
$I->amOnModulePage('Activities', 'activities_categories.php');
$I->click('Delete', "//td[contains(text(),'Test Category Edited " . $uniqueID . "')]/..");
$I->fillField('confirm', 'Delete');
$I->click('Yes');
$I->seeSuccessMessage();
