<?php
/*
TawasulOS Deep REST API — versioned schema migrations.

Each entry is [version, ";end"-delimited SQL] and is applied in order by
System Admin > Manage Modules > Update.
*/

$count = 0;
$sql = [];

// v2.0.00 — first release of the deep API. Upgrades a v1 "TawasulCore" install
// (tables restAPIKey / restAPIToken / restAPILog) to the v2 table names and
// adds the new columns. Statements are written so a fresh install, which
// already has the v2 tables from manifest.php, is unaffected.
$sql[$count][0] = '2.0.00';
$sql[$count][1] = "";
$count++;

// v3.0.00 — full schema coverage, relation expansion, batch writes and signed
// webhooks. Adds the two webhook tables and the five new settings. Every
// statement is written to be safe on a fresh install, where manifest.php has
// already created the tables, and on a re-run.
$sql[$count][0] = '3.0.00';
$sql[$count][1] = "CREATE TABLE IF NOT EXISTS `tos_api_webhook` (
    `tos_api_webhookID` INT(8) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(60) NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `events` TEXT NOT NULL,
    `secret` VARCHAR(80) NOT NULL,
    `headers` TEXT NOT NULL,
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `tawasulPersonIDCreator` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL,
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tos_api_webhookID`),
    KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end
CREATE TABLE IF NOT EXISTS `tos_api_webhookDelivery` (
    `tos_api_webhookDeliveryID` BIGINT(16) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `tos_api_webhookID` INT(8) UNSIGNED ZEROFILL NOT NULL,
    `event` VARCHAR(60) NOT NULL,
    `payload` MEDIUMTEXT NOT NULL,
    `statusCode` INT(3) NOT NULL DEFAULT 0,
    `success` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `durationMS` INT(8) NOT NULL DEFAULT 0,
    `response` VARCHAR(500) NOT NULL DEFAULT '',
    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tos_api_webhookDeliveryID`),
    KEY `tos_api_webhookID` (`tos_api_webhookID`),
    KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulCore', 'exposeGenerated', 'Expose Generated Schema Endpoints', 'Serve the auto-generated endpoints covering every remaining database table. Set to N to publish only the curated endpoints.', 'Y');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulCore', 'bulkMaxItems', 'Bulk Request Limit', 'Maximum number of records accepted in one /bulk request.', '200');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulCore', 'webhooksEnabled', 'Enable Webhooks', 'Send signed change events to subscribed URLs whenever a record is created, updated or deleted through the API.', 'N');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulCore', 'webhookTimeout', 'Webhook Timeout (seconds)', 'How long to wait for a webhook receiver before recording the delivery as failed.', '5');end";
$count++;

// v3.1.00 — adds the API control dashboard (in-TawasulOS screen plus
// GET /v2/dashboard). Only a new action row is needed; safe to re-run.
$sql[$count][0] = '3.1.00';
$sql[$count][1] = "INSERT INTO `tawasulAction` (`tawasulModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `entrySidebar`, `menuShow`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`)
SELECT `tawasulModuleID`, 'API Dashboard', 0, 'API', 'Live view of schools, students, staff and API sync status.', 'dashboard.php', 'dashboard.php', 'Y', 'Y', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N'
FROM `tawasulModule` WHERE `name`='TawasulCore'
AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `tawasulAction`) a WHERE a.`name`='API Dashboard' AND a.`tawasulModuleID`=`tawasulModule`.`tawasulModuleID`);end
INSERT INTO `tawasulPermission` (`tawasulRoleID`, `tawasulActionID`)
SELECT 001, a.`tawasulActionID` FROM `tawasulAction` a
JOIN `tawasulModule` m ON (m.`tawasulModuleID`=a.`tawasulModuleID`)
WHERE m.`name`='TawasulCore' AND a.`name`='API Dashboard'
AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `tawasulPermission`) p WHERE p.`tawasulActionID`=a.`tawasulActionID` AND p.`tawasulRoleID`=001);end";
$count++;

