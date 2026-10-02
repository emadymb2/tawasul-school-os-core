<?php
/*
Accounting core: chart of accounts, fiscal calendar, the general ledger and the
supporting masters (cost centres, salary components, document sequences).

The journal tables are deliberately read-only here. A posted entry must balance,
land in an open period and carry a unique document number, and only the
TawasulFinance posting engine knows how to do that. Letting the generic CRUD
write a single unbalanced line would put the whole ledger beyond repair, so
writes go through the /v2/accounting routes instead.
*/

return [

'accounts' => [
    'title' => 'Chart of Accounts',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Chart of Accounts',
    'table' => 'tawasulFinanceAccount',
    'primaryKey' => 'tawasulFinanceAccountID',
    'description' => 'The school chart of accounts. Headings (isPosting = N) group their children and cannot be posted to; leaf accounts are what a journal line points at.',
    'select' => "SELECT tawasulFinanceAccount.tawasulFinanceAccountID, tawasulFinanceAccount.code, tawasulFinanceAccount.name, tawasulFinanceAccount.type, tawasulFinanceAccount.parentAccountID, tawasulFinanceAccount.isPosting, tawasulFinanceAccount.active, tawasulFinanceAccount.timestampCreator, parent.code AS parentCode, parent.name AS parentName FROM tawasulFinanceAccount LEFT JOIN tawasulFinanceAccount AS parent ON (tawasulFinanceAccount.parentAccountID=parent.tawasulFinanceAccountID)",
    'filters' => [
        'type' => 'tawasulFinanceAccount.type',
        'active' => 'tawasulFinanceAccount.active',
        'isPosting' => 'tawasulFinanceAccount.isPosting',
        'parentAccountID' => 'tawasulFinanceAccount.parentAccountID',
    ],
    'search' => ['tawasulFinanceAccount.code', 'tawasulFinanceAccount.name'],
    'sort' => [
        'code' => 'tawasulFinanceAccount.code',
        'name' => 'tawasulFinanceAccount.name',
        'type' => 'tawasulFinanceAccount.type',
    ],
    'defaultSort' => 'tawasulFinanceAccount.code',
    'writable' => ['code', 'name', 'type', 'parentAccountID', 'isPosting', 'active'],
    'required' => ['code', 'name'],
    'enums' => [
        'type' => ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'],
        'isPosting' => ['Y', 'N'],
        'active' => ['Y', 'N'],
    ],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'parentAccountID' => 'integer',
    ],
    'maxLength' => [
        'code' => 150,
        'name' => 150,
        'type' => 30,
        'isPosting' => 30,
        'active' => 30,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'fiscal-years' => [
    'title' => 'Fiscal Years',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Fiscal Years',
    'table' => 'tawasulFinanceFiscalYear',
    'primaryKey' => 'tawasulFinanceFiscalYearID',
    'description' => 'The financial year, its first and last day, and whether it is open or closed.',
    'select' => "SELECT tawasulFinanceFiscalYear.tawasulFinanceFiscalYearID, tawasulFinanceFiscalYear.name, tawasulFinanceFiscalYear.firstDay, tawasulFinanceFiscalYear.lastDay, tawasulFinanceFiscalYear.status, tawasulFinanceFiscalYear.timestampCreator, (SELECT COUNT(*) FROM tawasulFinancePeriod WHERE tawasulFinancePeriod.tawasulFinanceFiscalYearID=tawasulFinanceFiscalYear.tawasulFinanceFiscalYearID) AS periodCount, (SELECT COUNT(*) FROM tawasulFinanceJournalEntry WHERE tawasulFinanceJournalEntry.tawasulFinancePeriodID IN (SELECT tawasulFinancePeriodID FROM tawasulFinancePeriod WHERE tawasulFinancePeriod.tawasulFinanceFiscalYearID=tawasulFinanceFiscalYear.tawasulFinanceFiscalYearID) AND tawasulFinanceJournalEntry.status='Posted') AS postedEntryCount FROM tawasulFinanceFiscalYear",
    'filters' => ['status' => 'tawasulFinanceFiscalYear.status'],
    'search' => ['tawasulFinanceFiscalYear.name'],
    'sort' => ['name' => 'tawasulFinanceFiscalYear.name', 'firstDay' => 'tawasulFinanceFiscalYear.firstDay'],
    'defaultSort' => 'tawasulFinanceFiscalYear.firstDay DESC',
    'writable' => ['name', 'firstDay', 'lastDay', 'status'],
    'required' => ['name', 'firstDay', 'lastDay'],
    'enums' => ['status' => ['Open', 'Closed']],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'firstDay' => 'date',
        'lastDay' => 'date',
    ],
    'maxLength' => [
        'name' => 150,
        'status' => 30,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'periods' => [
    'title' => 'Fiscal Periods',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Fiscal Years',
    'table' => 'tawasulFinancePeriod',
    'primaryKey' => 'tawasulFinancePeriodID',
    'description' => 'The periods a fiscal year is divided into. A posting is only accepted while its period is Open.',
    'select' => "SELECT tawasulFinancePeriod.tawasulFinancePeriodID, tawasulFinancePeriod.tawasulFinanceFiscalYearID, tawasulFinancePeriod.name, tawasulFinancePeriod.startDate, tawasulFinancePeriod.endDate, tawasulFinancePeriod.status, tawasulFinanceFiscalYear.name AS fiscalYearName, (SELECT COUNT(*) FROM tawasulFinanceJournalEntry WHERE tawasulFinanceJournalEntry.tawasulFinancePeriodID=tawasulFinancePeriod.tawasulFinancePeriodID AND tawasulFinanceJournalEntry.status='Posted') AS postedEntryCount FROM tawasulFinancePeriod LEFT JOIN tawasulFinanceFiscalYear ON (tawasulFinancePeriod.tawasulFinanceFiscalYearID=tawasulFinanceFiscalYear.tawasulFinanceFiscalYearID)",
    'filters' => [
        'status' => 'tawasulFinancePeriod.status',
        'tawasulFinanceFiscalYearID' => 'tawasulFinancePeriod.tawasulFinanceFiscalYearID',
    ],
    'search' => ['tawasulFinancePeriod.name'],
    'sort' => [
        'startDate' => 'tawasulFinancePeriod.startDate',
        'name' => 'tawasulFinancePeriod.name',
    ],
    'defaultSort' => 'tawasulFinancePeriod.startDate',
    'writable' => ['tawasulFinanceFiscalYearID', 'name', 'startDate', 'endDate', 'status'],
    'required' => ['tawasulFinanceFiscalYearID', 'name', 'startDate', 'endDate'],
    'enums' => ['status' => ['Open', 'Closed']],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulFinanceFiscalYearID' => 'integer',
        'startDate' => 'date',
        'endDate' => 'date',
    ],
    'maxLength' => [
        'name' => 150,
        'status' => 30,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'journal-entries' => [
    'title' => 'Journal Entries',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Journal Entries',
    'table' => 'tawasulFinanceJournalEntry',
    'primaryKey' => 'tawasulFinanceJournalEntryID',
    'description' => 'The header of every accounting entry: document number and type, date, period, status and who posted it. Lines live under /journal-entries/{id}/lines. Read-only: post with POST /v2/accounting/journal.',
    'select' => "SELECT tawasulFinanceJournalEntry.tawasulFinanceJournalEntryID, tawasulFinanceJournalEntry.documentNumber, tawasulFinanceJournalEntry.documentType, tawasulFinanceJournalEntry.date, tawasulFinanceJournalEntry.tawasulFinancePeriodID, tawasulFinanceJournalEntry.description, tawasulFinanceJournalEntry.sourceType, tawasulFinanceJournalEntry.sourceID, tawasulFinanceJournalEntry.status, tawasulFinanceJournalEntry.isRecurring, tawasulFinanceJournalEntry.recurringFrequency, tawasulFinanceJournalEntry.reversedEntryID, tawasulFinanceJournalEntry.tawasulPersonIDCreator, tawasulFinanceJournalEntry.timestampCreator, tawasulFinanceJournalEntry.tawasulPersonIDPoster, tawasulFinanceJournalEntry.timestampPoster, tawasulFinancePeriod.name AS periodName, tawasulFinanceFiscalYear.name AS fiscalYearName, (SELECT COUNT(*) FROM tawasulFinanceJournalLine WHERE tawasulFinanceJournalLine.tawasulFinanceJournalEntryID=tawasulFinanceJournalEntry.tawasulFinanceJournalEntryID) AS lineCount, (SELECT SUM(tawasulFinanceJournalLine.debit) FROM tawasulFinanceJournalLine WHERE tawasulFinanceJournalLine.tawasulFinanceJournalEntryID=tawasulFinanceJournalEntry.tawasulFinanceJournalEntryID) AS totalDebit, (SELECT SUM(tawasulFinanceJournalLine.credit) FROM tawasulFinanceJournalLine WHERE tawasulFinanceJournalLine.tawasulFinanceJournalEntryID=tawasulFinanceJournalEntry.tawasulFinanceJournalEntryID) AS totalCredit FROM tawasulFinanceJournalEntry LEFT JOIN tawasulFinancePeriod ON (tawasulFinanceJournalEntry.tawasulFinancePeriodID=tawasulFinancePeriod.tawasulFinancePeriodID) LEFT JOIN tawasulFinanceFiscalYear ON (tawasulFinancePeriod.tawasulFinanceFiscalYearID=tawasulFinanceFiscalYear.tawasulFinanceFiscalYearID)",
    'filters' => [
        'status' => 'tawasulFinanceJournalEntry.status',
        'documentType' => 'tawasulFinanceJournalEntry.documentType',
        'sourceType' => 'tawasulFinanceJournalEntry.sourceType',
        'sourceID' => 'tawasulFinanceJournalEntry.sourceID',
        'tawasulFinancePeriodID' => 'tawasulFinanceJournalEntry.tawasulFinancePeriodID',
        'reversedEntryID' => 'tawasulFinanceJournalEntry.reversedEntryID',
        'tawasulPersonIDCreator' => 'tawasulFinanceJournalEntry.tawasulPersonIDCreator',
        'date' => 'tawasulFinanceJournalEntry.date',
    ],
    'search' => ['tawasulFinanceJournalEntry.documentNumber', 'tawasulFinanceJournalEntry.description'],
    'sort' => [
        'date' => 'tawasulFinanceJournalEntry.date',
        'documentNumber' => 'tawasulFinanceJournalEntry.documentNumber',
        'status' => 'tawasulFinanceJournalEntry.status',
    ],
    'defaultSort' => 'tawasulFinanceJournalEntry.date DESC, tawasulFinanceJournalEntry.tawasulFinanceJournalEntryID DESC',
    // Posting, drafting and reversing all go through the AccountingController so
    // the balance and open-period rules cannot be side-stepped. Deleting a posted
    // entry would destroy an audit trail, so DELETE is withheld as well.
    'methods' => ['GET'],
    'writable' => [],
    'required' => [],
    'enums' => ['status' => ['Draft', 'Posted', 'Reversed']],
],

'journal-lines' => [
    'title' => 'Journal Lines',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Journal Entries',
    'table' => 'tawasulFinanceJournalLine',
    'primaryKey' => 'tawasulFinanceJournalLineID',
    'description' => 'The debit and credit lines of an entry, with the account and optional cost centre each one touches. Read-only: a line can only be written as part of a whole, balanced entry.',
    'select' => "SELECT tawasulFinanceJournalLine.tawasulFinanceJournalLineID, tawasulFinanceJournalLine.tawasulFinanceJournalEntryID, tawasulFinanceJournalLine.tawasulFinanceAccountID, tawasulFinanceJournalLine.tawasulFinanceCostCenterID, tawasulFinanceJournalLine.tawasulPersonID, tawasulFinanceJournalLine.debit, tawasulFinanceJournalLine.credit, tawasulFinanceJournalLine.memo, tawasulFinanceAccount.code AS accountCode, tawasulFinanceAccount.name AS accountName, tawasulFinanceAccount.type AS accountType, tawasulFinanceCostCenter.code AS costCenterCode, tawasulFinanceCostCenter.name AS costCenterName, tawasulFinanceJournalEntry.date, tawasulFinanceJournalEntry.documentNumber, tawasulFinanceJournalEntry.status FROM tawasulFinanceJournalLine JOIN tawasulFinanceJournalEntry ON (tawasulFinanceJournalLine.tawasulFinanceJournalEntryID=tawasulFinanceJournalEntry.tawasulFinanceJournalEntryID) LEFT JOIN tawasulFinanceAccount ON (tawasulFinanceJournalLine.tawasulFinanceAccountID=tawasulFinanceAccount.tawasulFinanceAccountID) LEFT JOIN tawasulFinanceCostCenter ON (tawasulFinanceJournalLine.tawasulFinanceCostCenterID=tawasulFinanceCostCenter.tawasulFinanceCostCenterID)",
    'filters' => [
        'tawasulFinanceJournalEntryID' => 'tawasulFinanceJournalLine.tawasulFinanceJournalEntryID',
        'tawasulFinanceAccountID' => 'tawasulFinanceJournalLine.tawasulFinanceAccountID',
        'tawasulFinanceCostCenterID' => 'tawasulFinanceJournalLine.tawasulFinanceCostCenterID',
        'tawasulPersonID' => 'tawasulFinanceJournalLine.tawasulPersonID',
        'status' => 'tawasulFinanceJournalEntry.status',
        'date' => 'tawasulFinanceJournalEntry.date',
    ],
    'search' => ['tawasulFinanceAccount.code', 'tawasulFinanceAccount.name', 'tawasulFinanceJournalLine.memo', 'tawasulFinanceJournalEntry.documentNumber'],
    'sort' => [
        'date' => 'tawasulFinanceJournalEntry.date',
        'accountCode' => 'tawasulFinanceAccount.code',
        'debit' => 'tawasulFinanceJournalLine.debit',
        'credit' => 'tawasulFinanceJournalLine.credit',
    ],
    'defaultSort' => 'tawasulFinanceJournalEntry.date DESC, tawasulFinanceJournalLine.tawasulFinanceJournalEntryID, tawasulFinanceJournalLine.tawasulFinanceJournalLineID',
    'methods' => ['GET'],
    'writable' => [],
    'required' => [],
],

'cost-centers' => [
    'title' => 'Cost Centres',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Chart of Accounts',
    'table' => 'tawasulFinanceCostCenter',
    'primaryKey' => 'tawasulFinanceCostCenterID',
    'description' => 'Divisions such as stages or departments, used to attribute a journal line to part of the school.',
    'select' => "SELECT tawasulFinanceCostCenter.tawasulFinanceCostCenterID, tawasulFinanceCostCenter.code, tawasulFinanceCostCenter.name, tawasulFinanceCostCenter.type, tawasulFinanceCostCenter.timestampCreator, (SELECT COUNT(*) FROM tawasulFinanceJournalLine WHERE tawasulFinanceJournalLine.tawasulFinanceCostCenterID=tawasulFinanceCostCenter.tawasulFinanceCostCenterID) AS lineCount FROM tawasulFinanceCostCenter",
    'filters' => ['type' => 'tawasulFinanceCostCenter.type'],
    'search' => ['tawasulFinanceCostCenter.code', 'tawasulFinanceCostCenter.name'],
    'sort' => ['code' => 'tawasulFinanceCostCenter.code', 'name' => 'tawasulFinanceCostCenter.name'],
    'defaultSort' => 'tawasulFinanceCostCenter.code',
    'writable' => ['code', 'name', 'type'],
    'required' => ['code', 'name'],
    'maxLength' => [
        'code' => 150,
        'name' => 150,
        'type' => 30,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'salary-components' => [
    'title' => 'Salary Components',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Chart of Accounts',
    'table' => 'tawasulFinanceSalaryComponent',
    'primaryKey' => 'tawasulFinanceSalaryComponentID',
    'description' => 'The earning and deduction lines a staff salary is built from, each mapped to the account it is posted to.',
    'select' => "SELECT tawasulFinanceSalaryComponent.tawasulFinanceSalaryComponentID, tawasulFinanceSalaryComponent.name, tawasulFinanceSalaryComponent.type, tawasulFinanceSalaryComponent.tawasulFinanceAccountID, tawasulFinanceSalaryComponent.timestampCreator, tawasulFinanceAccount.code AS accountCode, tawasulFinanceAccount.name AS accountName FROM tawasulFinanceSalaryComponent LEFT JOIN tawasulFinanceAccount ON (tawasulFinanceSalaryComponent.tawasulFinanceAccountID=tawasulFinanceAccount.tawasulFinanceAccountID)",
    'filters' => [
        'type' => 'tawasulFinanceSalaryComponent.type',
        'tawasulFinanceAccountID' => 'tawasulFinanceSalaryComponent.tawasulFinanceAccountID',
    ],
    'search' => ['tawasulFinanceSalaryComponent.name', 'tawasulFinanceAccount.code'],
    'defaultSort' => 'tawasulFinanceSalaryComponent.name',
    'writable' => ['name', 'type', 'tawasulFinanceAccountID'],
    'required' => ['name'],
    'enums' => ['type' => ['Earning', 'Deduction']],
    // Derived from the column types in tawasulFinance manifest, so a caller
    // gets a 422 naming the field instead of a silent zero or zero-date.
    'types' => [
        'tawasulFinanceAccountID' => 'integer',
    ],
    'maxLength' => [
        'name' => 150,
        'type' => 30,
    ],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'sequences' => [
    'title' => 'Document Sequences',
    'group' => 'Accounting',
    'scope' => 'accounting',
    'module' => 'Finance',
    'action' => 'Manage Journal Entries',
    'table' => 'tawasulFinanceSequence',
    'primaryKey' => 'documentType',
    'description' => 'Per-type counter behind every document number (JV, REV, BR, BP and so on). The posting engine allocates the next number under a row lock, so this endpoint is read-only.',
    'select' => "SELECT tawasulFinanceSequence.documentType, tawasulFinanceSequence.prefix, tawasulFinanceSequence.nextNumber FROM tawasulFinanceSequence",
    'filters' => ['documentType' => 'tawasulFinanceSequence.documentType'],
    'sort' => ['documentType' => 'tawasulFinanceSequence.documentType', 'nextNumber' => 'tawasulFinanceSequence.nextNumber'],
    'defaultSort' => 'tawasulFinanceSequence.documentType',
    'methods' => ['GET'],
    'writable' => [],
    'required' => [],
],

];
