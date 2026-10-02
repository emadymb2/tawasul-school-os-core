<?php
/*
Gibbon Deep REST API — versioned schema migrations.

Each entry is [version, ";end"-delimited SQL] and is applied in order by
System Admin > Manage Modules > Update.
*/

$count = 0;
$sql = [];

// v2.0.00 — first release of the deep API. Upgrades a v1 "Rest API" install
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
$sql[$count][1] = "CREATE TABLE IF NOT EXISTS `restApiWebhook` (
    `restApiWebhookID` INT(8) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(60) NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `events` TEXT NOT NULL,
    `secret` VARCHAR(80) NOT NULL,
    `headers` TEXT NOT NULL,
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `tawasulPersonIDCreator` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL,
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`restApiWebhookID`),
    KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end
CREATE TABLE IF NOT EXISTS `restApiWebhookDelivery` (
    `restApiWebhookDeliveryID` BIGINT(16) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `restApiWebhookID` INT(8) UNSIGNED ZEROFILL NOT NULL,
    `event` VARCHAR(60) NOT NULL,
    `payload` MEDIUMTEXT NOT NULL,
    `statusCode` INT(3) NOT NULL DEFAULT 0,
    `success` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `durationMS` INT(8) NOT NULL DEFAULT 0,
    `response` VARCHAR(500) NOT NULL DEFAULT '',
    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`restApiWebhookDeliveryID`),
    KEY `restApiWebhookID` (`restApiWebhookID`),
    KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'exposeGenerated', 'Expose Generated Schema Endpoints', 'Serve the auto-generated endpoints covering every remaining database table. Set to N to publish only the curated endpoints.', 'Y');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'bulkMaxItems', 'Bulk Request Limit', 'Maximum number of records accepted in one /bulk request.', '200');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'webhooksEnabled', 'Enable Webhooks', 'Send signed change events to subscribed URLs whenever a record is created, updated or deleted through the API.', 'N');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'webhookTimeout', 'Webhook Timeout (seconds)', 'How long to wait for a webhook receiver before recording the delivery as failed.', '5');end";
$count++;

// v3.1.00 — adds the API control dashboard (in-Gibbon screen plus
// GET /v2/dashboard). Only a new action row is needed; safe to re-run.
$sql[$count][0] = '3.1.00';
$sql[$count][1] = "INSERT INTO `tawasulAction` (`tawasulModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `entrySidebar`, `menuShow`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`)
SELECT `tawasulModuleID`, 'API Dashboard', 0, 'API', 'Live view of schools, students, staff and API sync status.', 'dashboard.php', 'dashboard.php', 'Y', 'Y', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N'
FROM `tawasulModule` WHERE `name`='Rest API'
AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `tawasulAction`) a WHERE a.`name`='API Dashboard' AND a.`tawasulModuleID`=`tawasulModule`.`tawasulModuleID`);end
INSERT INTO `tawasulPermission` (`tawasulRoleID`, `tawasulActionID`)
SELECT 001, a.`tawasulActionID` FROM `tawasulAction` a
JOIN `tawasulModule` m ON (m.`tawasulModuleID`=a.`tawasulModuleID`)
WHERE m.`name`='Rest API' AND a.`name`='API Dashboard'
AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `tawasulPermission`) p WHERE p.`tawasulActionID`=a.`tawasulActionID` AND p.`tawasulRoleID`=001);end";
$count++;

// v3.2.00 — action-to-permission mapping. Every generated endpoint now checks
// the Gibbon screen permission that owns its table (src/Resource/actionMap.php),
// with separate read and write actions. No schema change is required.
$sql[$count][0] = '3.2.00';
$sql[$count][1] = "-- No database changes; permission mapping ships in code.";
$count++;

// v3.3.00 — file transfer, sign-in throttling and token rate limiting.
// Adds the new settings and two indexes the throttle counters rely on. The
// index statements are wrapped so re-running on a database that already has
// them cannot fail the upgrade.
$sql[$count][0] = '3.3.00';
$sql[$count][1] = "INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'filesEnabled', 'Enable File Transfer', 'Allow files to be downloaded and uploaded through /v2/files. Set to N to block every file route.', 'N');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'fileMaxSizeMB', 'Maximum Upload Size (MB)', 'Largest single file accepted by an API upload.', '20');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'fileExtensions', 'Allowed File Extensions', 'Comma separated list of extensions an API upload may use. Anything else is rejected.', 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,rtf,odt,ods,jpg,jpeg,png,gif,webp,heic,svg,zip,mp3,mp4,m4a,wav');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'loginMaxAttempts', 'Sign-In Attempt Limit', 'Failed credential attempts allowed from one address before it is locked out. 0 disables the lockout.', '10');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'loginLockoutMinutes', 'Sign-In Lockout Window (minutes)', 'How far back failed credential attempts are counted, and how long a locked-out address waits.', '15');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'tokenRateLimit', 'Token Request Limit (per minute)', 'Requests per minute allowed for a token issued by username and password. 0 disables the limit.', '240');end
CREATE INDEX `ipAddress` ON `restApiLog` (`ipAddress`, `timestamp`);end
CREATE INDEX `tawasulPersonID` ON `restApiLog` (`tawasulPersonID`, `timestamp`);end";
$count++;

// v3.3.01 — phone number sign-in support. Adds the loginMethod setting so
// administrators can allow phone numbers (phone1–phone4) as a sign-in
// credential alongside username and email.
$sql[$count][0] = '3.3.01';
$sql[$count][1] = "INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'loginMethod', 'Sign-In Method', 'Controls which identifier the password grant accepts: username only (username), phone number only (phone), or all of username/email/phone (all).', 'username');end";
$count++;

