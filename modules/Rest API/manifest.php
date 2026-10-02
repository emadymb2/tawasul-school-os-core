<?php
/*
Tawasul Deep REST API — module manifest.

Install: copy the whole "Rest API" folder into Tawasul's /modules folder, then
go to System Admin > Manage Modules > Install to run the SQL below.
Licensed GNU GPL v3 or later, matching Tawasul core.
*/

// BASIC MODULE METADATA
$name = 'Rest API';
$description = 'A deep, self-documenting REST API for Tawasul OS. Covers the whole core schema and every installed community module: users, families, students, staff, school structure, courses, timetable, attendance, markbook, behaviour, planner, activities, library, finance, messenger, individual needs, medical, forms and system administration. Full read and write coverage with relation expansion, batch writes, signed webhooks, scoped API keys, bearer tokens, role-permission enforcement, rate limiting, request logging and a generated OpenAPI 3 document.';
$entryURL = 'dashboard.php';
$type = 'Additional';
$category = 'Admin';
$version = '3.3.03';
$author = 'Tawasul Deep REST API';
$url = 'https://github.com/emadymb';

// DATABASE TABLES
$moduleTables[] = "CREATE TABLE `restApiKey` (
    `restApiKeyID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(60) NOT NULL,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `keyPrefix` VARCHAR(16) NOT NULL,
    `keyHash` CHAR(64) NOT NULL,
    `tawasulPersonID` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL,
    `tawasulSchoolYearID` INT(3) UNSIGNED ZEROFILL NULL DEFAULT NULL,
    `scopes` TEXT NOT NULL,
    `ipAllowList` TEXT NOT NULL,
    `rateLimit` INT(6) NOT NULL DEFAULT 600,
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `dateExpiry` DATE NULL DEFAULT NULL,
    `lastAccess` TIMESTAMP NULL DEFAULT NULL,
    `lastIPAddress` VARCHAR(45) NOT NULL DEFAULT '',
    `tawasulPersonIDCreator` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL,
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`restApiKeyID`),
    UNIQUE KEY `keyPrefix` (`keyPrefix`),
    KEY `keyHash` (`keyHash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `restApiToken` (
    `restApiTokenID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `tawasulPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `tokenHash` CHAR(64) NOT NULL,
    `refreshHash` CHAR(64) NULL DEFAULT NULL,
    `scopes` TEXT NOT NULL,
    `ipAddress` VARCHAR(45) NOT NULL DEFAULT '',
    `userAgent` VARCHAR(255) NOT NULL DEFAULT '',
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampExpiry` TIMESTAMP NULL DEFAULT NULL,
    `timestampLastUse` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`restApiTokenID`),
    UNIQUE KEY `tokenHash` (`tokenHash`),
    KEY `refreshHash` (`refreshHash`),
    KEY `tawasulPersonID` (`tawasulPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `restApiLog` (
    `restApiLogID` BIGINT(16) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `restApiKeyID` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL,
    `tawasulPersonID` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL,
    `method` VARCHAR(8) NOT NULL,
    `endpoint` VARCHAR(255) NOT NULL,
    `resource` VARCHAR(60) NOT NULL DEFAULT '',
    `statusCode` INT(3) NOT NULL DEFAULT 200,
    `durationMS` INT(8) NOT NULL DEFAULT 0,
    `message` VARCHAR(255) NOT NULL DEFAULT '',
    `ipAddress` VARCHAR(45) NOT NULL DEFAULT '',
    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`restApiLogID`),
    KEY `restApiKeyID` (`restApiKeyID`),
    KEY `timestamp` (`timestamp`),
    KEY `ipAddress` (`ipAddress`, `timestamp`),
    KEY `tawasulPersonID` (`tawasulPersonID`, `timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `restApiWebhook` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `restApiWebhookDelivery` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// MODULE SETTINGS
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'apiEnabled', 'API Enabled', 'Master switch. When set to N every API request returns 503.', 'Y');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'allowPasswordGrant', 'Allow Password Login', 'Allow POST /v2/auth/login to exchange a Tawasul OS username and password for a bearer token.', 'Y');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'tokenLifetime', 'Token Lifetime (minutes)', 'How long a token issued by /v2/auth/login remains valid.', '120');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'refreshLifetime', 'Refresh Token Lifetime (minutes)', 'How long a refresh token issued alongside a bearer token remains valid.', '20160');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'defaultPageSize', 'Default Page Size', 'Number of records returned per page when no pageSize is supplied.', '50');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'maxPageSize', 'Maximum Page Size', 'Upper limit for the pageSize parameter.', '500');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'corsOrigins', 'CORS Allowed Origins', 'Comma separated list of allowed origins, or * for any. Leave blank to disable CORS headers.', '');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'logRequests', 'Log Requests', 'Write every API request to the request log.', 'Y');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'logRetentionDays', 'Log Retention (days)', 'Request log entries older than this are pruned automatically.', '30');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'enforceRolePermissions', 'Enforce Role Permissions', 'In addition to scopes, check the linked user role against the Gibbon action that owns each resource.', 'Y');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'allowWrites', 'Allow Write Operations', 'Global switch for POST, PATCH and DELETE. Set to N for a strictly read-only API.', 'Y');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'exposeModules', 'Expose Community Modules', 'Serve endpoints for installed community modules (Free Learning, Help Desk, Trip Planner and so on).', 'Y');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'exposeGenerated', 'Expose Generated Schema Endpoints', 'Serve the auto-generated endpoints covering every remaining database table. Set to N to publish only the curated endpoints.', 'Y');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'bulkMaxItems', 'Bulk Request Limit', 'Maximum number of records accepted in one /bulk request.', '200');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'webhooksEnabled', 'Enable Webhooks', 'Send signed change events to subscribed URLs whenever a record is created, updated or deleted through the API.', 'N');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'webhookTimeout', 'Webhook Timeout (seconds)', 'How long to wait for a webhook receiver before recording the delivery as failed.', '5');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'filesEnabled', 'Enable File Transfer', 'Allow files to be downloaded and uploaded through /v2/files. Set to N to block every file route.', 'N');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'fileMaxSizeMB', 'Maximum Upload Size (MB)', 'Largest single file accepted by an API upload.', '20');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'fileExtensions', 'Allowed File Extensions', 'Comma separated list of extensions an API upload may use. Anything else is rejected.', 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,rtf,odt,ods,jpg,jpeg,png,gif,webp,heic,svg,zip,mp3,mp4,m4a,wav');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'loginMaxAttempts', 'Sign-In Attempt Limit', 'Failed credential attempts allowed from one address before it is locked out. 0 disables the lockout.', '10');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'loginLockoutMinutes', 'Sign-In Lockout Window (minutes)', 'How far back failed credential attempts are counted, and how long a locked-out address waits.', '15');";
$tawasulSetting[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('Rest API', 'tokenRateLimit', 'Token Request Limit (per minute)', 'Requests per minute allowed for a token issued by username and password. 0 disables the limit.', '240');";

// ACTIONS
$actionRows[] = [
    'name' => 'API Dashboard',
    'precedence' => '0',
    'category' => 'API',
    'description' => 'Live view of school structure, students, staff and API sync status.',
    'URLList' => 'dashboard.php',
    'entryURL' => 'dashboard.php',
    'entrySidebar' => 'Y',
    'menuShow' => 'Y',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'N',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N',
];

$actionRows[] = [
    'name' => 'Manage API Keys',
    'precedence' => '0',
    'category' => 'API',
    'description' => 'Create, edit, revoke and inspect REST API keys.',
    'URLList' => 'keys_manage.php, keys_manage_add.php, keys_manage_addProcess.php, keys_manage_edit.php, keys_manage_editProcess.php, keys_manage_delete.php, keys_manage_deleteProcess.php',
    'entryURL' => 'keys_manage.php',
    'entrySidebar' => 'Y',
    'menuShow' => 'Y',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'N',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N',
];

$actionRows[] = [
    'name' => 'View Request Log',
    'precedence' => '0',
    'category' => 'API',
    'description' => 'Browse the REST API request log.',
    'URLList' => 'logs_view.php',
    'entryURL' => 'logs_view.php',
    'entrySidebar' => 'Y',
    'menuShow' => 'Y',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'N',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N',
];

$actionRows[] = [
    'name' => 'API Documentation',
    'precedence' => '0',
    'category' => 'API',
    'description' => 'Browse every endpoint, scope and field the API exposes.',
    'URLList' => 'docs.php',
    'entryURL' => 'docs.php',
    'entrySidebar' => 'Y',
    'menuShow' => 'Y',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'Y',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N',
];

$actionRows[] = [
    'name' => 'Manage Settings',
    'precedence' => '0',
    'category' => 'Admin',
    'description' => 'Configure REST API behaviour.',
    'URLList' => 'settings_manage.php, settings_manageProcess.php',
    'entryURL' => 'settings_manage.php',
    'entrySidebar' => 'Y',
    'menuShow' => 'Y',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'N',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N',
];

$actionRows[] = [
    'name' => 'Manage Webhooks',
    'precedence' => '0',
    'category' => 'API',
    'description' => 'Subscribe other systems to signed change events from the API.',
    'URLList' => 'webhooks_manage.php, webhooks_manage_add.php, webhooks_manage_addProcess.php, webhooks_manage_edit.php, webhooks_manage_editProcess.php, webhooks_manage_delete.php, webhooks_manage_deleteProcess.php',
    'entryURL' => 'webhooks_manage.php',
    'entrySidebar' => 'Y',
    'menuShow' => 'Y',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'N',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N',
];
