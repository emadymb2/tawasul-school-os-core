<?php
// Upgrade SQL per version. Each ';end' separates statements.
$sql = [];
$count = 0;

// v1.0.00 - initial release
$sql[$count][0] = '1.0.00';
$sql[$count][1] = '';
$count++;

// v1.1.00 - accounting core: chart of accounts, double-entry journal, fiscal
// years and periods, receivables/payables, payroll, fixed assets, cash
// accounts, bank reconciliation and the document sequence. Budgets and
// invoices gain their links into the accounting core.
$sql[$count][0] = '1.1.00';
$sql[$count][1] = <<<'SQL'
CREATE TABLE IF NOT EXISTS `tawasulFinanceAccount` (
  `tawasulFinanceAccountID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `code` varchar(150) NOT NULL DEFAULT '',
  `name` varchar(150) NOT NULL DEFAULT '',
  `type` varchar(30) NOT NULL DEFAULT 'Asset',
  `parentAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `isPosting` varchar(30) NOT NULL DEFAULT 'Y',
  `active` varchar(30) NOT NULL DEFAULT 'Y',
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceAccountID`),
  UNIQUE KEY `uniq_code` (`code`),
  KEY `parent` (`parentAccountID`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceAsset` (
  `tawasulFinanceAssetID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT '',
  `acquisitionDate` date DEFAULT NULL,
  `cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `salvageValue` decimal(15,2) NOT NULL DEFAULT '0.00',
  `usefulLifeYears` int NOT NULL DEFAULT '0',
  `method` varchar(30) NOT NULL DEFAULT 'StraightLine',
  `tawasulFinanceAssetAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceAccumDepAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceExpenseAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceCostCenterID` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceAssetID`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceAuditLog` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceBankReconciliation` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceCashAccount` (
  `tawasulFinanceCashAccountID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT '',
  `type` varchar(30) NOT NULL DEFAULT 'Cash',
  `bankAccountNumber` varchar(150) NOT NULL DEFAULT '',
  `tawasulFinanceAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceCashAccountID`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceCostCenter` (
  `tawasulFinanceCostCenterID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `code` varchar(150) NOT NULL DEFAULT '',
  `name` varchar(150) NOT NULL DEFAULT '',
  `type` varchar(30) NOT NULL DEFAULT 'Stage',
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceCostCenterID`),
  UNIQUE KEY `uniq_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceDepreciation` (
  `tawasulFinanceDepreciationID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceAssetID` int(10) unsigned zerofill NOT NULL,
  `periodEnd` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceDepreciationID`),
  UNIQUE KEY `assetPeriod` (`tawasulFinanceAssetID`,`periodEnd`),
  KEY `asset_end` (`tawasulFinanceAssetID`,`periodEnd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceDiscount` (
  `tawasulFinanceDiscountID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT '',
  `kind` varchar(30) NOT NULL DEFAULT 'Sibling',
  `method` varchar(30) NOT NULL DEFAULT 'Percent',
  `value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `tawasulFinanceExpenseAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceDiscountID`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceFeeItem` (
  `tawasulFinanceFeeItemID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT '',
  `category` varchar(30) NOT NULL DEFAULT 'Registration',
  `tawasulFinanceRevenueAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `defaultAmount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceFeeItemID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceFeePlan` (
  `tawasulFinanceFeePlanID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT '',
  `tawasulSchoolYearID` int(3) unsigned zerofill DEFAULT NULL,
  `tawasulYearGroupID` int(3) unsigned zerofill DEFAULT NULL,
  `installments` int NOT NULL DEFAULT '0',
  `tawasulFinanceCostCenterID` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceFeePlanID`),
  KEY `year_group` (`tawasulSchoolYearID`,`tawasulYearGroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceFeePlanItem` (
  `tawasulFinanceFeePlanItemID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceFeePlanID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceFeeItemID` int(10) unsigned zerofill DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceFeePlanItemID`),
  KEY `plan_item` (`tawasulFinanceFeePlanID`,`tawasulFinanceFeeItemID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceFiscalYear` (
  `tawasulFinanceFiscalYearID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT '',
  `firstDay` date DEFAULT NULL,
  `lastDay` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Open',
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceFiscalYearID`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceJournalEntry` (
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `documentNumber` varchar(30) NOT NULL,
  `documentType` varchar(20) NOT NULL DEFAULT 'JV',
  `date` date NOT NULL,
  `tawasulFinancePeriodID` int(10) unsigned zerofill DEFAULT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `sourceType` varchar(30) DEFAULT NULL,
  `sourceID` int(10) unsigned zerofill DEFAULT NULL,
  `status` enum('Draft','Posted','Reversed') NOT NULL DEFAULT 'Draft',
  `isRecurring` enum('N','Y') NOT NULL DEFAULT 'N',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceJournalLine` (
  `tawasulFinanceJournalLineID` int(12) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill NOT NULL,
  `tawasulFinanceAccountID` int(10) unsigned zerofill NOT NULL,
  `tawasulFinanceCostCenterID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulPersonID` int(10) unsigned zerofill DEFAULT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `memo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceJournalLineID`),
  KEY `entry` (`tawasulFinanceJournalEntryID`),
  KEY `account` (`tawasulFinanceAccountID`),
  KEY `line_acct_date` (`tawasulFinanceAccountID`,`tawasulFinanceJournalEntryID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinancePaymentVoucher` (
  `tawasulFinancePaymentVoucherID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `voucherNumber` varchar(30) NOT NULL,
  `tawasulFinancePurchaseBillID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceCashAccountID` int(10) unsigned zerofill NOT NULL,
  `date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `method` enum('Cash','Transfer','Cheque') NOT NULL DEFAULT 'Cash',
  `reference` varchar(60) DEFAULT NULL,
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill DEFAULT NULL,
  PRIMARY KEY (`tawasulFinancePaymentVoucherID`),
  UNIQUE KEY `uniq_voucher` (`voucherNumber`),
  KEY `bill` (`tawasulFinancePurchaseBillID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinancePayrollLine` (
  `tawasulFinancePayrollLineID` int(12) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinancePayrollRunID` int(10) unsigned zerofill NOT NULL,
  `tawasulPersonID` int(10) unsigned zerofill NOT NULL,
  `tawasulFinanceSalaryComponentID` int(10) unsigned zerofill NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  PRIMARY KEY (`tawasulFinancePayrollLineID`),
  KEY `run_person` (`tawasulFinancePayrollRunID`,`tawasulPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinancePayrollRun` (
  `tawasulFinancePayrollRunID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `month` char(7) NOT NULL,
  `tawasulFinanceCashAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `totalEarnings` decimal(15,2) NOT NULL DEFAULT '0.00',
  `totalDeductions` decimal(15,2) NOT NULL DEFAULT '0.00',
  `netPay` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('Draft','Posted') NOT NULL DEFAULT 'Draft',
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill DEFAULT NULL,
  PRIMARY KEY (`tawasulFinancePayrollRunID`),
  UNIQUE KEY `month` (`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinancePeriod` (
  `tawasulFinancePeriodID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceFiscalYearID` int(10) unsigned zerofill DEFAULT NULL,
  `name` varchar(150) NOT NULL DEFAULT '',
  `startDate` date DEFAULT NULL,
  `endDate` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Open',
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinancePeriodID`),
  KEY `fy_dates` (`tawasulFinanceFiscalYearID`,`startDate`),
  KEY `dates` (`startDate`,`endDate`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinancePurchaseBill` (
  `tawasulFinancePurchaseBillID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `billNumber` varchar(30) NOT NULL,
  `supplierID` int(10) unsigned zerofill NOT NULL,
  `date` date NOT NULL,
  `dueDate` date DEFAULT NULL,
  `tawasulFinanceExpenseAccountID` int(10) unsigned zerofill NOT NULL,
  `tawasulFinanceCostCenterID` int(10) unsigned zerofill DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `taxAmount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `paidAmount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `description` varchar(255) DEFAULT NULL,
  `status` enum('Open','Partial','Paid','Cancelled') NOT NULL DEFAULT 'Open',
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill DEFAULT NULL,
  PRIMARY KEY (`tawasulFinancePurchaseBillID`),
  UNIQUE KEY `uniq_bill` (`billNumber`),
  KEY `supplier` (`supplierID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceReceipt` (
  `tawasulFinanceReceiptID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `receiptNumber` varchar(30) NOT NULL,
  `tawasulFinanceInvoiceID` int(10) unsigned zerofill NOT NULL,
  `tawasulFinanceCashAccountID` int(10) unsigned zerofill NOT NULL,
  `date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `method` enum('Cash','Transfer','Cheque','Card') NOT NULL DEFAULT 'Cash',
  `reference` varchar(60) DEFAULT NULL,
  `tawasulPersonIDReceiver` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceReceiptID`),
  UNIQUE KEY `uniq_receipt` (`receiptNumber`),
  KEY `invoice` (`tawasulFinanceInvoiceID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceSalaryComponent` (
  `tawasulFinanceSalaryComponentID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT '',
  `type` varchar(30) NOT NULL DEFAULT 'Earning',
  `tawasulFinanceAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceSalaryComponentID`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceSequence` (
  `documentType` varchar(20) NOT NULL,
  `prefix` varchar(10) NOT NULL DEFAULT '',
  `nextNumber` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`documentType`),
  KEY `prefix` (`prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceStaffSalary` (
  `tawasulFinanceStaffSalaryID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulPersonID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceSalaryComponentID` int(10) unsigned zerofill DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `tawasulFinanceCostCenterID` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceStaffSalaryID`),
  KEY `person` (`tawasulPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceStudentDiscount` (
  `tawasulFinanceStudentDiscountID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulPersonID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulFinanceDiscountID` int(10) unsigned zerofill DEFAULT NULL,
  `tawasulSchoolYearID` int(3) unsigned zerofill DEFAULT NULL,
  `tawasulPersonIDApprover` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceStudentDiscountID`),
  KEY `person` (`tawasulPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceSupplier` (
  `tawasulFinanceSupplierID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT '',
  `phone` varchar(150) NOT NULL DEFAULT '',
  `email` varchar(150) NOT NULL DEFAULT '',
  `taxNumber` varchar(150) NOT NULL DEFAULT '',
  `tawasulFinancePayableAccountID` int(10) unsigned zerofill DEFAULT NULL,
  `timestampCreator` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tawasulFinanceSupplierID`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
CREATE TABLE IF NOT EXISTS `tawasulFinanceTransfer` (
  `tawasulFinanceTransferID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `tawasulFinanceFromCashAccountID` int(10) unsigned zerofill NOT NULL,
  `toCashAccountID` int(10) unsigned zerofill NOT NULL,
  `date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `memo` varchar(255) DEFAULT NULL,
  `tawasulFinanceJournalEntryID` int(10) unsigned zerofill DEFAULT NULL,
  PRIMARY KEY (`tawasulFinanceTransferID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;end
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tawasulFinanceBudget' AND column_name = 'tawasulFinanceFiscalYearID');
SET @s := IF(@c = 0, 'ALTER TABLE `tawasulFinanceBudget` ADD COLUMN `tawasulFinanceFiscalYearID` int(10) unsigned zerofill NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;;end
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tawasulFinanceBudget' AND column_name = 'tawasulFinanceAccountID');
SET @s := IF(@c = 0, 'ALTER TABLE `tawasulFinanceBudget` ADD COLUMN `tawasulFinanceAccountID` int(10) unsigned zerofill NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;;end
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tawasulFinanceBudget' AND column_name = 'tawasulFinanceCostCenterID');
SET @s := IF(@c = 0, 'ALTER TABLE `tawasulFinanceBudget` ADD COLUMN `tawasulFinanceCostCenterID` int(10) unsigned zerofill NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;;end
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tawasulFinanceBudget' AND column_name = 'amount');
SET @s := IF(@c = 0, 'ALTER TABLE `tawasulFinanceBudget` ADD COLUMN `amount` decimal(15,2) NOT NULL DEFAULT 0.00', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;;end
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tawasulFinanceInvoice' AND column_name = 'tawasulFinanceJournalEntryID');
SET @s := IF(@c = 0, 'ALTER TABLE `tawasulFinanceInvoice` ADD COLUMN `tawasulFinanceJournalEntryID` int(10) unsigned zerofill NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;;end
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tawasulFinanceInvoice' AND column_name = 'grossAmount');
SET @s := IF(@c = 0, 'ALTER TABLE `tawasulFinanceInvoice` ADD COLUMN `grossAmount` decimal(15,2) NOT NULL DEFAULT 0.00', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;;end
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tawasulFinanceInvoice' AND column_name = 'discountAmount');
SET @s := IF(@c = 0, 'ALTER TABLE `tawasulFinanceInvoice` ADD COLUMN `discountAmount` decimal(15,2) NOT NULL DEFAULT 0.00', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;;end
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tawasulFinanceInvoice' AND column_name = 'netAmount');
SET @s := IF(@c = 0, 'ALTER TABLE `tawasulFinanceInvoice` ADD COLUMN `netAmount` decimal(15,2) NOT NULL DEFAULT 0.00', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;;end
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tawasulFinanceInvoice' AND column_name = 'postedDate');
SET @s := IF(@c = 0, 'ALTER TABLE `tawasulFinanceInvoice` ADD COLUMN `postedDate` date NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;;end
SET @c := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'tawasulFinanceBudget' AND index_name = 'fy_account');
SET @s := IF(@c = 0, 'ALTER TABLE `tawasulFinanceBudget` ADD INDEX `fy_account` (`tawasulFinanceFiscalYearID`,`tawasulFinanceAccountID`)', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;;end
SET @c := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'tawasulFinanceBudget' AND index_name = 'fiscal_year');
SET @s := IF(@c = 0, 'ALTER TABLE `tawasulFinanceBudget` ADD INDEX `fiscal_year` (`tawasulFinanceFiscalYearID`)', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;;end
SET @c := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'tawasulFinanceInvoice' AND index_name = 'je');
SET @s := IF(@c = 0, 'ALTER TABLE `tawasulFinanceInvoice` ADD INDEX `je` (`tawasulFinanceJournalEntryID`)', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;;end
SQL;
$count++;

// v1.1.01 - accounting permission actions.
//
// The REST definitions for the chart of accounts, the fiscal calendar and the
// journal gate on these three actions so that posting to the general ledger can
// be granted separately from invoicing. A table update cannot be reverted, so
// the rows are inserted only when absent and are matched on (module, name) so
// re-running this file is harmless.
$sql[$count][0] = '1.1.01';
$sql[$count][1] = <<<'SQL'
INSERT INTO `tawasulAction` (`tawasulModuleID`,`name`,`precedence`,`category`,`description`,`helpURL`,`URLList`,`entryURL`,`entrySidebar`,`menuShow`,`defaultPermissionAdmin`,`defaultPermissionTeacher`,`defaultPermissionStudent`,`defaultPermissionParent`,`defaultPermissionSupport`,`categoryPermissionStaff`,`categoryPermissionStudent`,`categoryPermissionParent`,`categoryPermissionOther`)
SELECT m.tawasulModuleID, 'Manage Chart of Accounts', 0, 'Accounting', 'Allows users to view and edit the chart of accounts, cost centres and salary components.', '', 'accounts_manage.php,accounts_manage_edit.php,costCenters_manage.php,salaryComponents_manage.php', 'accounts_manage.php', 'N', 'N', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N'
  FROM `tawasulModule` m
 WHERE m.name = 'TawasulFinance'
   AND NOT EXISTS (SELECT 1 FROM `tawasulAction` a WHERE a.tawasulModuleID = m.tawasulModuleID AND a.name = 'Manage Chart of Accounts');;end
INSERT INTO `tawasulAction` (`tawasulModuleID`,`name`,`precedence`,`category`,`description`,`helpURL`,`URLList`,`entryURL`,`entrySidebar`,`menuShow`,`defaultPermissionAdmin`,`defaultPermissionTeacher`,`defaultPermissionStudent`,`defaultPermissionParent`,`defaultPermissionSupport`,`categoryPermissionStaff`,`categoryPermissionStudent`,`categoryPermissionParent`,`categoryPermissionOther`)
SELECT m.tawasulModuleID, 'Manage Fiscal Years', 0, 'Accounting', 'Allows users to create fiscal years and to open or close their periods.', '', 'fiscalYears_manage.php,fiscalYears_manage_edit.php,periods_manage.php,periods_manage_edit.php', 'fiscalYears_manage.php', 'N', 'N', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N'
  FROM `tawasulModule` m
 WHERE m.name = 'TawasulFinance'
   AND NOT EXISTS (SELECT 1 FROM `tawasulAction` a WHERE a.tawasulModuleID = m.tawasulModuleID AND a.name = 'Manage Fiscal Years');;end
INSERT INTO `tawasulAction` (`tawasulModuleID`,`name`,`precedence`,`category`,`description`,`helpURL`,`URLList`,`entryURL`,`entrySidebar`,`menuShow`,`defaultPermissionAdmin`,`defaultPermissionTeacher`,`defaultPermissionStudent`,`defaultPermissionParent`,`defaultPermissionSupport`,`categoryPermissionStaff`,`categoryPermissionStudent`,`categoryPermissionParent`,`categoryPermissionOther`)
SELECT m.tawasulModuleID, 'Manage Journal Entries', 0, 'Accounting', 'Allows users to post, draft and reverse general ledger entries, and to read the ledger reports.', '', 'journal_manage.php,journal_manage_add.php,journal_manage_view.php,reports_ledger.php', 'journal_manage.php', 'N', 'N', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N'
  FROM `tawasulModule` m
 WHERE m.name = 'TawasulFinance'
   AND NOT EXISTS (SELECT 1 FROM `tawasulAction` a WHERE a.tawasulModuleID = m.tawasulModuleID AND a.name = 'Manage Journal Entries');;end
SQL;
$count++;

// v1.2.00 -- the SchoolAccounting module was merged into this one.
//
// The two modules each shipped a whole accounting system with their own tables,
// their own chart of accounts and their own set of pages, and the two charts
// shared no codes at all. TawasulFinance survives, because it is the one with
// real postings hanging off it. SchoolAccounting held no postings, so nothing
// transactional crossed: the merge carried a chart and a set of pages.
//
// What this migration does:
//   * moves the 13 module settings into the Finance scope, and repoints the six
//     that named an account code onto the code that account resolved to;
//   * adopts the retired module's 39 actions so every tawasulPermission row
//     stays valid, and deletes its module row;
//   * adds the four chart accounts the old chart had and this one lacked.
//
// docs/schoolaccounting_chart_merge.md is generated from the same mapping and
// records which old code resolved to which. The 29 tawasulSchoolAccounting*
// tables are dropped separately: their content was only the retired chart and
// its document sequences, both superseded above.
$sql[$count][0] = '1.2.00';
$sql[$count][1] = <<<'SQL'
UPDATE `tawasulSetting` SET scope = 'Finance' WHERE scope = 'School Accounting';end
UPDATE `tawasulSetting` SET value = '1200' WHERE scope = 'Finance' AND name = 'receivableAccountCode';end
UPDATE `tawasulSetting` SET value = '2100' WHERE scope = 'Finance' AND name = 'payableAccountCode';end
UPDATE `tawasulSetting` SET value = '2120' WHERE scope = 'Finance' AND name = 'salariesPayableCode';end
UPDATE `tawasulSetting` SET value = '2130' WHERE scope = 'Finance' AND name = 'taxAccountCode';end
UPDATE `tawasulSetting` SET value = '3200' WHERE scope = 'Finance' AND name = 'retainedEarningsCode';end
UPDATE `tawasulSetting` SET value = '5240' WHERE scope = 'Finance' AND name = 'discountAccountCode';end
UPDATE `tawasulAction` SET tawasulModuleID = (SELECT tawasulModuleID FROM `tawasulModule` WHERE name = 'TawasulFinance'), entryURL = REPLACE(entryURL, '/modules/SchoolAccounting/', '/modules/TawasulFinance/'), helpURL = REPLACE(helpURL, '/modules/SchoolAccounting/', '/modules/TawasulFinance/') WHERE tawasulModuleID = (SELECT tawasulModuleID FROM `tawasulModule` WHERE name = 'SchoolAccounting');end
DELETE FROM `tawasulModule` WHERE name = 'SchoolAccounting';end
INSERT INTO `tawasulFinanceAccount` (code,name,type,isPosting,parentAccountID,active) VALUES ('1410','Staff Advances / سلف الموظفين','Asset','Y','1000','Y') ON DUPLICATE KEY UPDATE name = VALUES(name), type = VALUES(type), parentAccountID = VALUES(parentAccountID);end
INSERT INTO `tawasulFinanceAccount` (code,name,type,isPosting,parentAccountID,active) VALUES ('4180','Other Income / إيرادات أخرى','Revenue','Y','4000','Y') ON DUPLICATE KEY UPDATE name = VALUES(name), type = VALUES(type), parentAccountID = VALUES(parentAccountID);end
INSERT INTO `tawasulFinanceAccount` (code,name,type,isPosting,parentAccountID,active) VALUES ('5105','Staff Allowances / البدلات','Expense','Y','5000','Y') ON DUPLICATE KEY UPDATE name = VALUES(name), type = VALUES(type), parentAccountID = VALUES(parentAccountID);end
INSERT INTO `tawasulFinanceAccount` (code,name,type,isPosting,parentAccountID,active) VALUES ('5115','Rent / الإيجارات','Expense','Y','5000','Y') ON DUPLICATE KEY UPDATE name = VALUES(name), type = VALUES(type), parentAccountID = VALUES(parentAccountID)
SQL;

$count++;
