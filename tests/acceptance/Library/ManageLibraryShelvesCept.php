<?php
/**
 * @covers modules/TawasulLibrary/library_manage_shelves.php
 * @covers modules/TawasulLibrary/library_manage_shelves_add.php
 * @covers modules/TawasulLibrary/library_manage_shelves_edit.php
 * @covers modules/TawasulLibrary/library_manage_shelves_delete.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('add, edit and delete a library shelf');
$I->loginAsAdmin();
$I->amOnModulePage('Library', 'library_manage_shelves.php');
$I->seeBreadcrumb('Manage Library Shelves');

// Add ------------------------------------------------
$I->click('Add', 'a');
$I->seeBreadcrumb('Add');

$I->selectFromDropdown('type', 1);
$I->selectFromDropdown('field', 1);

$formValues = array(
    'shelfName' => 'Test Shelf',
    'active' => 'Y',
    'shuffle' => 'N',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

$tawasulLibraryShelfID = $I->grabEditIDFromURL();

// Edit ------------------------------------------------
$I->amOnModulePage('Library', 'library_manage_shelves_edit.php', array(
    'tawasulLibraryShelfID' => $tawasulLibraryShelfID
));
$I->seeBreadcrumb('Edit');

$I->seeInFormFields('#content form', array(
    'name' => 'Test Shelf',
));

$formValues = array(
    'name' => 'Updated Shelf',
);

$I->submitForm('#content form', $formValues, 'Submit');
$I->seeSuccessMessage();

// Delete ------------------------------------------------
$I->amOnModulePage('Library', 'library_manage_shelves_delete.php', array(
    'tawasulLibraryShelfID' => $tawasulLibraryShelfID
));

$I->click('Delete');
$I->seeSuccessMessage();
