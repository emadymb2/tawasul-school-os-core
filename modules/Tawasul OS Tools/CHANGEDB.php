<?php
/*
Database changes per version; each entry is a list of SQL statements separated by ";end".
Statements are written so they are safe to run against an install that never had
the earlier (un-rebranded) schema, as well as one that does.
*/
$sql = [];
$count = 0;

$sql[$count][0] = '1.0.00';
$sql[$count][1] = '';

++$count;
$sql[$count][0] = '1.1.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `tawasulAnnouncement` (`tawasulAnnouncementID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT, `titleAr` VARCHAR(255) NOT NULL, `bodyAr` TEXT NOT NULL, `titleEn` VARCHAR(255) NOT NULL, `bodyEn` TEXT NOT NULL, `tawasulPersonIDCreator` INT(10) UNSIGNED ZEROFILL NOT NULL, `timestampCreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`tawasulAnnouncementID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end
CREATE TABLE IF NOT EXISTS `tawasulAnnouncementRecipient` (`tawasulAnnouncementRecipientID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT, `tawasulAnnouncementID` INT(10) UNSIGNED ZEROFILL NOT NULL, `tawasulPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL, `timestampRead` DATETIME NULL DEFAULT NULL, PRIMARY KEY (`tawasulAnnouncementRecipientID`), UNIQUE KEY `announcementPerson` (`tawasulAnnouncementID`, `tawasulPersonID`), KEY `person` (`tawasulPersonID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end
INSERT INTO tawasulAction (tawasulModuleID, name, precedence, category, description, URLList, entryURL, entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther) VALUES ((SELECT tawasulModuleID FROM tawasulModule WHERE name='Tawasul OS Tools'), 'Announcements Inbox', 0, 'Communication', 'Read announcements sent to you, with read status.', 'inbox.php,inbox_view.php', 'inbox.php', 'Y', 'Y', 'Y', 'Y', 'N', 'N', 'Y', 'Y', 'N', 'N', 'N');end
INSERT INTO tawasulAction (tawasulModuleID, name, precedence, category, description, URLList, entryURL, entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther) VALUES ((SELECT tawasulModuleID FROM tawasulModule WHERE name='Tawasul OS Tools'), 'Sent Announcements', 0, 'Communication', 'See who has read each announcement.', 'announcements_sent.php', 'announcements_sent.php', 'Y', 'Y', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
UPDATE tawasulAction SET URLList='announcements.php,announcementsSendProcess.php' WHERE name='Announcement Writer' AND tawasulModuleID=(SELECT tawasulModuleID FROM tawasulModule WHERE name='Tawasul OS Tools');end
INSERT INTO tawasulPermission (tawasulRoleID, tawasulActionID) SELECT '001', tawasulActionID FROM tawasulAction WHERE name IN ('Announcements Inbox','Sent Announcements') AND tawasulModuleID=(SELECT tawasulModuleID FROM tawasulModule WHERE name='Tawasul OS Tools');end
INSERT INTO tawasulPermission (tawasulRoleID, tawasulActionID) SELECT tawasulRoleID, (SELECT tawasulActionID FROM tawasulAction WHERE name='Announcements Inbox' AND tawasulModuleID=(SELECT tawasulModuleID FROM tawasulModule WHERE name='Tawasul OS Tools')) FROM tawasulRole WHERE category='Staff' AND tawasulRoleID<>'001';end
";

++$count;
// v1.2.00 — rebrand the module onto the platform's own table, column, namespace
// and setting names. Installs that were created straight from the corrected
// manifest.php already have the new names, so each step is guarded.
$sql[$count][0] = '1.2.00';
$sql[$count][1] = "
RENAME TABLE `tosAnnouncement` TO `tawasulAnnouncement`;end
RENAME TABLE `tosAnnouncementRecipient` TO `tawasulAnnouncementRecipient`;end
ALTER TABLE `tawasulAnnouncement` CHANGE `tawasulAnnouncementID` `tawasulAnnouncementID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT;end
ALTER TABLE `tawasulAnnouncement` CHANGE `gibbonPersonIDCreator` `tawasulPersonIDCreator` INT(10) UNSIGNED ZEROFILL NOT NULL;end
ALTER TABLE `tawasulAnnouncementRecipient` CHANGE `tawasulAnnouncementRecipientID` `tawasulAnnouncementRecipientID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT;end
ALTER TABLE `tawasulAnnouncementRecipient` CHANGE `tawasulAnnouncementID` `tawasulAnnouncementID` INT(10) UNSIGNED ZEROFILL NOT NULL;end
ALTER TABLE `tawasulAnnouncementRecipient` CHANGE `gibbonPersonID` `tawasulPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL;end
ALTER TABLE `tawasulAnnouncementRecipient` DROP INDEX `person`;end
ALTER TABLE `tawasulAnnouncementRecipient` DROP INDEX `announcementPerson`;end
ALTER TABLE `tawasulAnnouncementRecipient` ADD UNIQUE KEY `announcementPerson` (`tawasulAnnouncementID`, `tawasulPersonID`);end
ALTER TABLE `tawasulAnnouncementRecipient` ADD KEY `person` (`tawasulPersonID`);end
INSERT INTO tawasulSetting (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Tawasul OS Tools', 'aiServiceURL', 'Announcement Service URL', 'Address of the Tawasul OS drafting service.', 'https://project--55c16ee0-218e-4db6-ae36-b1bdc4fa57b6.lovable.app/api/public/announcement-draft');end
INSERT INTO tawasulSetting (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Tawasul OS Tools', 'aiServiceToken', 'Announcement Service Token', 'Shared access token for the drafting service.', '');end
INSERT INTO tawasulSetting (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Tawasul OS Tools', 'brandUseCustom', 'Use Custom Colours', 'Apply the custom login and portal colours below.', 'N');end
INSERT INTO tawasulSetting (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Tawasul OS Tools', 'brandPrimary', 'Main Colour', 'Sidebar, login background and headings.', '#1f4a33');end
INSERT INTO tawasulSetting (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Tawasul OS Tools', 'brandAccent', 'Accent Colour', 'Buttons and highlights.', '#e8664f');end
INSERT INTO tawasulSetting (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Tawasul OS Tools', 'brandHighlight', 'Highlight Colour', 'Focus rings, badges and timetable periods.', '#e9b949');end
";
