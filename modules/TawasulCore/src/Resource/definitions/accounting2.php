<?php
/*
The rest of the accounting core: payables and treasury, payroll, fixed assets,
fee plans and discounts, and the audit trail.

These are plain reference and transaction tables, so the generic CRUD serves
them. The two exceptions are deliberate:

  - purchase-bills, payment-vouchers, receipts, transfers, payroll-runs and
    depreciation all carry tawasulFinanceJournalEntryID. That column is the
    ledger document the row produced, and it is written by the posting engine,
    not by the caller, so it is withheld from 'writable' below. A body that
    includes it is ignored rather than trusted.
  - payroll-runs and staff-salaries are reference data; the money only reaches
    the ledger when a run is posted.

Definitions here are read-only for anything that must balance: only the
AccountingController can write the ledger.
*/

return [

// ---------------- Payables and treasury ----------------

'suppliers' => [
    'title' => 'Suppliers',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Chart of Accounts',
    'table' => 'tawasulFinanceSupplier',
    'primaryKey' => 'tawasulFinanceSupplierID',
    'description' => 'The vendors a school buys from, and the payable account each one settles into.',
    'select' => "SELECT tawasulFinanceSupplier.tawasulFinanceSupplierID, tawasulFinanceSupplier.name, tawasulFinanceSupplier.phone, tawasulFinanceSupplier.email, tawasulFinanceSupplier.taxNumber, tawasulFinanceSupplier.tawasulFinancePayableAccountID, tawasulFinanceSupplier.timestampCreator, payable.code AS payableAccountCode, payable.name AS payableAccountName, (SELECT COUNT(*) FROM tawasulFinancePurchaseBill WHERE tawasulFinancePurchaseBill.supplierID=tawasulFinanceSupplier.tawasulFinanceSupplierID AND tawasulFinancePurchaseBill.status<>'Cancelled') AS openBillCount, (SELECT COALESCE(SUM(tawasulFinancePurchaseBill.amount-tawasulFinancePurchaseBill.paidAmount), 0) FROM tawasulFinancePurchaseBill WHERE tawasulFinancePurchaseBill.supplierID=tawasulFinanceSupplier.tawasulFinanceSupplierID AND tawasulFinancePurchaseBill.status<>'Cancelled') AS outstandingAmount FROM tawasulFinanceSupplier LEFT JOIN tawasulFinanceAccount AS payable ON (tawasulFinanceSupplier.tawasulFinancePayableAccountID=payable.tawasulFinanceAccountID)",
    'filters' => ['tawasulFinancePayableAccountID' => 'tawasulFinanceSupplier.tawasulFinancePayableAccountID'],
    'search' => ['tawasulFinanceSupplier.name', 'tawasulFinanceSupplier.email', 'tawasulFinanceSupplier.taxNumber'],
    'sort' => ['name' => 'tawasulFinanceSupplier.name'],
    'defaultSort' => 'tawasulFinanceSupplier.name',
    'writable' => ['name', 'phone', 'email', 'taxNumber', 'tawasulFinancePayableAccountID'],
    'required' => ['name'],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulFinancePayableAccountID' => 'integer',
    ],
    'maxLength' => [
        'name' => 150,
        'phone' => 150,
        'email' => 150,
        'taxNumber' => 150,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'purchase-bills' => [
    'title' => 'Purchase Bills',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Journal Entries',
    'table' => 'tawasulFinancePurchaseBill',
    'primaryKey' => 'tawasulFinancePurchaseBillID',
    'description' => 'What the school owes a supplier. Posting one writes the accrual to the ledger, and tawasulFinanceJournalEntryID records which entry did it.',
    'select' => "SELECT tawasulFinancePurchaseBill.tawasulFinancePurchaseBillID, tawasulFinancePurchaseBill.billNumber, tawasulFinancePurchaseBill.supplierID, tawasulFinanceSupplier.name AS supplierName, tawasulFinancePurchaseBill.date, tawasulFinancePurchaseBill.dueDate, tawasulFinancePurchaseBill.tawasulFinanceExpenseAccountID, tawasulFinancePurchaseBill.tawasulFinanceCostCenterID, tawasulFinancePurchaseBill.amount, tawasulFinancePurchaseBill.taxAmount, tawasulFinancePurchaseBill.paidAmount, (tawasulFinancePurchaseBill.amount-tawasulFinancePurchaseBill.paidAmount) AS outstandingAmount, tawasulFinancePurchaseBill.description, tawasulFinancePurchaseBill.status, tawasulFinancePurchaseBill.tawasulFinanceJournalEntryID, expense.code AS expenseAccountCode, expense.name AS expenseAccountName FROM tawasulFinancePurchaseBill LEFT JOIN tawasulFinanceSupplier ON (tawasulFinancePurchaseBill.supplierID=tawasulFinanceSupplier.tawasulFinanceSupplierID) LEFT JOIN tawasulFinanceAccount AS expense ON (tawasulFinancePurchaseBill.tawasulFinanceExpenseAccountID=expense.tawasulFinanceAccountID)",
    'filters' => [
        'status' => 'tawasulFinancePurchaseBill.status',
        'supplierID' => 'tawasulFinancePurchaseBill.supplierID',
        'date' => 'tawasulFinancePurchaseBill.date',
        'tawasulFinanceCostCenterID' => 'tawasulFinancePurchaseBill.tawasulFinanceCostCenterID',
        'tawasulFinanceJournalEntryID' => 'tawasulFinancePurchaseBill.tawasulFinanceJournalEntryID',
    ],
    'search' => ['tawasulFinancePurchaseBill.billNumber', 'tawasulFinancePurchaseBill.description', 'tawasulFinanceSupplier.name'],
    'sort' => ['date' => 'tawasulFinancePurchaseBill.date', 'dueDate' => 'tawasulFinancePurchaseBill.dueDate', 'amount' => 'tawasulFinancePurchaseBill.amount', 'status' => 'tawasulFinancePurchaseBill.status'],
    'defaultSort' => 'tawasulFinancePurchaseBill.date DESC',
    'writable' => ['billNumber', 'supplierID', 'date', 'dueDate', 'tawasulFinanceExpenseAccountID', 'tawasulFinanceCostCenterID', 'amount', 'taxAmount', 'paidAmount', 'description', 'status'],
    'required' => ['billNumber', 'supplierID', 'date', 'amount', 'tawasulFinanceExpenseAccountID'],
    'enums' => ['status' => ['Open', 'Partial', 'Paid', 'Cancelled']],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'supplierID' => 'integer',
        'date' => 'date',
        'dueDate' => 'date',
        'tawasulFinanceExpenseAccountID' => 'integer',
        'tawasulFinanceCostCenterID' => 'integer',
        'amount' => 'number',
        'taxAmount' => 'number',
        'paidAmount' => 'number',
    ],
    'maxLength' => [
        'billNumber' => 30,
        'description' => 255,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'payment-vouchers' => [
    'title' => 'Payment Vouchers',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Journal Entries',
    'table' => 'tawasulFinancePaymentVoucher',
    'primaryKey' => 'tawasulFinancePaymentVoucherID',
    'description' => 'Money paid out against a purchase bill, and the ledger entry that recorded it.',
    'select' => "SELECT tawasulFinancePaymentVoucher.tawasulFinancePaymentVoucherID, tawasulFinancePaymentVoucher.voucherNumber, tawasulFinancePaymentVoucher.tawasulFinancePurchaseBillID, tawasulFinancePurchaseBill.billNumber, tawasulFinancePurchaseBill.supplierID, tawasulFinanceSupplier.name AS supplierName, tawasulFinancePaymentVoucher.tawasulFinanceCashAccountID, cash.name AS cashAccountName, tawasulFinancePaymentVoucher.date, tawasulFinancePaymentVoucher.amount, tawasulFinancePaymentVoucher.method, tawasulFinancePaymentVoucher.reference, tawasulFinancePaymentVoucher.tawasulFinanceJournalEntryID FROM tawasulFinancePaymentVoucher LEFT JOIN tawasulFinancePurchaseBill ON (tawasulFinancePaymentVoucher.tawasulFinancePurchaseBillID=tawasulFinancePurchaseBill.tawasulFinancePurchaseBillID) LEFT JOIN tawasulFinanceSupplier ON (tawasulFinancePurchaseBill.supplierID=tawasulFinanceSupplier.tawasulFinanceSupplierID) LEFT JOIN tawasulFinanceCashAccount AS cash ON (tawasulFinancePaymentVoucher.tawasulFinanceCashAccountID=cash.tawasulFinanceCashAccountID)",
    'filters' => [
        'method' => 'tawasulFinancePaymentVoucher.method',
        'date' => 'tawasulFinancePaymentVoucher.date',
        'tawasulFinancePurchaseBillID' => 'tawasulFinancePaymentVoucher.tawasulFinancePurchaseBillID',
        'tawasulFinanceCashAccountID' => 'tawasulFinancePaymentVoucher.tawasulFinanceCashAccountID',
    ],
    'search' => ['tawasulFinancePaymentVoucher.voucherNumber', 'tawasulFinancePaymentVoucher.reference', 'tawasulFinanceSupplier.name'],
    'sort' => ['date' => 'tawasulFinancePaymentVoucher.date', 'amount' => 'tawasulFinancePaymentVoucher.amount'],
    'defaultSort' => 'tawasulFinancePaymentVoucher.date DESC',
    'writable' => ['voucherNumber', 'tawasulFinancePurchaseBillID', 'tawasulFinanceCashAccountID', 'date', 'amount', 'method', 'reference'],
    'required' => ['voucherNumber', 'tawasulFinancePurchaseBillID', 'tawasulFinanceCashAccountID', 'date', 'amount'],
    'enums' => ['method' => ['Cash', 'Transfer', 'Cheque']],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulFinancePurchaseBillID' => 'integer',
        'tawasulFinanceCashAccountID' => 'integer',
        'date' => 'date',
        'amount' => 'number',
    ],
    'maxLength' => [
        'voucherNumber' => 30,
        'reference' => 60,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'receipts' => [
    'title' => 'Receipts',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Journal Entries',
    'table' => 'tawasulFinanceReceipt',
    'primaryKey' => 'tawasulFinanceReceiptID',
    'description' => 'Money received against an invoice, and the ledger entry that recorded it.',
    'select' => "SELECT tawasulFinanceReceipt.tawasulFinanceReceiptID, tawasulFinanceReceipt.receiptNumber, tawasulFinanceReceipt.tawasulFinanceInvoiceID, tawasulFinanceInvoice.invoiceIssueDate, tawasulFinanceInvoicee.tawasulPersonID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulFinanceReceipt.tawasulFinanceCashAccountID, cash.name AS cashAccountName, tawasulFinanceReceipt.date, tawasulFinanceReceipt.amount, tawasulFinanceReceipt.method, tawasulFinanceReceipt.reference, tawasulFinanceReceipt.tawasulPersonIDReceiver, tawasulFinanceReceipt.tawasulFinanceJournalEntryID FROM tawasulFinanceReceipt LEFT JOIN tawasulFinanceInvoice ON (tawasulFinanceReceipt.tawasulFinanceInvoiceID=tawasulFinanceInvoice.tawasulFinanceInvoiceID) LEFT JOIN tawasulFinanceInvoicee ON (tawasulFinanceInvoice.tawasulFinanceInvoiceeID=tawasulFinanceInvoicee.tawasulFinanceInvoiceeID) LEFT JOIN tawasulPerson ON (tawasulFinanceInvoicee.tawasulPersonID=tawasulPerson.tawasulPersonID) LEFT JOIN tawasulFinanceCashAccount AS cash ON (tawasulFinanceReceipt.tawasulFinanceCashAccountID=cash.tawasulFinanceCashAccountID)",
    'filters' => [
        'method' => 'tawasulFinanceReceipt.method',
        'date' => 'tawasulFinanceReceipt.date',
        'tawasulFinanceInvoiceID' => 'tawasulFinanceReceipt.tawasulFinanceInvoiceID',
        'tawasulFinanceCashAccountID' => 'tawasulFinanceReceipt.tawasulFinanceCashAccountID',
    ],
    'search' => ['tawasulFinanceReceipt.receiptNumber', 'tawasulFinanceReceipt.reference', 'tawasulPerson.surname', 'tawasulPerson.preferredName'],
    'sort' => ['date' => 'tawasulFinanceReceipt.date', 'amount' => 'tawasulFinanceReceipt.amount'],
    'defaultSort' => 'tawasulFinanceReceipt.date DESC',
    'writable' => ['receiptNumber', 'tawasulFinanceInvoiceID', 'tawasulFinanceCashAccountID', 'date', 'amount', 'method', 'reference', 'tawasulPersonIDReceiver'],
    'required' => ['receiptNumber', 'tawasulFinanceInvoiceID', 'tawasulFinanceCashAccountID', 'date', 'amount'],
    'enums' => ['method' => ['Cash', 'Transfer', 'Cheque', 'Card']],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulFinanceInvoiceID' => 'integer',
        'tawasulFinanceCashAccountID' => 'integer',
        'date' => 'date',
        'amount' => 'number',
        'tawasulPersonIDReceiver' => 'integer',
    ],
    'maxLength' => [
        'receiptNumber' => 30,
        'reference' => 60,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'cash-accounts' => [
    'title' => 'Cash Accounts',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Chart of Accounts',
    'table' => 'tawasulFinanceCashAccount',
    'primaryKey' => 'tawasulFinanceCashAccountID',
    'description' => 'The bank and till accounts money can move through, each linked to its ledger account.',
    'select' => "SELECT tawasulFinanceCashAccount.tawasulFinanceCashAccountID, tawasulFinanceCashAccount.name, tawasulFinanceCashAccount.type, tawasulFinanceCashAccount.bankAccountNumber, tawasulFinanceCashAccount.tawasulFinanceAccountID, tawasulFinanceCashAccount.timestampCreator, tawasulFinanceAccount.code AS accountCode, tawasulFinanceAccount.name AS accountName FROM tawasulFinanceCashAccount LEFT JOIN tawasulFinanceAccount ON (tawasulFinanceCashAccount.tawasulFinanceAccountID=tawasulFinanceAccount.tawasulFinanceAccountID)",
    'filters' => ['type' => 'tawasulFinanceCashAccount.type'],
    'search' => ['tawasulFinanceCashAccount.name', 'tawasulFinanceCashAccount.bankAccountNumber'],
    'sort' => ['name' => 'tawasulFinanceCashAccount.name'],
    'defaultSort' => 'tawasulFinanceCashAccount.name',
    'writable' => ['name', 'type', 'bankAccountNumber', 'tawasulFinanceAccountID'],
    'required' => ['name'],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulFinanceAccountID' => 'integer',
    ],
    'maxLength' => [
        'name' => 150,
        'type' => 30,
        'bankAccountNumber' => 150,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'transfers' => [
    'title' => 'Transfers',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Journal Entries',
    'table' => 'tawasulFinanceTransfer',
    'primaryKey' => 'tawasulFinanceTransferID',
    'description' => 'Money moved between two cash accounts, with the ledger entry that moved it.',
    'select' => "SELECT tawasulFinanceTransfer.tawasulFinanceTransferID, tawasulFinanceTransfer.tawasulFinanceFromCashAccountID, fromAccount.name AS fromAccountName, tawasulFinanceTransfer.toCashAccountID, toAccount.name AS toAccountName, tawasulFinanceTransfer.date, tawasulFinanceTransfer.amount, tawasulFinanceTransfer.memo, tawasulFinanceTransfer.tawasulFinanceJournalEntryID FROM tawasulFinanceTransfer LEFT JOIN tawasulFinanceCashAccount AS fromAccount ON (tawasulFinanceTransfer.tawasulFinanceFromCashAccountID=fromAccount.tawasulFinanceCashAccountID) LEFT JOIN tawasulFinanceCashAccount AS toAccount ON (tawasulFinanceTransfer.toCashAccountID=toAccount.tawasulFinanceCashAccountID)",
    'filters' => [
        'date' => 'tawasulFinanceTransfer.date',
        'tawasulFinanceFromCashAccountID' => 'tawasulFinanceTransfer.tawasulFinanceFromCashAccountID',
        'toCashAccountID' => 'tawasulFinanceTransfer.toCashAccountID',
    ],
    'search' => ['tawasulFinanceTransfer.memo'],
    'sort' => ['date' => 'tawasulFinanceTransfer.date', 'amount' => 'tawasulFinanceTransfer.amount'],
    'defaultSort' => 'tawasulFinanceTransfer.date DESC',
    'writable' => ['tawasulFinanceFromCashAccountID', 'toCashAccountID', 'date', 'amount', 'memo'],
    'required' => ['tawasulFinanceFromCashAccountID', 'toCashAccountID', 'date', 'amount'],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulFinanceFromCashAccountID' => 'integer',
        'toCashAccountID' => 'integer',
        'date' => 'date',
        'amount' => 'number',
    ],
    'maxLength' => [
        'memo' => 255,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'bank-reconciliations' => [
    'title' => 'Bank Reconciliations',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Journal Entries',
    'table' => 'tawasulFinanceBankReconciliation',
    'primaryKey' => 'tawasulFinanceBankReconciliationID',
    'description' => 'A comparison of one cash account against its bank statement, with the difference and who reconciled it.',
    'select' => "SELECT tawasulFinanceBankReconciliation.tawasulFinanceBankReconciliationID, tawasulFinanceBankReconciliation.tawasulFinanceCashAccountID, tawasulFinanceCashAccount.name AS cashAccountName, tawasulFinanceBankReconciliation.statementDate, tawasulFinanceBankReconciliation.statementBalance, tawasulFinanceBankReconciliation.bookBalance, tawasulFinanceBankReconciliation.difference, tawasulFinanceBankReconciliation.notes, tawasulFinanceBankReconciliation.reconciledByID, reconciler.surname AS reconciledBySurname, reconciler.preferredName AS reconciledByPreferredName FROM tawasulFinanceBankReconciliation LEFT JOIN tawasulFinanceCashAccount ON (tawasulFinanceBankReconciliation.tawasulFinanceCashAccountID=tawasulFinanceCashAccount.tawasulFinanceCashAccountID) LEFT JOIN tawasulPerson AS reconciler ON (tawasulFinanceBankReconciliation.reconciledByID=reconciler.tawasulPersonID)",
    'filters' => [
        'tawasulFinanceCashAccountID' => 'tawasulFinanceBankReconciliation.tawasulFinanceCashAccountID',
        'statementDate' => 'tawasulFinanceBankReconciliation.statementDate',
    ],
    'search' => ['tawasulFinanceBankReconciliation.notes'],
    'sort' => ['statementDate' => 'tawasulFinanceBankReconciliation.statementDate', 'difference' => 'tawasulFinanceBankReconciliation.difference'],
    'defaultSort' => 'tawasulFinanceBankReconciliation.statementDate DESC',
    'writable' => ['tawasulFinanceCashAccountID', 'statementDate', 'statementBalance', 'bookBalance', 'difference', 'notes', 'reconciledByID'],
    // statementBalance, bookBalance and difference are NOT NULL with no default in the
    // schema, so omitting one would store 0 and read back as a genuine zero balance.
    'required' => ['tawasulFinanceCashAccountID', 'statementDate', 'statementBalance', 'bookBalance', 'difference'],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulFinanceCashAccountID' => 'integer',
        'statementDate' => 'date',
        'statementBalance' => 'number',
        'bookBalance' => 'number',
        'difference' => 'number',
        'reconciledByID' => 'integer',
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

// ---------------- Payroll ----------------

'payroll-runs' => [
    'title' => 'Payroll Runs',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Journal Entries',
    'table' => 'tawasulFinancePayrollRun',
    'primaryKey' => 'tawasulFinancePayrollRunID',
    'description' => 'One month of salaries. A draft run holds totals but touches no account; posting it writes the earnings and deductions to the ledger.',
    'select' => "SELECT tawasulFinancePayrollRun.tawasulFinancePayrollRunID, tawasulFinancePayrollRun.month, tawasulFinancePayrollRun.tawasulFinanceCashAccountID, tawasulFinanceCashAccount.name AS cashAccountName, tawasulFinancePayrollRun.totalEarnings, tawasulFinancePayrollRun.totalDeductions, tawasulFinancePayrollRun.netPay, tawasulFinancePayrollRun.status, tawasulFinancePayrollRun.tawasulFinanceJournalEntryID, (SELECT COUNT(*) FROM tawasulFinancePayrollLine WHERE tawasulFinancePayrollLine.tawasulFinancePayrollRunID=tawasulFinancePayrollRun.tawasulFinancePayrollRunID) AS lineCount FROM tawasulFinancePayrollRun LEFT JOIN tawasulFinanceCashAccount ON (tawasulFinancePayrollRun.tawasulFinanceCashAccountID=tawasulFinanceCashAccount.tawasulFinanceCashAccountID)",
    'filters' => [
        'status' => 'tawasulFinancePayrollRun.status',
        'month' => 'tawasulFinancePayrollRun.month',
    ],
    'sort' => ['month' => 'tawasulFinancePayrollRun.month', 'netPay' => 'tawasulFinancePayrollRun.netPay'],
    'defaultSort' => 'tawasulFinancePayrollRun.month DESC',
    'writable' => ['month', 'tawasulFinanceCashAccountID', 'totalEarnings', 'totalDeductions', 'netPay', 'status'],
    'required' => ['month'],
    'enums' => ['status' => ['Draft', 'Posted']],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulFinanceCashAccountID' => 'integer',
        'totalEarnings' => 'number',
        'totalDeductions' => 'number',
        'netPay' => 'number',
    ],
    'maxLength' => [
        'month' => 7,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'payroll-lines' => [
    'title' => 'Payroll Lines',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Journal Entries',
    'table' => 'tawasulFinancePayrollLine',
    'primaryKey' => 'tawasulFinancePayrollLineID',
    'description' => 'One staff member, one salary component, in one payroll run.',
    'select' => "SELECT tawasulFinancePayrollLine.tawasulFinancePayrollLineID, tawasulFinancePayrollLine.tawasulFinancePayrollRunID, tawasulFinancePayrollRun.month, tawasulFinancePayrollRun.status AS runStatus, tawasulFinancePayrollLine.tawasulPersonID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulFinancePayrollLine.tawasulFinanceSalaryComponentID, tawasulFinanceSalaryComponent.name AS componentName, tawasulFinanceSalaryComponent.type AS componentType, tawasulFinancePayrollLine.amount FROM tawasulFinancePayrollLine JOIN tawasulFinancePayrollRun ON (tawasulFinancePayrollLine.tawasulFinancePayrollRunID=tawasulFinancePayrollRun.tawasulFinancePayrollRunID) LEFT JOIN tawasulPerson ON (tawasulFinancePayrollLine.tawasulPersonID=tawasulPerson.tawasulPersonID) LEFT JOIN tawasulFinanceSalaryComponent ON (tawasulFinancePayrollLine.tawasulFinanceSalaryComponentID=tawasulFinanceSalaryComponent.tawasulFinanceSalaryComponentID)",
    'filters' => [
        'tawasulFinancePayrollRunID' => 'tawasulFinancePayrollLine.tawasulFinancePayrollRunID',
        'tawasulPersonID' => 'tawasulFinancePayrollLine.tawasulPersonID',
        'tawasulFinanceSalaryComponentID' => 'tawasulFinancePayrollLine.tawasulFinanceSalaryComponentID',
        'componentType' => 'tawasulFinanceSalaryComponent.type',
        'month' => 'tawasulFinancePayrollRun.month',
    ],
    'search' => ['tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulFinanceSalaryComponent.name'],
    'sort' => ['amount' => 'tawasulFinancePayrollLine.amount', 'month' => 'tawasulFinancePayrollRun.month'],
    'defaultSort' => 'tawasulFinancePayrollRun.month DESC, tawasulPerson.surname',
    'writable' => ['tawasulFinancePayrollRunID', 'tawasulPersonID', 'tawasulFinanceSalaryComponentID', 'amount'],
    'required' => ['tawasulFinancePayrollRunID', 'tawasulPersonID', 'tawasulFinanceSalaryComponentID', 'amount'],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulFinancePayrollRunID' => 'integer',
        'tawasulPersonID' => 'integer',
        'tawasulFinanceSalaryComponentID' => 'integer',
        'amount' => 'number',
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'staff-salaries' => [
    'title' => 'Staff Salaries',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Chart of Accounts',
    'table' => 'tawasulFinanceStaffSalary',
    'primaryKey' => 'tawasulFinanceStaffSalaryID',
    'description' => 'What each staff member earns and is deducted, component by component. A payroll run copies these into payroll lines; changing one here never alters a run already posted.',
    'select' => "SELECT tawasulFinanceStaffSalary.tawasulFinanceStaffSalaryID, tawasulFinanceStaffSalary.tawasulPersonID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulFinanceStaffSalary.tawasulFinanceSalaryComponentID, tawasulFinanceSalaryComponent.name AS componentName, tawasulFinanceSalaryComponent.type AS componentType, tawasulFinanceStaffSalary.amount, tawasulFinanceStaffSalary.tawasulFinanceCostCenterID, tawasulFinanceCostCenter.code AS costCenterCode, tawasulFinanceCostCenter.name AS costCenterName, tawasulFinanceStaffSalary.timestampCreator FROM tawasulFinanceStaffSalary LEFT JOIN tawasulPerson ON (tawasulFinanceStaffSalary.tawasulPersonID=tawasulPerson.tawasulPersonID) LEFT JOIN tawasulFinanceSalaryComponent ON (tawasulFinanceStaffSalary.tawasulFinanceSalaryComponentID=tawasulFinanceSalaryComponent.tawasulFinanceSalaryComponentID) LEFT JOIN tawasulFinanceCostCenter ON (tawasulFinanceStaffSalary.tawasulFinanceCostCenterID=tawasulFinanceCostCenter.tawasulFinanceCostCenterID)",
    'filters' => [
        'tawasulPersonID' => 'tawasulFinanceStaffSalary.tawasulPersonID',
        'tawasulFinanceSalaryComponentID' => 'tawasulFinanceStaffSalary.tawasulFinanceSalaryComponentID',
        'componentType' => 'tawasulFinanceSalaryComponent.type',
        'tawasulFinanceCostCenterID' => 'tawasulFinanceStaffSalary.tawasulFinanceCostCenterID',
    ],
    'search' => ['tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulFinanceSalaryComponent.name'],
    'defaultSort' => 'tawasulPerson.surname, tawasulFinanceSalaryComponent.name',
    'writable' => ['tawasulPersonID', 'tawasulFinanceSalaryComponentID', 'amount', 'tawasulFinanceCostCenterID'],
    'required' => ['tawasulPersonID', 'tawasulFinanceSalaryComponentID', 'amount'],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulPersonID' => 'integer',
        'tawasulFinanceSalaryComponentID' => 'integer',
        'amount' => 'number',
        'tawasulFinanceCostCenterID' => 'integer',
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

// ---------------- Fixed assets ----------------

'assets' => [
    'title' => 'Assets',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Chart of Accounts',
    'table' => 'tawasulFinanceAsset',
    'primaryKey' => 'tawasulFinanceAssetID',
    'description' => 'Fixed assets, with the three accounts their depreciation runs between: the asset itself, accumulated depreciation, and the expense.',
    'select' => "SELECT tawasulFinanceAsset.tawasulFinanceAssetID, tawasulFinanceAsset.name, tawasulFinanceAsset.acquisitionDate, tawasulFinanceAsset.cost, tawasulFinanceAsset.salvageValue, tawasulFinanceAsset.usefulLifeYears, tawasulFinanceAsset.method, tawasulFinanceAsset.tawasulFinanceAssetAccountID, assetAccount.code AS assetAccountCode, tawasulFinanceAsset.tawasulFinanceAccumDepAccountID, accumDep.code AS accumDepAccountCode, tawasulFinanceAsset.tawasulFinanceExpenseAccountID, expense.code AS expenseAccountCode, tawasulFinanceAsset.tawasulFinanceCostCenterID, tawasulFinanceCostCenter.code AS costCenterCode, tawasulFinanceAsset.timestampCreator, (SELECT COALESCE(SUM(tawasulFinanceDepreciation.amount), 0) FROM tawasulFinanceDepreciation WHERE tawasulFinanceDepreciation.tawasulFinanceAssetID=tawasulFinanceAsset.tawasulFinanceAssetID) AS accumulatedDepreciation, (SELECT COUNT(*) FROM tawasulFinanceDepreciation WHERE tawasulFinanceDepreciation.tawasulFinanceAssetID=tawasulFinanceAsset.tawasulFinanceAssetID) AS depreciationCount FROM tawasulFinanceAsset LEFT JOIN tawasulFinanceAccount AS assetAccount ON (tawasulFinanceAsset.tawasulFinanceAssetAccountID=assetAccount.tawasulFinanceAccountID) LEFT JOIN tawasulFinanceAccount AS accumDep ON (tawasulFinanceAsset.tawasulFinanceAccumDepAccountID=accumDep.tawasulFinanceAccountID) LEFT JOIN tawasulFinanceAccount AS expense ON (tawasulFinanceAsset.tawasulFinanceExpenseAccountID=expense.tawasulFinanceAccountID) LEFT JOIN tawasulFinanceCostCenter ON (tawasulFinanceAsset.tawasulFinanceCostCenterID=tawasulFinanceCostCenter.tawasulFinanceCostCenterID)",
    'filters' => ['method' => 'tawasulFinanceAsset.method', 'tawasulFinanceCostCenterID' => 'tawasulFinanceAsset.tawasulFinanceCostCenterID'],
    'search' => ['tawasulFinanceAsset.name'],
    'sort' => ['name' => 'tawasulFinanceAsset.name', 'acquisitionDate' => 'tawasulFinanceAsset.acquisitionDate', 'cost' => 'tawasulFinanceAsset.cost'],
    'defaultSort' => 'tawasulFinanceAsset.name',
    'writable' => ['name', 'acquisitionDate', 'cost', 'salvageValue', 'usefulLifeYears', 'method', 'tawasulFinanceAssetAccountID', 'tawasulFinanceAccumDepAccountID', 'tawasulFinanceExpenseAccountID', 'tawasulFinanceCostCenterID'],
    'required' => ['name', 'cost'],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'acquisitionDate' => 'date',
        'cost' => 'number',
        'salvageValue' => 'number',
        'usefulLifeYears' => 'integer',
        'tawasulFinanceAssetAccountID' => 'integer',
        'tawasulFinanceAccumDepAccountID' => 'integer',
        'tawasulFinanceExpenseAccountID' => 'integer',
        'tawasulFinanceCostCenterID' => 'integer',
    ],
    'maxLength' => [
        'name' => 150,
        'method' => 30,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'depreciations' => [
    'title' => 'Depreciations',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Journal Entries',
    'table' => 'tawasulFinanceDepreciation',
    'primaryKey' => 'tawasulFinanceDepreciationID',
    'description' => 'One period of depreciation for one asset, and the ledger entry that booked it.',
    'select' => "SELECT tawasulFinanceDepreciation.tawasulFinanceDepreciationID, tawasulFinanceDepreciation.tawasulFinanceAssetID, tawasulFinanceAsset.name AS assetName, tawasulFinanceAsset.method, tawasulFinanceAsset.usefulLifeYears, tawasulFinanceDepreciation.periodEnd, tawasulFinanceDepreciation.amount, tawasulFinanceDepreciation.tawasulFinanceJournalEntryID FROM tawasulFinanceDepreciation LEFT JOIN tawasulFinanceAsset ON (tawasulFinanceDepreciation.tawasulFinanceAssetID=tawasulFinanceAsset.tawasulFinanceAssetID)",
    'filters' => [
        'tawasulFinanceAssetID' => 'tawasulFinanceDepreciation.tawasulFinanceAssetID',
        'periodEnd' => 'tawasulFinanceDepreciation.periodEnd',
        'tawasulFinanceJournalEntryID' => 'tawasulFinanceDepreciation.tawasulFinanceJournalEntryID',
    ],
    'search' => ['tawasulFinanceAsset.name'],
    'sort' => ['periodEnd' => 'tawasulFinanceDepreciation.periodEnd', 'amount' => 'tawasulFinanceDepreciation.amount'],
    'defaultSort' => 'tawasulFinanceDepreciation.periodEnd DESC',
    'writable' => ['tawasulFinanceAssetID', 'periodEnd', 'amount'],
    'required' => ['tawasulFinanceAssetID', 'periodEnd', 'amount'],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulFinanceAssetID' => 'integer',
        'periodEnd' => 'date',
        'amount' => 'number',
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

// ---------------- Fee plans and discounts ----------------

'fee-plans' => [
    'title' => 'Fee Plans',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Fees',
    'table' => 'tawasulFinanceFeePlan',
    'primaryKey' => 'tawasulFinanceFeePlanID',
    'description' => 'A named bundle of fee items that can be billed in installments to a year group.',
    'select' => "SELECT tawasulFinanceFeePlan.tawasulFinanceFeePlanID, tawasulFinanceFeePlan.name, tawasulFinanceFeePlan.tawasulSchoolYearID, tawasulFinanceFeePlan.tawasulYearGroupID, tawasulFinanceFeePlan.installments, tawasulFinanceFeePlan.tawasulFinanceCostCenterID, tawasulFinanceCostCenter.code AS costCenterCode, tawasulFinanceFeePlan.timestampCreator, (SELECT COUNT(*) FROM tawasulFinanceFeePlanItem WHERE tawasulFinanceFeePlanItem.tawasulFinanceFeePlanID=tawasulFinanceFeePlan.tawasulFinanceFeePlanID) AS itemCount, (SELECT COALESCE(SUM(tawasulFinanceFeePlanItem.amount), 0) FROM tawasulFinanceFeePlanItem WHERE tawasulFinanceFeePlanItem.tawasulFinanceFeePlanID=tawasulFinanceFeePlan.tawasulFinanceFeePlanID) AS planTotal FROM tawasulFinanceFeePlan LEFT JOIN tawasulFinanceCostCenter ON (tawasulFinanceFeePlan.tawasulFinanceCostCenterID=tawasulFinanceCostCenter.tawasulFinanceCostCenterID)",
    'yearFilter' => 'tawasulFinanceFeePlan.tawasulSchoolYearID',
    'filters' => [
        'tawasulSchoolYearID' => 'tawasulFinanceFeePlan.tawasulSchoolYearID',
        'tawasulYearGroupID' => 'tawasulFinanceFeePlan.tawasulYearGroupID',
    ],
    'search' => ['tawasulFinanceFeePlan.name'],
    'defaultSort' => 'tawasulFinanceFeePlan.name',
    'writable' => ['name', 'tawasulSchoolYearID', 'tawasulYearGroupID', 'installments', 'tawasulFinanceCostCenterID'],
    'required' => ['name', 'tawasulSchoolYearID'],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulSchoolYearID' => 'integer',
        'tawasulYearGroupID' => 'integer',
        'installments' => 'integer',
        'tawasulFinanceCostCenterID' => 'integer',
    ],
    'maxLength' => [
        'name' => 150,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'fee-plan-items' => [
    'title' => 'Fee Plan Items',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Fees',
    'table' => 'tawasulFinanceFeePlanItem',
    'primaryKey' => 'tawasulFinanceFeePlanItemID',
    'description' => 'One fee item inside a plan, and the amount this school charges for it.',
    'select' => "SELECT tawasulFinanceFeePlanItem.tawasulFinanceFeePlanItemID, tawasulFinanceFeePlanItem.tawasulFinanceFeePlanID, tawasulFinanceFeePlan.name AS planName, tawasulFinanceFeePlan.tawasulSchoolYearID, tawasulFinanceFeePlanItem.tawasulFinanceFeeItemID, tawasulFinanceFeeItem.name AS itemName, tawasulFinanceFeeItem.category, tawasulFinanceFeePlanItem.amount, tawasulFinanceFeePlanItem.timestampCreator FROM tawasulFinanceFeePlanItem LEFT JOIN tawasulFinanceFeePlan ON (tawasulFinanceFeePlanItem.tawasulFinanceFeePlanID=tawasulFinanceFeePlan.tawasulFinanceFeePlanID) LEFT JOIN tawasulFinanceFeeItem ON (tawasulFinanceFeePlanItem.tawasulFinanceFeeItemID=tawasulFinanceFeeItem.tawasulFinanceFeeItemID)",
    'filters' => [
        'tawasulFinanceFeePlanID' => 'tawasulFinanceFeePlanItem.tawasulFinanceFeePlanID',
        'tawasulFinanceFeeItemID' => 'tawasulFinanceFeePlanItem.tawasulFinanceFeeItemID',
        'category' => 'tawasulFinanceFeeItem.category',
    ],
    'search' => ['tawasulFinanceFeeItem.name', 'tawasulFinanceFeePlan.name'],
    'sort' => ['amount' => 'tawasulFinanceFeePlanItem.amount'],
    'defaultSort' => 'tawasulFinanceFeePlan.name, tawasulFinanceFeeItem.name',
    'writable' => ['tawasulFinanceFeePlanID', 'tawasulFinanceFeeItemID', 'amount'],
    'required' => ['tawasulFinanceFeePlanID', 'tawasulFinanceFeeItemID', 'amount'],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulFinanceFeePlanID' => 'integer',
        'tawasulFinanceFeeItemID' => 'integer',
        'amount' => 'number',
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'fee-items' => [
    'title' => 'Fee Items',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Fees',
    'table' => 'tawasulFinanceFeeItem',
    'primaryKey' => 'tawasulFinanceFeeItemID',
    'description' => 'The individual things a school bills for, each mapped to the revenue account it posts to.',
    'select' => "SELECT tawasulFinanceFeeItem.tawasulFinanceFeeItemID, tawasulFinanceFeeItem.name, tawasulFinanceFeeItem.category, tawasulFinanceFeeItem.tawasulFinanceRevenueAccountID, tawasulFinanceAccount.code AS revenueAccountCode, tawasulFinanceAccount.name AS revenueAccountName, tawasulFinanceFeeItem.defaultAmount, tawasulFinanceFeeItem.timestampCreator FROM tawasulFinanceFeeItem LEFT JOIN tawasulFinanceAccount ON (tawasulFinanceFeeItem.tawasulFinanceRevenueAccountID=tawasulFinanceAccount.tawasulFinanceAccountID)",
    'filters' => ['category' => 'tawasulFinanceFeeItem.category'],
    'search' => ['tawasulFinanceFeeItem.name'],
    'sort' => ['name' => 'tawasulFinanceFeeItem.name', 'defaultAmount' => 'tawasulFinanceFeeItem.defaultAmount'],
    'defaultSort' => 'tawasulFinanceFeeItem.name',
    'writable' => ['name', 'category', 'tawasulFinanceRevenueAccountID', 'defaultAmount'],
    'required' => ['name'],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulFinanceRevenueAccountID' => 'integer',
        'defaultAmount' => 'number',
    ],
    'maxLength' => [
        'name' => 150,
        'category' => 30,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'discounts' => [
    'title' => 'Discounts',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Fees',
    'table' => 'tawasulFinanceDiscount',
    'primaryKey' => 'tawasulFinanceDiscountID',
    'description' => 'Sibling, staff and bursary discounts, and the expense account the cost lands in.',
    'select' => "SELECT tawasulFinanceDiscount.tawasulFinanceDiscountID, tawasulFinanceDiscount.name, tawasulFinanceDiscount.kind, tawasulFinanceDiscount.method, tawasulFinanceDiscount.value, tawasulFinanceDiscount.tawasulFinanceExpenseAccountID, tawasulFinanceAccount.code AS expenseAccountCode, tawasulFinanceAccount.name AS expenseAccountName, tawasulFinanceDiscount.timestampCreator, (SELECT COUNT(*) FROM tawasulFinanceStudentDiscount WHERE tawasulFinanceStudentDiscount.tawasulFinanceDiscountID=tawasulFinanceDiscount.tawasulFinanceDiscountID) AS recipientCount FROM tawasulFinanceDiscount LEFT JOIN tawasulFinanceAccount ON (tawasulFinanceDiscount.tawasulFinanceExpenseAccountID=tawasulFinanceAccount.tawasulFinanceAccountID)",
    'filters' => [
        'kind' => 'tawasulFinanceDiscount.kind',
        'method' => 'tawasulFinanceDiscount.method',
    ],
    'search' => ['tawasulFinanceDiscount.name'],
    'sort' => ['name' => 'tawasulFinanceDiscount.name'],
    'defaultSort' => 'tawasulFinanceDiscount.name',
    'writable' => ['name', 'kind', 'method', 'value', 'tawasulFinanceExpenseAccountID'],
    'required' => ['name', 'kind'],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'value' => 'number',
        'tawasulFinanceExpenseAccountID' => 'integer',
    ],
    'maxLength' => [
        'name' => 150,
        'kind' => 30,
        'method' => 30,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'student-discounts' => [
    'title' => 'Student Discounts',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Fees',
    'table' => 'tawasulFinanceStudentDiscount',
    'primaryKey' => 'tawasulFinanceStudentDiscountID',
    'description' => 'Which student holds which discount, for which school year, and who approved it.',
    'select' => "SELECT tawasulFinanceStudentDiscount.tawasulFinanceStudentDiscountID, tawasulFinanceStudentDiscount.tawasulPersonID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulPerson.studentID, tawasulFinanceStudentDiscount.tawasulFinanceDiscountID, tawasulFinanceDiscount.name AS discountName, tawasulFinanceDiscount.kind, tawasulFinanceDiscount.method, tawasulFinanceDiscount.value, tawasulFinanceStudentDiscount.tawasulSchoolYearID, tawasulFinanceStudentDiscount.tawasulPersonIDApprover, approver.surname AS approverSurname, approver.preferredName AS approverPreferredName FROM tawasulFinanceStudentDiscount LEFT JOIN tawasulPerson ON (tawasulFinanceStudentDiscount.tawasulPersonID=tawasulPerson.tawasulPersonID) LEFT JOIN tawasulFinanceDiscount ON (tawasulFinanceStudentDiscount.tawasulFinanceDiscountID=tawasulFinanceDiscount.tawasulFinanceDiscountID) LEFT JOIN tawasulPerson AS approver ON (tawasulFinanceStudentDiscount.tawasulPersonIDApprover=approver.tawasulPersonID)",
    'yearFilter' => 'tawasulFinanceStudentDiscount.tawasulSchoolYearID',
    'filters' => [
        'tawasulPersonID' => 'tawasulFinanceStudentDiscount.tawasulPersonID',
        'tawasulFinanceDiscountID' => 'tawasulFinanceStudentDiscount.tawasulFinanceDiscountID',
        'tawasulSchoolYearID' => 'tawasulFinanceStudentDiscount.tawasulSchoolYearID',
        'kind' => 'tawasulFinanceDiscount.kind',
    ],
    'search' => ['tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulFinanceDiscount.name'],
    'defaultSort' => 'tawasulPerson.surname',
    'writable' => ['tawasulPersonID', 'tawasulFinanceDiscountID', 'tawasulSchoolYearID', 'tawasulPersonIDApprover'],
    'required' => ['tawasulPersonID', 'tawasulFinanceDiscountID', 'tawasulSchoolYearID'],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulPersonID' => 'integer',
        'tawasulFinanceDiscountID' => 'integer',
        'tawasulSchoolYearID' => 'integer',
        'tawasulPersonIDApprover' => 'integer',
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

// ---------------- Audit trail ----------------

'finance-audit-log' => [
    'title' => 'Finance Audit Log',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Journal Entries',
    'table' => 'tawasulFinanceAuditLog',
    'primaryKey' => 'tawasulFinanceAuditLogID',
    'description' => 'Every posting, draft and reversal the API performed, with who did it and from where. Append-only: a record here is the evidence that a ledger movement happened, so there is no way to edit or delete one.',
    'select' => "SELECT tawasulFinanceAuditLog.tawasulFinanceAuditLogID, tawasulFinanceAuditLog.tableName, tawasulFinanceAuditLog.recordID, tawasulFinanceAuditLog.action, tawasulFinanceAuditLog.data, tawasulFinanceAuditLog.tawasulPersonID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulFinanceAuditLog.ip, tawasulFinanceAuditLog.timestamp FROM tawasulFinanceAuditLog LEFT JOIN tawasulPerson ON (tawasulFinanceAuditLog.tawasulPersonID=tawasulPerson.tawasulPersonID)",
    'filters' => [
        'tableName' => 'tawasulFinanceAuditLog.tableName',
        'action' => 'tawasulFinanceAuditLog.action',
        'recordID' => 'tawasulFinanceAuditLog.recordID',
        'tawasulPersonID' => 'tawasulFinanceAuditLog.tawasulPersonID',
    ],
    'search' => ['tawasulFinanceAuditLog.tableName', 'tawasulFinanceAuditLog.action'],
    'sort' => ['timestamp' => 'tawasulFinanceAuditLog.timestamp', 'action' => 'tawasulFinanceAuditLog.action'],
    'defaultSort' => 'tawasulFinanceAuditLog.timestamp DESC',
    'methods' => ['GET'],
    'writable' => [],
    'required' => [],
],

];