// v3.2.00 — action-to-permission mapping. Every generated endpoint now checks
// the TawasulOS screen permission that owns its table (src/Resource/actionMap.php),
// with separate read and write actions. No schema change is required.
$sql[$count][0] = '3.2.00';
$sql[$count][1] = "-- No database changes; permission mapping ships in code.";
$count++;

// v3.3.00 — file transfer, sign-in throttling and token rate limiting.
// Adds the new settings and two indexes the throttle counters rely on. The
// index statements are wrapped so re-running on a database that already has
// them cannot fail the upgrade.
$sql[$count][0] = '3.3.00';
$sql[$count][1] = "INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulCore', 'filesEnabled', 'Enable File Transfer', 'Allow files to be downloaded and uploaded through /v2/files. Set to N to block every file route.', 'N');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulCore', 'fileMaxSizeMB', 'Maximum Upload Size (MB)', 'Largest single file accepted by an API upload.', '20');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulCore', 'fileExtensions', 'Allowed File Extensions', 'Comma separated list of extensions an API upload may use. Anything else is rejected.', 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,rtf,odt,ods,jpg,jpeg,png,gif,webp,heic,svg,zip,mp3,mp4,m4a,wav');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulCore', 'loginMaxAttempts', 'Sign-In Attempt Limit', 'Failed credential attempts allowed from one address before it is locked out. 0 disables the lockout.', '10');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulCore', 'loginLockoutMinutes', 'Sign-In Lockout Window (minutes)', 'How far back failed credential attempts are counted, and how long a locked-out address waits.', '15');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulCore', 'tokenRateLimit', 'Token Request Limit (per minute)', 'Requests per minute allowed for a token issued by username and password. 0 disables the limit.', '240');end
CREATE INDEX `ipAddress` ON `tos_api_log` (`ipAddress`, `timestamp`);end
CREATE INDEX `tawasulPersonID` ON `tos_api_log` (`tawasulPersonID`, `timestamp`);end";
$count++;

// v3.3.01 — phone number sign-in support. Adds the loginMethod setting so
// administrators can allow phone numbers (phone1–phone4) as a sign-in
// credential alongside username and email.
$sql[$count][0] = '3.3.01';
$sql[$count][1] = "INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulCore', 'loginMethod', 'Sign-In Method', 'Controls which identifier the password grant accepts: username only (username), phone number only (phone), or all of username/email/phone (all).', 'username');end";
$count++;

// v3.4.00 - accounting REST surface and shared-endpoint repairs.
//
// No schema change: this release is entirely code. The entry exists so that
// update.php records the version and does not re-run anything.
//
//   - definitions/accounting.php publishes the chart of accounts, the fiscal
//     calendar and the ledger, with the journal tables read-only.
//   - AccountingController owns posting, drafting, reversal and the ledger
//     reports, because those cannot be expressed as a generic CRUD.
//   - Permissions resolves a module by bare or prefixed name in both gates.
//   - routeTable() reports the scope authorise() actually enforces.
//   - QueryBuilder::getWhereSQL() is public, restoring /aggregate and /distinct.
//   - "field" is a reserved query parameter, so /distinct can read its own.
//   - a missing table answers 503 schema_incomplete rather than 500.
$sql[$count][0] = '3.4.00';
$sql[$count][1] = '';
$count++;

// v3.4.01 - write-path validation and CSV export safety.
//
// No schema change; all code.
//
//   - the accounting definitions now declare 'types' and 'maxLength' derived
//     from the manifest's column types, so a bad date or amount is a 422 that
//     names the field instead of a silent zero date or 0.00.
//   - every accounting column the schema holds NOT NULL with no default is
//     declared required, so omitting one no longer stores 0.
//   - CSV export prefixes a value a spreadsheet would evaluate. The JSON export
//     is untouched, so API clients still read the original value.
$sql[$count][0] = '3.4.01';
$sql[$count][1] = '';
$count++;
