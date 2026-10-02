<?php
/*
Tawasul OS Branches — multi-branch (multi-campus) support.
Licensed under the GNU General Public License v3 or later.

A "branch" is a campus or site within one school group. The module owns the
branch register and tags the records that the rest of the platform groups by
campus, so the existing modules can be made branch-aware without rewriting
their schemas.

Note for maintainers: MySQL 8 has no `ADD COLUMN IF NOT EXISTS`, so the
ALTERs below are deliberately plain. They run exactly once, at install time.
Installs upgrading from an older build pick the same statements up through
CHANGEDB.php instead, which is gated on the module version.
*/

// Folder name must stay "Tawasul OS Branches" (the installer matches it to $name).
// tawasulModule.name is varchar(30); this is 19.
$name        = 'Tawasul OS Branches';
$description = 'Manage school branches (campuses) and assign staff, students, classes, departments and units to them.';
$entryURL    = 'branches.php';
$type        = 'Additional';
$category    = 'Other';
$version     = '1.0.00';
$author      = 'Tawasul OS';
$url         = 'https://tos.fiksutiliratkaisut.fi';

$moduleTables = [
    // The branch register itself.
    "CREATE TABLE IF NOT EXISTS `tawasulBranch` (
        `tawasulBranchID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
        `code` VARCHAR(20) NOT NULL,
        `nameAr` VARCHAR(150) NOT NULL,
        `nameEn` VARCHAR(150) NOT NULL,
        `addressAr` VARCHAR(255) NOT NULL DEFAULT '',
        `addressEn` VARCHAR(255) NOT NULL DEFAULT '',
        `phone` VARCHAR(40) NOT NULL DEFAULT '',
        `email` VARCHAR(150) NOT NULL DEFAULT '',
        `headPersonID` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL,
        `noteAr` TEXT NULL,
        `noteEn` TEXT NULL,
        `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
        `timestampCreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`tawasulBranchID`),
        UNIQUE KEY `branchCode` (`code`),
        KEY `branchActive` (`active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Branch tags. Every column is nullable so that a record created before
    // multi-branch was switched on — or by a module that does not know about
    // branches — never breaks a join or a NOT NULL constraint.
    "ALTER TABLE `tawasulStaff` ADD COLUMN `tawasulBranchID` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL AFTER `tawasulPersonID`;",
    "ALTER TABLE `tawasulStudentEnrolment` ADD COLUMN `tawasulBranchID` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL AFTER `tawasulPersonID`;",
    "ALTER TABLE `tawasulCourseClass` ADD COLUMN `tawasulBranchID` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL AFTER `tawasulCourseClassID`;",
    "ALTER TABLE `tawasulDepartment` ADD COLUMN `tawasulBranchID` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL AFTER `tawasulDepartmentID`;",
    "ALTER TABLE `tawasulUnit` ADD COLUMN `tawasulBranchID` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL AFTER `tawasulUnitID`;",

    // Supporting indexes: every branch-aware module filters on these columns and
    // they are also joined to tawasulBranch by id.
    "ALTER TABLE `tawasulStaff` ADD INDEX `branchStaff` (`tawasulBranchID`);",
    "ALTER TABLE `tawasulStudentEnrolment` ADD INDEX `branchEnrolment` (`tawasulBranchID`);",
    "ALTER TABLE `tawasulCourseClass` ADD INDEX `branchCourseClass` (`tawasulBranchID`);",
    "ALTER TABLE `tawasulDepartment` ADD INDEX `branchDepartment` (`tawasulBranchID`);",
    "ALTER TABLE `tawasulUnit` ADD INDEX `branchUnit` (`tawasulBranchID`);",
    "ALTER TABLE `tawasulBranch` ADD INDEX `branchHead` (`headPersonID`);",
];

$tawasulSetting = [
    // Master switch. While 'N', no other module filters by branch, so the
    // platform behaves exactly as it did before this module existed. This is
    // the rollback lever if branch filtering misbehaves on live data.
    "INSERT INTO `tawasulSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Tawasul OS Branches', 'branchFilterEnabled', 'Filter Modules By Branch', 'When Yes, modules such as Attendance, Timetable and Reports limit themselves to the branch you are viewing.', 'N');",

    // The branch a user lands on when they have not chosen one, and the one
    // used by users who cannot switch (students, parents).
    "INSERT INTO `tawasulSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Tawasul OS Branches', 'branchDefaultID', 'Default Branch', 'Branch used when a user has not selected one.', '0');",

    // 'Delegated staff': roles beyond Administrator that may create and edit
    // branches. Comma separated tawasulRoleID values. Administrators always
    // retain access regardless of what is listed here.
    "INSERT INTO `tawasulSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Tawasul OS Branches', 'branchManagerRoles', 'Branch Manager Roles', 'Roles, in addition to Administrator, that may manage branches. Leave empty for administrators only.', '');",
];