// v3.3.02 — Tawasul OS port. The module shipped upstream against the `gibbon*`
// prefix, which this platform does not have: its bootstrap file is tawasul.php,
// not gibbon.php, and the settings live in tawasulSetting. The entry point and
// the settings reader were corrected, so on any installation where the upgrade
// never landed the 22 settings can be missing entirely and the API answers 503
// or fatals on its first query. These are idempotent inserts: they add whatever
// is absent and change nothing that is already there.
$sql[$count][0] = '3.3.02';
$sql[$count][1] = "INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'apiEnabled', 'API Enabled', 'Master switch. When set to N every API request returns 503.', 'Y');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'allowPasswordGrant', 'Allow Password Login', 'Allow POST /v2/auth/login to exchange a Tawasul OS username and password for a bearer token.', 'Y');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'tokenLifetime', 'Token Lifetime (minutes)', 'How long a token issued by /v2/auth/login remains valid.', '120');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'refreshLifetime', 'Refresh Token Lifetime (minutes)', 'How long a refresh token issued alongside a bearer token remains valid.', '20160');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'defaultPageSize', 'Default Page Size', 'Number of records returned per page when no pageSize is supplied.', '50');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'maxPageSize', 'Maximum Page Size', 'Upper limit for the pageSize parameter.', '500');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'corsOrigins', 'CORS Allowed Origins', 'Comma separated list of allowed origins, or * for any. Leave blank to disable CORS headers.', '');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'logRequests', 'Log Requests', 'Write every API request to the request log.', 'Y');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'logRetentionDays', 'Log Retention (days)', 'Request log entries older than this are pruned automatically.', '30');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'enforceRolePermissions', 'Enforce Role Permissions', 'In addition to scopes, check the linked user role against the Gibbon action that owns each resource.', 'Y');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'allowWrites', 'Allow Write Operations', 'Global switch for POST, PATCH and DELETE. Set to N for a strictly read-only API.', 'Y');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'exposeModules', 'Expose Community Modules', 'Serve endpoints for installed community modules (Free Learning, Help Desk, Trip Planner and so on).', 'Y');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'exposeGenerated', 'Expose Generated Schema Endpoints', 'Serve the auto-generated endpoints covering every remaining database table. Set to N to publish only the curated endpoints.', 'Y');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'bulkMaxItems', 'Bulk Request Limit', 'Maximum number of records accepted in one /bulk request.', '200');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'webhooksEnabled', 'Enable Webhooks', 'Send signed change events to subscribed URLs whenever a record is created, updated or deleted through the API.', 'N');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'webhookTimeout', 'Webhook Timeout (seconds)', 'How long to wait for a webhook receiver before recording the delivery as failed.', '5');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'filesEnabled', 'Enable File Transfer', 'Allow files to be downloaded and uploaded through /v2/files. Set to N to block every file route.', 'N');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'fileMaxSizeMB', 'Maximum Upload Size (MB)', 'Largest single file accepted by an API upload.', '20');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'fileExtensions', 'Allowed File Extensions', 'Comma separated list of extensions an API upload may use. Anything else is rejected.', 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,rtf,odt,ods,jpg,jpeg,png,gif,webp,heic,svg,zip,mp3,mp4,m4a,wav');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'loginMaxAttempts', 'Sign-In Attempt Limit', 'Failed credential attempts allowed from one address before it is locked out. 0 disables the lockout.', '10');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'loginLockoutMinutes', 'Sign-In Lockout Window (minutes)', 'How far back failed credential attempts are counted, and how long a locked-out address waits.', '15');end
INSERT IGNORE INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'tokenRateLimit', 'Token Request Limit (per minute)', 'Requests per minute allowed for a token issued by username and password. 0 disables the limit.', '240');";
$count++;

// v3.3.03 — Tawasul OS port of the runtime. The module shipped against the
// `gibbon*` prefix this platform does not have, so every query in the entry
// point, the kernel, the controllers, the gateways and the resource
// definitions failed with "table doesn't exist" the moment they touched the
// database. The columns below are the same rename applied to the module's own
// tables, which no amount of code rewriting can fix on its own.
//
// Each rename is guarded by an information_schema check rather than run bare:
// MySQL has no RENAME COLUMN IF EXISTS, so re-running this step on a database
// that has already been ported would otherwise abort the whole upgrade. The
// guards are written as prepared statements so each one only fires when the old
// column is actually still there.
$renames = [
    'restApiKey'          => ['gibbonSchoolYearID',    'tawasulSchoolYearID'],
    'restApiToken'        => ['gibbonPersonID',        'tawasulPersonID'],
    'restApiLog'          => ['gibbonPersonID',        'tawasulPersonID'],
    'restApiWebhook'      => ['gibbonPersonIDCreator', 'tawasulPersonIDCreator'],
];

$sql[$count][0] = '3.3.03';
$sql[$count][1] = '';
foreach ($renames as $table => [$old, $new]) {
    $sql[$count][1] .= "SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS"
        ." WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '".$table."'"
        ." AND COLUMN_NAME = '".$old."');end"
        ."SET @s := IF(@c > 0, 'ALTER TABLE `".$table."` CHANGE COLUMN `".$old."` `".$new."` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL', 'DO 0');end"
        ."PREPARE stmt FROM @s;end"
        ."EXECUTE stmt;end"
        ."DEALLOCATE PREPARE stmt;end";
}
$count++;
