<?php
/*
Database changes per version; each entry is a list of SQL statements separated
by ";end".

Fresh installs never read this file: modules/TawasulSystemAdmin/
module_manage_installProcess.php runs manifest.php's $moduleTables instead.
These entries exist for installs that pick the module up from a build where the
tables already existed.

MySQL 8 has no `ADD COLUMN IF NOT EXISTS`, so these are plain DDL and rely on
the version gate in module_manage_updateProcess.php (runs once, for versions
strictly greater than the installed one).
*/
$sql = [];
$count = 0;

$sql[$count][0] = '1.0.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `tawasulBranch` (`tawasulBranchID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT, `code` VARCHAR(20) NOT NULL, `nameAr` VARCHAR(150) NOT NULL, `nameEn` VARCHAR(150) NOT NULL, `addressAr` VARCHAR(255) NOT NULL DEFAULT '', `addressEn` VARCHAR(255) NOT NULL DEFAULT '', `phone` VARCHAR(40) NOT NULL DEFAULT '', `email` VARCHAR(150) NOT NULL DEFAULT '', `headPersonID` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL, `noteAr` TEXT NULL, `noteEn` TEXT NULL, `active` ENUM('Y','N') NOT NULL DEFAULT 'Y', `timestampCreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`tawasulBranchID`), UNIQUE KEY `branchCode` (`code`), KEY `branchActive` (`active`), KEY `branchHead` (`headPersonID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end
ALTER TABLE `tawasulStaff` ADD COLUMN `tawasulBranchID` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL AFTER `tawasulPersonID`;end
ALTER TABLE `tawasulStudentEnrolment` ADD COLUMN `tawasulBranchID` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL AFTER `tawasulPersonID`;end
ALTER TABLE `tawasulCourseClass` ADD COLUMN `tawasulBranchID` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL AFTER `tawasulCourseClassID`;end
ALTER TABLE `tawasulDepartment` ADD COLUMN `tawasulBranchID` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL AFTER `tawasulDepartmentID`;end
ALTER TABLE `tawasulUnit` ADD COLUMN `tawasulBranchID` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL AFTER `tawasulUnitID`;end
ALTER TABLE `tawasulStaff` ADD INDEX `branchStaff` (`tawasulBranchID`);end
ALTER TABLE `tawasulStudentEnrolment` ADD INDEX `branchEnrolment` (`tawasulBranchID`);end
ALTER TABLE `tawasulCourseClass` ADD INDEX `branchCourseClass` (`tawasulBranchID`);end
ALTER TABLE `tawasulDepartment` ADD INDEX `branchDepartment` (`tawasulBranchID`);end
ALTER TABLE `tawasulUnit` ADD INDEX `branchUnit` (`tawasulBranchID`);end
";

++$count;