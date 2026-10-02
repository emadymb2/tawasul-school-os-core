<?php
/**
 * @covers modules/TawasulLibrary/library_manage_catalog.php
 * @covers modules/TawasulLibrary/library_manage_catalog_add.php
 * @covers modules/TawasulLibrary/library_manage_catalog_edit.php
 * @covers modules/TawasulLibrary/library_manage_catalog_delete.php
 * @covers modules/TawasulLibrary/library_manage_catalog_duplicate.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add, edit and delete a catalog item');
$I->loginAsAdmin();
$I->amOnModulePage('Library', 'library_manage_catalog.php');
$I->seeBreadcrumb('Manage Catalog');

// Add ------------------------------------------------
$I->click('Add', 'a');
$I->seeBreadcrumb('Add');

$I->selectFromDropdown('tawasulLibraryTypeID', 1);
$I->selectFromDropdown('tawasulSpaceID', 1);

$formValues = array(
    'name' => 'Test Catalog Item',
    'producer' => 'Test Producer',
    'idCheck' => 'TEST001',
    'vendor' => 'Test Vendor',
    'purchaseDate' => date('Y-m-d'),
    'invoiceNumber' => 'INV001',
    'borrowable' => 'Y',
    'status' => 'Available',
    'replacement' => 'N',
    'imageType' => 'File',
);

$I->attachFile('imageFile', 'attachment.jpg');
$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$tawasulLibraryItemID = $I->grabEditIDFromURL();
$file = $I->grabFromDatabase('tawasulLibraryItem', 'imageLocation', ['tawasulLibraryItemID' => $tawasulLibraryItemID]);
$I->assertNotEmpty($file);

// Edit ------------------------------------------------
$I->amOnModulePage('Library', 'library_manage_catalog_edit.php', array(
    'tawasulLibraryItemID' => $tawasulLibraryItemID
));
$I->seeBreadcrumb('Edit');

$I->seeInFormFields('#content form', array(
    'name' => 'Test Catalog Item',
));

$formValues = array(
    'name' => 'Updated Catalog Item',
    'producer' => 'Updated Producer',
    'imageType' => '',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$I->seeInDatabase('tawasulLibraryItem', ['tawasulLibraryItemID' => $tawasulLibraryItemID, 'imageLocation' => '']);

// Edit - File Upload ------------------------------------------------
$I->amOnModulePage('Library', 'library_manage_catalog_edit.php', array(
    'tawasulLibraryItemID' => $tawasulLibraryItemID
));

$I->selectOption('imageType', 'File');
$I->attachFile('imageFile', 'attachment2.png');
$I->submitForm('#content form', [], 'Submit');
$I->seeSuccessMessage();

$file2 = $I->grabFromDatabase('tawasulLibraryItem', 'imageLocation', ['tawasulLibraryItemID' => $tawasulLibraryItemID]);
$I->assertNotEmpty($file2);

// Duplicate ------------------------------------------------
$I->amOnModulePage('Library', 'library_manage_catalog_duplicate.php', array(
    'tawasulLibraryItemID' => $tawasulLibraryItemID
));
$I->seeBreadcrumb('Duplicate');

// Step 1 - Select number of copies
$I->selectFromDropdown('number', 1);
$I->submitForm('#content form', [], 'Submit');

// Step 2 - Enter IDs for duplicates
$duplicateValues = array(
    'id1' => 'DUP' . time(),
);

$I->submitForm('#content form', $duplicateValues, 'Submit');
$I->seeSuccessMessage();

// Delete ------------------------------------------------
$I->amOnModulePage('Library', 'library_manage_catalog_delete.php', array(
    'tawasulLibraryItemID' => $tawasulLibraryItemID
));

$I->click('Delete');
$I->seeSuccessMessage();