$actionRows = [
    [
        'name' => 'Branches', 'precedence' => '0', 'category' => 'School Admin',
        'description' => 'View the branch register and switch between branches.',
        'URLList' => 'branches.php,branch_activeProcess.php', 'entryURL' => 'branches.php',
        'entrySidebar' => 'Y', 'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'Y', 'defaultPermissionStudent' => 'Y', 'defaultPermissionParent' => 'Y', 'defaultPermissionSupport' => 'Y',
        'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N',
    ],
    [
        'name' => 'Add Branch', 'precedence' => '1', 'category' => 'School Admin',
        'description' => 'Create a branch or edit an existing one.',
        'URLList' => 'branch_manage.php,branch_manageProcess.php', 'entryURL' => 'branch_manage.php',
        'entrySidebar' => 'Y', 'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N',
        'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N',
    ],
    [
        'name' => 'Assign To Branch', 'precedence' => '2', 'category' => 'School Admin',
        'description' => 'Assign staff, students, classes, departments and units to branches.',
        'URLList' => 'assignments.php,assignmentsProcess.php', 'entryURL' => 'assignments.php',
        'entrySidebar' => 'Y', 'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N',
        'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N',
    ],
    [
        'name' => 'Branch Settings', 'precedence' => '3', 'category' => 'School Admin',
        'description' => 'Choose the default branch, delegated manager roles and whether modules filter by branch.',
        'URLList' => 'settings.php,settingsProcess.php', 'entryURL' => 'settings.php',
        'entrySidebar' => 'Y', 'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N',
        'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N',
    ],
];

/*
Install-time data migration. Runs after $moduleTables, so the branch table and
the tawasulBranchID columns already exist. Guarded on the branch table being
non-empty so a re-run cannot add a second default branch.
*/
$hooks = [
    // Seed one branch, named after the organisation, so that every pre-existing
    // record has a home instead of being orphaned on a NULL branch.
    "INSERT INTO `tawasulBranch` (`code`, `nameAr`, `nameEn`, `noteEn`)
     SELECT 'MAIN', `value`, `value`, 'Created automatically when multi-branch support was installed.'
     FROM `tawasulSetting`
     WHERE `scope`='System' AND `name`='organisationName'
     ORDER BY `tawasulSettingID` LIMIT 1;",

    // Adopt any branch created by hand before this hook could run.
    "INSERT INTO `tawasulBranch` (`code`, `nameAr`, `nameEn`)
     SELECT 'MAIN', 'الفرع الرئيسي', 'Main Branch'
     FROM DUAL
     WHERE NOT EXISTS (SELECT 1 FROM `tawasulBranch`);",

    // Backfill. Only rows still on NULL are touched, so this is safe to repeat.
    "UPDATE `tawasulStaff` SET `tawasulBranchID` = (SELECT MIN(`tawasulBranchID`) FROM `tawasulBranch`) WHERE `tawasulBranchID` IS NULL AND EXISTS (SELECT 1 FROM `tawasulBranch`);",
    "UPDATE `tawasulStudentEnrolment` SET `tawasulBranchID` = (SELECT MIN(`tawasulBranchID`) FROM `tawasulBranch`) WHERE `tawasulBranchID` IS NULL AND EXISTS (SELECT 1 FROM `tawasulBranch`);",
    "UPDATE `tawasulCourseClass` SET `tawasulBranchID` = (SELECT MIN(`tawasulBranchID`) FROM `tawasulBranch`) WHERE `tawasulBranchID` IS NULL AND EXISTS (SELECT 1 FROM `tawasulBranch`);",
    "UPDATE `tawasulDepartment` SET `tawasulBranchID` = (SELECT MIN(`tawasulBranchID`) FROM `tawasulBranch`) WHERE `tawasulBranchID` IS NULL AND EXISTS (SELECT 1 FROM `tawasulBranch`);",
    "UPDATE `tawasulUnit` SET `tawasulBranchID` = (SELECT MIN(`tawasulBranchID`) FROM `tawasulBranch`) WHERE `tawasulBranchID` IS NULL AND EXISTS (SELECT 1 FROM `tawasulBranch`);",

    // Point the default-branch setting at the branch that was just created.
    "UPDATE `tawasulSetting` SET `value` = (SELECT MIN(`tawasulBranchID`) FROM `tawasulBranch`)
     WHERE `scope`='Tawasul OS Branches' AND `name`='branchDefaultID';",
];