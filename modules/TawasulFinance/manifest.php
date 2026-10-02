<?php
/*
 * TawasulFinance - school finance module for TawasulOS
 * Fees, invoices, expenses, budgets and petty cash.
 *
 * Licence: GPL-3.0
 */
$name        = 'TawasulFinance';
$description = 'Fee scheduling, invoicing, expense requests and budgets.';
$entryURL    = 'invoices_manage.php';
$type        = 'Additional';
$category    = 'Admin';
$version     = '1.2.00';
$author      = 'TawasulFinance';
$url         = '';

// ---------------- Tables ----------------
// The transactional tables (fees, invoices, expenses, budgets) plus the
// accounting core: chart of accounts, double-entry journal, fiscal years and
// periods, receivables/payables, payroll, fixed assets, cash accounts, bank
// reconciliation and the document sequence.

// tawasulFinanceAccount
$moduleTables[] = 'CREATE TABLE `tawasulFinanceAccount` (
  `tawasulFinanceAccountID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `code` varchar(150) NOT NULL DEFAULT \'\',
  `name` varchar(150) NOT NULL DEFAULT \'\',
  `type` varchar(30) NOT NULL DEFAULT \'Asset\',
  `parentAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `isPosting` varchar(30) NOT NULL DEFAULT \'Y\',
  `active` varchar(30) NOT NULL DEFAULT \'Y\',
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceAccountID`),
  UNIQUE KEY `uniq_code` (`code`),
  KEY `parent` (`parentAccountID`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceAsset
$moduleTables[] = 'CREATE TABLE `tawasulFinanceAsset` (
  `tawasulFinanceAssetID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT \'\',
  `acquisitionDate` date DEFAULT NULL,
  `cost` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `salvageValue` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `usefulLifeYears` int NOT NULL DEFAULT \'0\',
  `method` varchar(30) NOT NULL DEFAULT \'StraightLine\',
  `tawasulFinanceAssetAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceAccumDepAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceExpenseAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceCostCenterID` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceAssetID`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceAuditLog
$moduleTables[] = 'CREATE TABLE `tawasulFinanceAuditLog` (
  `tawasulFinanceAuditLogID` int(12) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tableName` varchar(60) NOT NULL,
  `recordID` int NOT NULL,
  `action` varchar(20) NOT NULL,
  `data` text,
  `tawasulPersonID` int(10) unsigned zerofill DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceAuditLogID`),
  KEY `rec` (`tableName`,`recordID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceBankReconciliation
$moduleTables[] = 'CREATE TABLE `tawasulFinanceBankReconciliation` (
  `tawasulFinanceBankReconciliationID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceCashAccountID` int(10) unsigned zerofill NOT NULL,
  `statementDate` date NOT NULL,
  `statementBalance` decimal(15,2) NOT NULL,
  `bookBalance` decimal(15,2) NOT NULL,
  `difference` decimal(15,2) NOT NULL,
  `notes` text,
  `reconciledByID` int(10) unsigned zerofill DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceBankReconciliationID`),
  KEY `cash_stmt` (`tawasulFinanceCashAccountID`,`statementDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceBillingSchedule
$moduleTables[] = 'CREATE TABLE `tawasulFinanceBillingSchedule` (
  `tawasulFinanceBillingScheduleID` int(6) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulSchoolYearID` int(3) unsigned zerofill NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `active` enum(\'Y\',\'N\') NOT NULL DEFAULT \'Y\',
  `invoiceIssueDate` date DEFAULT NULL,
  `invoiceDueDate` date DEFAULT NULL,
  `tawasulPersonIDCreator` int(10) unsigned zerofill NOT NULL,
  `timestampCreator` timestamp NULL DEFAULT NULL,
  `tawasulPersonIDUpdate` int(10) unsigned zerofill DEFAULT NULL,
  `timestampUpdate` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceBillingScheduleID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3';

// tawasulFinanceBudget
$moduleTables[] = 'CREATE TABLE `tawasulFinanceBudget` (
  `tawasulFinanceBudgetID` int(4) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL,
  `nameShort` varchar(8) NOT NULL,
  `active` enum(\'Y\',\'N\') NOT NULL DEFAULT \'Y\',
  `category` varchar(255) NOT NULL,
  `tawasulPersonIDCreator` int(10) unsigned zerofill NOT NULL,
  `timestampCreator` timestamp NULL DEFAULT NULL,
  `tawasulPersonIDUpdate` int(10) unsigned zerofill DEFAULT NULL,
  `timestampUpdate` timestamp NULL DEFAULT NULL,
  `tawasulFinanceFiscalYearID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceCostCenterID` int(10) unsigned zerofill DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  PRIMARY KEY (`tawasulFinanceBudgetID`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `nameShort` (`nameShort`),
  KEY `fy_account` (`tawasulFinanceFiscalYearID`,`tawasulFinanceAccountID`),
  KEY `fiscal_year` (`tawasulFinanceFiscalYearID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3';

