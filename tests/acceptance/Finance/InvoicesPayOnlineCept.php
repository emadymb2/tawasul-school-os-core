<?php
/**
 * @covers modules/TawasulFinance/invoices_payOnline.php
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('check online payment page');
$I->loginAsAdmin();

$tawasulSchoolYearID = $I->grabFromDatabase('tawasulSchoolYear', 'tawasulSchoolYearID', ['status' => 'Current']);

// Get an invoicee
$tawasulFinanceInvoiceeID = $I->grabFromDatabase('tawasulFinanceInvoicee', 'tawasulFinanceInvoiceeID', []);

if (!$tawasulFinanceInvoiceeID) {
    $I->comment('No invoicees found, skipping pay online test');
    return;
}

// Get admin person ID for creator field
$tawasulPersonIDCreator = $I->grabFromDatabase('tawasulPerson', 'tawasulPersonID', ['username' => 'admin']);

// Create an issued invoice for payment
$invoiceKey = uniqid('test_', true);
$tawasulFinanceInvoiceID = $I->haveInDatabase('tawasulFinanceInvoice', [
    'tawasulSchoolYearID' => $tawasulSchoolYearID,
    'tawasulFinanceInvoiceeID' => $tawasulFinanceInvoiceeID,
    'invoiceTo' => 'Family',
    'billingScheduleType' => 'Ad Hoc',
    'status' => 'Issued',
    'invoiceIssueDate' => date('Y-m-d'),
    'invoiceDueDate' => date('Y-m-d', strtotime('+30 days')),
    'key' => $invoiceKey,
    'notes' => 'Test invoice for online payment',
    'tawasulPersonIDCreator' => $tawasulPersonIDCreator,
]);

// Add a fee to the invoice
$I->haveInDatabase('tawasulFinanceInvoiceFee', [
    'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID,
    'feeType' => 'Ad Hoc',
    'name' => 'Test Fee',
    'fee' => 100.00,
    'tawasulFinanceFeeCategoryID' => 1,
    'sequenceNumber' => 1,
]);

// Navigate to the pay online page
$I->amOnModulePage('Finance', 'invoices_payOnline.php', [
    'tawasulFinanceInvoiceID' => $tawasulFinanceInvoiceID,
    'key' => $invoiceKey
]);

$I->dontSeeErrors();
