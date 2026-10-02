<?php
/**
 * @covers modules/TawasulFinance/invoices_manage_processBulkExportContents.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('export invoices in bulk');
$I->loginAsAdmin();

$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

// Create a test invoice first
$tawasulFinanceInvoiceeID = $I->grabFromDatabase('tawasulFinanceInvoicee', 'tawasulFinanceInvoiceeID', []);

if (!$tawasulFinanceInvoiceeID) {
    $I->comment('No invoicees found, skipping bulk export test');
    return;
}

// Get admin person ID for creator field
$tawasulPersonIDCreator = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['username' => 'admin']);

// Create a pending invoice for export
$tawasulFinanceInvoiceID = $I->haveInDatabase('tawasulFinanceInvoice', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID,
    'invoiceTo' => 'Family',
    'billingScheduleType' => 'Ad Hoc',
    'status' => 'Pending',
    'invoiceIssueDate' => date('Y-m-d'),
    'invoiceDueDate' => date('Y-m-d', strtotime('+30 days')),
    'notes' => 'Test invoice for export',
    'key' => uniqid('test_', true),
    'tawasulPersonIDCreator' => $tawasulPersonIDCreator,
]);

// Set the session variable for export
$I->haveHttpHeader('Cookie', 'financeInvoiceExportIDs=' . serialize([$tawasulFinanceInvoiceID]));

// Navigate to the export page
$I->amOnModulePage('Finance', 'invoices_manage_processBulkExportContents.php', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID
]);

$I->dontSeeErrors();