// tawasulFinanceBudgetCycle
$moduleTables[] = 'CREATE TABLE `tawasulFinanceBudgetCycle` (
  `tawasulFinanceBudgetCycleID` int(6) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(7) NOT NULL,
  `status` enum(\'Past\',\'Current\',\'Upcoming\') NOT NULL DEFAULT \'Upcoming\',
  `dateStart` date NOT NULL,
  `dateEnd` date NOT NULL,
  `sequenceNumber` int NOT NULL,
  `tawasulPersonIDCreator` int(10) unsigned zerofill NOT NULL,
  `timestampCreator` timestamp NULL DEFAULT NULL,
  `tawasulPersonIDUpdate` int(10) unsigned zerofill DEFAULT NULL,
  `timestampUpdate` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceBudgetCycleID`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3';

// tawasulFinanceBudgetCycleAllocation
$moduleTables[] = 'CREATE TABLE `tawasulFinanceBudgetCycleAllocation` (
  `tawasulFinanceBudgetCycleAllocationID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceBudgetID` int(4) unsigned zerofill NOT NULL,
  `tawasulFinanceBudgetCycleID` int(6) unsigned zerofill NOT NULL,
  `value` decimal(14,2) NOT NULL DEFAULT \'0.00\',
  PRIMARY KEY (`tawasulFinanceBudgetCycleAllocationID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3';

// tawasulFinanceBudgetPerson
$moduleTables[] = 'CREATE TABLE `tawasulFinanceBudgetPerson` (
  `tawasulFinanceBudgetPersonID` int(8) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceBudgetID` int(4) unsigned zerofill NOT NULL,
  `tawasulPersonID` int(10) unsigned zerofill NOT NULL,
  `access` enum(\'Full\',\'Write\',\'Read\') NOT NULL,
  PRIMARY KEY (`tawasulFinanceBudgetPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3';

// tawasulFinanceCashAccount
$moduleTables[] = 'CREATE TABLE `tawasulFinanceCashAccount` (
  `tawasulFinanceCashAccountID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT \'\',
  `type` varchar(30) NOT NULL DEFAULT \'Cash\',
  `bankAccountNumber` varchar(150) NOT NULL DEFAULT \'\',
  `tawasulFinanceAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceCashAccountID`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceCostCenter
$moduleTables[] = 'CREATE TABLE `tawasulFinanceCostCenter` (
  `tawasulFinanceCostCenterID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `code` varchar(150) NOT NULL DEFAULT \'\',
  `name` varchar(150) NOT NULL DEFAULT \'\',
  `type` varchar(30) NOT NULL DEFAULT \'Stage\',
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceCostCenterID`),
  UNIQUE KEY `uniq_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceDepreciation
$moduleTables[] = 'CREATE TABLE `tawasulFinanceDepreciation` (
  `tawasulFinanceDepreciationID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceAssetID` int(10) unsigned zerofill NOT NULL,
  `periodEnd` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceDepreciationID`),
  UNIQUE KEY `assetPeriod` (`tawasulFinanceAssetID`,`periodEnd`),
  KEY `asset_end` (`tawasulFinanceAssetID`,`periodEnd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceDiscount
$moduleTables[] = 'CREATE TABLE `tawasulFinanceDiscount` (
  `tawasulFinanceDiscountID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT \'\',
  `kind` varchar(30) NOT NULL DEFAULT \'Sibling\',
  `method` varchar(30) NOT NULL DEFAULT \'Percent\',
  `value` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `tawasulFinanceExpenseAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceDiscountID`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceExpense
$moduleTables[] = 'CREATE TABLE `tawasulFinanceExpense` (
  `tawasulFinanceExpenseID` int(14) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceBudgetID` int(4) unsigned zerofill NOT NULL,
  `tawasulFinanceBudgetCycleID` int(6) unsigned zerofill NOT NULL,
  `title` varchar(60) NOT NULL,
  `body` text NOT NULL,
  `status` enum(\'Requested\',\'Approved\',\'Rejected\',\'Cancelled\',\'Ordered\',\'Paid\') NOT NULL,
  `cost` decimal(12,2) NOT NULL,
  `countAgainstBudget` enum(\'Y\',\'N\') NOT NULL DEFAULT \'Y\',
  `purchaseBy` enum(\'School\',\'Self\') NOT NULL DEFAULT \'School\',
  `purchaseDetails` text NOT NULL,
  `paymentMethod` enum(\'Cash\',\'Cheque\',\'Credit Card\',\'Bank Transfer\',\'Other\') DEFAULT NULL,
  `paymentDate` date DEFAULT NULL,
  `paymentAmount` decimal(12,2) DEFAULT NULL,
  `tawasulPersonIDPayment` int(10) unsigned zerofill DEFAULT NULL,
  `paymentID` varchar(100) DEFAULT NULL,
  `paymentReimbursementReceipt` varchar(255) NOT NULL,
  `paymentReimbursementStatus` enum(\'Requested\',\'Complete\') DEFAULT NULL,
  `tawasulPersonIDCreator` int(10) unsigned zerofill NOT NULL,
  `timestampCreator` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `statusApprovalBudgetCleared` enum(\'N\',\'Y\') NOT NULL DEFAULT \'N\',
  PRIMARY KEY (`tawasulFinanceExpenseID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3';

// tawasulFinanceExpenseApprover
$moduleTables[] = 'CREATE TABLE `tawasulFinanceExpenseApprover` (
  `tawasulFinanceExpenseApproverID` int(4) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulPersonID` int(10) unsigned zerofill NOT NULL,
  `sequenceNumber` int DEFAULT NULL,
  `tawasulPersonIDCreator` int(10) unsigned zerofill NOT NULL,
  `timestampCreator` timestamp NULL DEFAULT NULL,
  `tawasulPersonIDUpdate` int(10) unsigned zerofill DEFAULT NULL,
  `timestampUpdate` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceExpenseApproverID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3';

// tawasulFinanceExpenseLog
$moduleTables[] = 'CREATE TABLE `tawasulFinanceExpenseLog` (
  `tawasulFinanceExpenseLogID` int(16) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceExpenseID` int(14) unsigned zerofill NOT NULL,
  `tawasulPersonID` int(10) unsigned zerofill NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `action` enum(\'Request\',\'Approval - Partial - Budget\',\'Approval - Partial - School\',\'Approval - Final\',\'Approval - Exempt\',\'Rejection\',\'Cancellation\',\'Order\',\'Payment\',\'Reimbursement Request\',\'Reimbursement Completion\',\'Comment\') NOT NULL,
  `comment` text NOT NULL,
  PRIMARY KEY (`tawasulFinanceExpenseLogID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3';

// tawasulFinanceFee
$moduleTables[] = 'CREATE TABLE `tawasulFinanceFee` (
  `tawasulFinanceFeeID` int(6) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulSchoolYearID` int(3) unsigned zerofill NOT NULL,
  `name` varchar(100) NOT NULL,
  `nameShort` varchar(6) NOT NULL,
  `description` text NOT NULL,
  `active` enum(\'Y\',\'N\') NOT NULL DEFAULT \'Y\',
  `tawasulFinanceFeeCategoryID` int(4) unsigned zerofill NOT NULL,
  `fee` decimal(12,2) NOT NULL,
  `tawasulPersonIDCreator` int(10) unsigned zerofill NOT NULL,
  `timestampCreator` timestamp NULL DEFAULT NULL,
  `tawasulPersonIDUpdate` int(10) unsigned zerofill DEFAULT NULL,
  `timestampUpdate` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceFeeID`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3';

// tawasulFinanceFeeCategory
$moduleTables[] = 'CREATE TABLE `tawasulFinanceFeeCategory` (
  `tawasulFinanceFeeCategoryID` int(4) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `nameShort` varchar(6) NOT NULL,
  `description` text NOT NULL,
  `active` enum(\'Y\',\'N\') NOT NULL,
  `tawasulPersonIDCreator` int(10) unsigned zerofill NOT NULL,
  `timestampCreator` timestamp NULL DEFAULT NULL,
  `tawasulPersonIDUpdate` int(10) unsigned zerofill DEFAULT NULL,
  `timestampUpdate` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceFeeCategoryID`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3';

// tawasulFinanceFeeItem
$moduleTables[] = 'CREATE TABLE `tawasulFinanceFeeItem` (
  `tawasulFinanceFeeItemID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT \'\',
  `category` varchar(30) NOT NULL DEFAULT \'Registration\',
  `tawasulFinanceRevenueAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `defaultAmount` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceFeeItemID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceFeePlan
$moduleTables[] = 'CREATE TABLE `tawasulFinanceFeePlan` (
  `tawasulFinanceFeePlanID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT \'\',
  `tawasulSchoolYearID` int(3) unsigned zerofill DEFAULT NULL,
  `tawasulYearGroupID` int(3) unsigned zerofill DEFAULT NULL,
  `installments` int NOT NULL DEFAULT \'0\',
  `tawasulFinanceCostCenterID` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceFeePlanID`),
  KEY `year_group` (`tawasulSchoolYearID`,`tawasulYearGroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceFeePlanItem
$moduleTables[] = 'CREATE TABLE `tawasulFinanceFeePlanItem` (
  `tawasulFinanceFeePlanItemID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceFeePlanID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceFeeItemID` int(10) unsigned zerofill DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceFeePlanItemID`),
  KEY `plan_item` (`tawasulFinanceFeePlanID`,`tawasulFinanceFeeItemID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceFiscalYear
$moduleTables[] = 'CREATE TABLE `tawasulFinanceFiscalYear` (
  `tawasulFinanceFiscalYearID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT \'\',
  `firstDay` date DEFAULT NULL,
  `lastDay` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT \'Open\',
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceFiscalYearID`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceInvoice
$moduleTables[] = 'CREATE TABLE `tawasulFinanceInvoice` (
  `tawasulFinanceInvoiceID` int(14) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulSchoolYearID` int(3) unsigned zerofill NOT NULL,
  `tawasulFinanceInvoiceeID` int(10) unsigned zerofill NOT NULL,
  `invoiceTo` enum(\'Family\',\'Company\') NOT NULL DEFAULT \'Family\',
  `billingScheduleType` enum(\'Scheduled\',\'Ad Hoc\') NOT NULL DEFAULT \'Ad Hoc\',
  `separated` enum(\'N\',\'Y\') DEFAULT NULL COMMENT \'Has this invoice been separated from its schedule in tawasulFinanceBillingSchedule? Only applies to scheduled invoices. Separation takes place during invoice issueing.\',
  `tawasulFinanceBillingScheduleID` int(6) unsigned zerofill DEFAULT NULL,
  `status` enum(\'Pending\',\'Issued\',\'Paid\',\'Paid - Partial\',\'Cancelled\',\'Refunded\') NOT NULL DEFAULT \'Pending\',
  `tawasulFinanceFeeCategoryIDList` text,
  `invoiceIssueDate` date DEFAULT NULL,
  `invoiceDueDate` date DEFAULT NULL,
  `paidDate` date DEFAULT NULL,
  `paidAmount` decimal(13,2) DEFAULT NULL COMMENT \'The current running total amount paid to this invoice\',
  `tawasulPaymentID` int(14) unsigned zerofill DEFAULT NULL,
  `reminderCount` int NOT NULL DEFAULT \'0\',
  `notes` text NOT NULL,
  `key` varchar(40) NOT NULL,
  `tawasulPersonIDCreator` int(10) unsigned zerofill NOT NULL,
  `timestampCreator` timestamp NULL DEFAULT NULL,
  `tawasulPersonIDUpdate` int(10) unsigned zerofill DEFAULT NULL,
  `timestampUpdate` timestamp NULL DEFAULT NULL,
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill DEFAULT NULL,
  `grossAmount` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `discountAmount` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `netAmount` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `postedDate` date DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceInvoiceID`),
  KEY `je` (`tawasulFinanceJournalEntryID`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb3';

// tawasulFinanceInvoiceFee
$moduleTables[] = 'CREATE TABLE `tawasulFinanceInvoiceFee` (
  `tawasulFinanceInvoiceFeeID` int(15) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceInvoiceID` int(14) unsigned zerofill NOT NULL,
  `feeType` enum(\'Standard\',\'Ad Hoc\') NOT NULL DEFAULT \'Ad Hoc\',
  `tawasulFinanceFeeID` int(6) unsigned zerofill DEFAULT NULL,
  `separated` enum(\'N\',\'Y\') DEFAULT NULL COMMENT \'Has this fee been separated from its parent in tawasulFinanceFee? Only applies to Standard fees. Separation takes place during invoice issueing.\',
  `name` varchar(100) DEFAULT NULL,
  `description` text,
  `tawasulFinanceFeeCategoryID` int(4) unsigned zerofill DEFAULT NULL,
  `fee` decimal(12,2) DEFAULT NULL,
  `sequenceNumber` int DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceInvoiceFeeID`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb3';

// tawasulFinanceInvoicee
$moduleTables[] = 'CREATE TABLE `tawasulFinanceInvoicee` (
  `tawasulFinanceInvoiceeID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulPersonID` int(10) unsigned zerofill NOT NULL,
  `invoiceTo` enum(\'Family\',\'Company\') NOT NULL,
  `companyName` varchar(100) DEFAULT NULL,
  `companyContact` varchar(100) DEFAULT NULL,
  `companyAddress` varchar(255) DEFAULT NULL,
  `companyEmail` text,
  `companyCCFamily` enum(\'N\',\'Y\') DEFAULT NULL COMMENT \'When company is billed, should family receive a copy?\',
  `companyPhone` varchar(20) DEFAULT NULL,
  `companyAll` enum(\'Y\',\'N\') DEFAULT NULL COMMENT \'Should company pay all invoices?.\',
  `tawasulFinanceFeeCategoryIDList` text COMMENT \'If companyAll is N, list category IDs for campany to pay here.\',
  PRIMARY KEY (`tawasulFinanceInvoiceeID`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb3';

// tawasulFinanceInvoiceeUpdate
$moduleTables[] = 'CREATE TABLE `tawasulFinanceInvoiceeUpdate` (
  `tawasulFinanceInvoiceeUpdateID` int(12) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulSchoolYearID` int(3) unsigned zerofill DEFAULT NULL,
  `status` enum(\'Pending\',\'Complete\') NOT NULL DEFAULT \'Pending\',
  `tawasulFinanceInvoiceeID` int(10) unsigned zerofill NOT NULL,
  `invoiceTo` enum(\'Family\',\'Company\') NOT NULL,
  `companyName` varchar(100) DEFAULT NULL,
  `companyContact` varchar(100) DEFAULT NULL,
  `companyAddress` varchar(255) DEFAULT NULL,
  `companyEmail` text,
  `companyCCFamily` enum(\'N\',\'Y\') DEFAULT NULL COMMENT \'When company is billed, should family receive a copy?\',
  `companyPhone` varchar(20) DEFAULT NULL,
  `companyAll` enum(\'Y\',\'N\') DEFAULT NULL COMMENT \'Should company pay all invoices?.\',
  `tawasulFinanceFeeCategoryIDList` text COMMENT \'If companyAll is N, list category IDs for campany to pay here.\',
  `tawasulPersonIDUpdater` int(10) unsigned zerofill NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceInvoiceeUpdateID`),
  KEY `tawasulInvoiceeIndex` (`tawasulFinanceInvoiceeID`,`tawasulSchoolYearID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3';

// tawasulFinanceJournalEntry
$moduleTables[] = 'CREATE TABLE `tawasulFinanceJournalEntry` (
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `documentNumber` varchar(30) NOT NULL,
  `documentType` varchar(20) NOT NULL DEFAULT \'JV\',
  `date` date NOT NULL,
  `tawasulFinancePeriodID` int(10) unsigned zerofill DEFAULT NULL,
  `description` varchar(255) NOT NULL DEFAULT \'\',
  `sourceType` varchar(30) DEFAULT NULL,
  `sourceID` int(10) unsigned zerofill DEFAULT NULL,
  `status` enum(\'Draft\',\'Posted\',\'Reversed\') NOT NULL DEFAULT \'Draft\',
  `isRecurring` enum(\'N\',\'Y\') NOT NULL DEFAULT \'N\',
  `recurringFrequency` varchar(20) DEFAULT NULL,
  `reversedEntryID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulPersonIDCreator` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulPersonIDPoster` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `timestampPoster` datetime DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceJournalEntryID`),
  UNIQUE KEY `doc` (`documentType`,`documentNumber`),
  KEY `date` (`date`),
  KEY `period_status` (`tawasulFinancePeriodID`,`status`),
  KEY `source` (`sourceType`,`sourceID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceJournalLine
$moduleTables[] = 'CREATE TABLE `tawasulFinanceJournalLine` (
  `tawasulFinanceJournalLineID` int(12) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill NOT NULL,
  `tawasulFinanceAccountID` int(10) unsigned zerofill NOT NULL,
  `tawasulFinanceCostCenterID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulPersonID` int(10) unsigned zerofill DEFAULT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `credit` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `memo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceJournalLineID`),
  KEY `entry` (`tawasulFinanceJournalEntryID`),
  KEY `account` (`tawasulFinanceAccountID`),
  KEY `line_acct_date` (`tawasulFinanceAccountID`,`tawasulFinanceJournalEntryID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinancePaymentVoucher
$moduleTables[] = 'CREATE TABLE `tawasulFinancePaymentVoucher` (
  `tawasulFinancePaymentVoucherID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `voucherNumber` varchar(30) NOT NULL,
  `tawasulFinancePurchaseBillID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceCashAccountID` int(10) unsigned zerofill NOT NULL,
  `date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `method` enum(\'Cash\',\'Transfer\',\'Cheque\') NOT NULL DEFAULT \'Cash\',
  `reference` varchar(60) DEFAULT NULL,
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill DEFAULT NULL,
  PRIMARY KEY (`tawasulFinancePaymentVoucherID`),
  UNIQUE KEY `uniq_voucher` (`voucherNumber`),
  KEY `bill` (`tawasulFinancePurchaseBillID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinancePayrollLine
$moduleTables[] = 'CREATE TABLE `tawasulFinancePayrollLine` (
  `tawasulFinancePayrollLineID` int(12) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinancePayrollRunID` int(10) unsigned zerofill NOT NULL,
  `tawasulPersonID` int(10) unsigned zerofill NOT NULL,
  `tawasulFinanceSalaryComponentID` int(10) unsigned zerofill NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  PRIMARY KEY (`tawasulFinancePayrollLineID`),
  KEY `run_person` (`tawasulFinancePayrollRunID`,`tawasulPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinancePayrollRun
$moduleTables[] = 'CREATE TABLE `tawasulFinancePayrollRun` (
  `tawasulFinancePayrollRunID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `month` char(7) NOT NULL,
  `tawasulFinanceCashAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `totalEarnings` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `totalDeductions` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `netPay` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `status` enum(\'Draft\',\'Posted\') NOT NULL DEFAULT \'Draft\',
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill DEFAULT NULL,
  PRIMARY KEY (`tawasulFinancePayrollRunID`),
  UNIQUE KEY `month` (`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinancePeriod
$moduleTables[] = 'CREATE TABLE `tawasulFinancePeriod` (
  `tawasulFinancePeriodID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceFiscalYearID` int(10) unsigned zerofill DEFAULT NULL,
  `name` varchar(150) NOT NULL DEFAULT \'\',
  `startDate` date DEFAULT NULL,
  `endDate` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT \'Open\',
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinancePeriodID`),
  KEY `fy_dates` (`tawasulFinanceFiscalYearID`,`startDate`),
  KEY `dates` (`startDate`,`endDate`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinancePettyCash
$moduleTables[] = 'CREATE TABLE `tawasulFinancePettyCash` (
  `tawasulFinancePettyCashID` int(12) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulSchoolYearID` varchar(3) NOT NULL,
  `tawasulPersonID` int NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reason` varchar(90) DEFAULT NULL,
  `notes` text,
  `tawasulPersonIDCreated` int DEFAULT NULL,
  `timestampCreated` timestamp NULL DEFAULT NULL,
  `actionRequired` varchar(60) DEFAULT NULL,
  `tawasulPersonIDStatus` int DEFAULT NULL,
  `timestampStatus` timestamp NULL DEFAULT NULL,
  `status` varchar(60) NOT NULL,
  PRIMARY KEY (`tawasulFinancePettyCashID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3';

// tawasulFinancePurchaseBill
$moduleTables[] = 'CREATE TABLE `tawasulFinancePurchaseBill` (
  `tawasulFinancePurchaseBillID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `billNumber` varchar(30) NOT NULL,
  `supplierID` int(10) unsigned zerofill NOT NULL,
  `date` date NOT NULL,
  `dueDate` date DEFAULT NULL,
  `tawasulFinanceExpenseAccountID` int(10) unsigned zerofill NOT NULL,
  `tawasulFinanceCostCenterID` int(10) unsigned zerofill DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `taxAmount` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `paidAmount` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `description` varchar(255) DEFAULT NULL,
  `status` enum(\'Open\',\'Partial\',\'Paid\',\'Cancelled\') NOT NULL DEFAULT \'Open\',
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill DEFAULT NULL,
  PRIMARY KEY (`tawasulFinancePurchaseBillID`),
  UNIQUE KEY `uniq_bill` (`billNumber`),
  KEY `supplier` (`supplierID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceReceipt
$moduleTables[] = 'CREATE TABLE `tawasulFinanceReceipt` (
  `tawasulFinanceReceiptID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `receiptNumber` varchar(30) NOT NULL,
  `tawasulFinanceInvoiceID` int(10) unsigned zerofill NOT NULL,
  `tawasulFinanceCashAccountID` int(10) unsigned zerofill NOT NULL,
  `date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `method` enum(\'Cash\',\'Transfer\',\'Cheque\',\'Card\') NOT NULL DEFAULT \'Cash\',
  `reference` varchar(60) DEFAULT NULL,
  `tawasulPersonIDReceiver` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceReceiptID`),
  UNIQUE KEY `uniq_receipt` (`receiptNumber`),
  KEY `invoice` (`tawasulFinanceInvoiceID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceSalaryComponent
$moduleTables[] = 'CREATE TABLE `tawasulFinanceSalaryComponent` (
  `tawasulFinanceSalaryComponentID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT \'\',
  `type` varchar(30) NOT NULL DEFAULT \'Earning\',
  `tawasulFinanceAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceSalaryComponentID`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceSequence
$moduleTables[] = 'CREATE TABLE `tawasulFinanceSequence` (
  `documentType` varchar(20) NOT NULL,
  `prefix` varchar(10) NOT NULL DEFAULT \'\',
  `nextNumber` int NOT NULL DEFAULT \'1\',
  PRIMARY KEY (`documentType`),
  KEY `prefix` (`prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceStaffSalary
$moduleTables[] = 'CREATE TABLE `tawasulFinanceStaffSalary` (
  `tawasulFinanceStaffSalaryID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulPersonID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceSalaryComponentID` int(10) unsigned zerofill DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT \'0.00\',
  `tawasulFinanceCostCenterID` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceStaffSalaryID`),
  KEY `person` (`tawasulPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceStudentDiscount
$moduleTables[] = 'CREATE TABLE `tawasulFinanceStudentDiscount` (
  `tawasulFinanceStudentDiscountID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulPersonID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceDiscountID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulSchoolYearID` int(3) unsigned zerofill DEFAULT NULL,
  `tawasulPersonIDApprover` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceStudentDiscountID`),
  KEY `person` (`tawasulPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceSupplier
$moduleTables[] = 'CREATE TABLE `tawasulFinanceSupplier` (
  `tawasulFinanceSupplierID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT \'\',
  `phone` varchar(150) NOT NULL DEFAULT \'\',
  `email` varchar(150) NOT NULL DEFAULT \'\',
  `taxNumber` varchar(150) NOT NULL DEFAULT \'\',
  `tawasulFinancePayableAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceSupplierID`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// tawasulFinanceTransfer
$moduleTables[] = 'CREATE TABLE `tawasulFinanceTransfer` (
  `tawasulFinanceTransferID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceFromCashAccountID` int(10) unsigned zerofill NOT NULL,
  `toCashAccountID` int(10) unsigned zerofill NOT NULL,
  `date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `memo` varchar(255) DEFAULT NULL,
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceTransferID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

// ---------------- Seed data ----------------
$moduleTables[] = 'INSERT INTO `tawasulFinanceFeeCategory` (`tawasulFinanceFeeCategoryID`,`name`,`nameShort`,`description`,`active`,`tawasulPersonIDCreator`,`timestampCreator`,`tawasulPersonIDUpdate`,`timestampUpdate`) VALUES (\'0001\',\'Other\',\'OTHR\',\'Category for fees not fitting into any other category.\',\'Y\',\'0000000001\',\'2013-07-12 10:25:32\',NULL,NULL)';

$moduleTables[] = 'INSERT INTO `tawasulFinanceFeeCategory` (`tawasulFinanceFeeCategoryID`,`name`,`nameShort`,`description`,`active`,`tawasulPersonIDCreator`,`timestampCreator`,`tawasulPersonIDUpdate`,`timestampUpdate`) VALUES (\'0002\',\'القسط الشهري\',\'قسط\',\'\',\'Y\',\'0000000001\',\'2026-09-26 07:34:06\',NULL,NULL)';

$moduleTables[] = 'INSERT INTO `tawasulFinanceFeeCategory` (`tawasulFinanceFeeCategoryID`,`name`,`nameShort`,`description`,`active`,`tawasulPersonIDCreator`,`timestampCreator`,`tawasulPersonIDUpdate`,`timestampUpdate`) VALUES (\'0003\',\'رسوم المدرسة\',\'الرسوم\',\'\',\'Y\',\'0000000001\',\'2026-09-26 07:34:33\',NULL,NULL)';

$moduleTables[] = 'INSERT INTO `tawasulFinanceFeeCategory` (`tawasulFinanceFeeCategoryID`,`name`,`nameShort`,`description`,`active`,`tawasulPersonIDCreator`,`timestampCreator`,`tawasulPersonIDUpdate`,`timestampUpdate`) VALUES (\'0005\',\'رسوم الكتب\',\'الكتب\',\'\',\'Y\',\'0000000001\',\'2026-09-26 11:07:09\',NULL,NULL)';
// ---------------- Actions ----------------
// Billing, expenses and petty cash permissions.
// Keys are explicit: the installer reads each field by name, so a
// positional array here would install NULL action names.
$actionRows[] = ['name' => 'Manage Billing Schedule', 'precedence' => '0', 'category' => 'Billing', 'description' => 'Allows users to create, view and edit billing windows.', 'URLList' => 'billingSchedule_manage.php,billingSchedule_manage_edit.php,billingSchedule_manage_add.php', 'entryURL' => 'billingSchedule_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y', 'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
$actionRows[] = ['name' => 'Manage Budget Cycles', 'precedence' => '0', 'category' => 'Expenses', 'description' => 'Allows a sufficiently priviledged user to create and manage budget cycles.', 'URLList' => 'budgetCycles_manage.php,budgetCycles_manage_add.php,budgetCycles_manage_edit.php,budgetCycles_manage_delete.php', 'entryURL' => 'budgetCycles_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y', 'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
$actionRows[] = ['name' => 'Manage Budgets', 'precedence' => '0', 'category' => 'Expenses', 'description' => 'Allows users to create, edit and delete budgets.', 'URLList' => 'budgets_manage.php,budgets_manage_add.php,budgets_manage_edit.php,budgets_manage_delete.php', 'entryURL' => 'budgets_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y', 'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
$actionRows[] = ['name' => 'Manage Expense Approvers', 'precedence' => '0', 'category' => 'Expenses', 'description' => 'Determines who can approve expense requests, in accordance to the Expense Approval Type setting in School Admin.', 'URLList' => 'expenseApprovers_manage.php,expenseApprovers_manage_add.php,expenseApprovers_manage_edit.php,expenseApprovers_manage_delete.php', 'entryURL' => 'expenseApprovers_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y', 'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
$actionRows[] = ['name' => 'Manage Expenses_all', 'precedence' => '0', 'category' => 'Expenses', 'description' => 'Gives access to full control all expenses across all budgets.', 'URLList' => 'expenses_manage.php, expenses_manage_add.php, expenses_manage_edit.php, expenses_manage_print.php, expenses_manage_approve.php, expenses_manage_view.php', 'entryURL' => 'expenses_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y', 'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
$actionRows[] = ['name' => 'Manage Expenses_myBudgets', 'precedence' => '0', 'category' => 'Expenses', 'description' => 'Gives access to control expenses, according to budget-level access rights.', 'URLList' => 'expenses_manage.php, expenses_manage_edit.php, expenses_manage_print.php, expenses_manage_approve.php, expenses_manage_view.php', 'entryURL' => 'expenses_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y', 'defaultPermissionAdmin' => 'N', 'defaultPermissionTeacher' => 'Y', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'Y', 'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
$actionRows[] = ['name' => 'Manage Fee Categories', 'precedence' => '0', 'category' => 'Billing', 'description' => 'Allows users to create, edit and delete fee categories.', 'URLList' => 'feeCategories_manage.php,feeCategories_manage_add.php,feeCategories_manage_edit.php,feeCategories_manage_delete.php', 'entryURL' => 'feeCategories_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y', 'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
$actionRows[] = ['name' => 'Manage Fees', 'precedence' => '0', 'category' => 'Billing', 'description' => 'Allows users to create, view and edit fees.', 'URLList' => 'fees_manage.php,fees_manage_edit.php,fees_manage_add.php', 'entryURL' => 'fees_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y', 'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
$actionRows[] = ['name' => 'Manage Invoicees', 'precedence' => '0', 'category' => 'Billing', 'description' => 'Allows users to view and edit invoice recipients.', 'URLList' => 'invoicees_manage.php,invoicees_manage_edit.php', 'entryURL' => 'invoicees_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y', 'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
$actionRows[] = ['name' => 'Manage Invoices', 'precedence' => '0', 'category' => 'Billing', 'description' => 'Allows users to generate, view, delete and edit invoices.', 'URLList' => 'invoices_manage.php,invoices_manage_edit.php,invoices_manage_add.php,invoices_manage_delete.php,invoices_manage_view.php,invoices_manage_issue.php,invoices_manage_print.php', 'entryURL' => 'invoices_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y', 'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
$actionRows[] = ['name' => 'My Expense Requests', 'precedence' => '0', 'category' => 'Expenses', 'description' => 'Allows a user to request expenses from budgets they have access to.', 'URLList' => 'expenseRequest_manage.php,expenseRequest_manage_add.php,expenseRequest_manage_view.php,expenseRequest_manage_reimburse.php', 'entryURL' => 'expenseRequest_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y', 'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
$actionRows[] = ['name' => 'Petty Cash', 'precedence' => '0', 'category' => 'Expenses', 'description' => 'Allows users to track basic payments and refunds of petty cash.', 'URLList' => 'pettyCash.php,pettyCash_addEdit.php,pettyCash_delete.php,pettyCash_action.php', 'entryURL' => 'pettyCash.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y', 'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
$actionRows[] = ['name' => 'View Invoices_mine', 'precedence' => '0', 'category' => 'Billing', 'description' => 'Allows a student to view their own invoices.', 'URLList' => 'invoices_view.php, invoices_view_print.php', 'entryURL' => 'invoices_view.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y', 'defaultPermissionAdmin' => 'N', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'N', 'categoryPermissionStudent' => 'Y', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
$actionRows[] = ['name' => 'View Invoices_myChildren', 'precedence' => '1', 'category' => 'Billing', 'description' => 'Allows parents to view invoices issued to members of their family.', 'URLList' => 'invoices_view.php, invoices_view_print.php', 'entryURL' => 'invoices_view.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y', 'defaultPermissionAdmin' => 'N', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'Y', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'N', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'Y', 'categoryPermissionOther' => 'N'];
// Accounting core permissions. These three carry no sidebar entry of their own
// yet (menuShow/entrySidebar are N): the accounting screens are reached from
// the Finance area, and the actions exist so that role permissions can be
// granted and enforced per capability rather than with one blanket billing
// permission. categoryPermissionStaff keeps them in the role editor.
$actionRows[] = ['name' => 'Manage Chart of Accounts', 'precedence' => '0', 'category' => 'Accounting', 'description' => 'Allows users to view and edit the chart of accounts, cost centres and salary components.', 'URLList' => 'accounts_manage.php,accounts_manage_edit.php,costCenters_manage.php,salaryComponents_manage.php', 'entryURL' => 'accounts_manage.php', 'entrySidebar' => 'N', 'menuShow' => 'N', 'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
$actionRows[] = ['name' => 'Manage Fiscal Years', 'precedence' => '0', 'category' => 'Accounting', 'description' => 'Allows users to create fiscal years and to open or close their periods.', 'URLList' => 'fiscalYears_manage.php,fiscalYears_manage_edit.php,periods_manage.php,periods_manage_edit.php', 'entryURL' => 'fiscalYears_manage.php', 'entrySidebar' => 'N', 'menuShow' => 'N', 'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
$actionRows[] = ['name' => 'Manage Journal Entries', 'precedence' => '0', 'category' => 'Accounting', 'description' => 'Allows users to post, draft and reverse general ledger entries, and to read the ledger reports.', 'URLList' => 'journal_manage.php,journal_manage_add.php,journal_manage_view.php,reports_ledger.php', 'entryURL' => 'journal_manage.php', 'entrySidebar' => 'N', 'menuShow' => 'N', 'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N', 'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N'];
