<?php
/*
Tawasul OS Tools — companion module for the Tawasul OS Theme.
Licensed under the GNU General Public License v3 or later.
*/

// Folder name must stay "Tawasul OS Tools" (the installer matches it to $name).
$name        = 'Tawasul OS Tools';
$description = 'AI bilingual staff announcements and login page branding with live preview for the Tawasul OS Theme.';
$entryURL    = 'announcements.php';
$type        = 'Additional';
$category    = 'Other';
$version     = '1.2.00';
$author      = 'Tawasul OS';
$url         = 'https://tos.fiksutiliratkaisut.fi';

$moduleTables = [
    "CREATE TABLE IF NOT EXISTS `tawasulAnnouncement` (
        `tawasulAnnouncementID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
        `titleAr` VARCHAR(255) NOT NULL, `bodyAr` TEXT NOT NULL,
        `titleEn` VARCHAR(255) NOT NULL, `bodyEn` TEXT NOT NULL,
        `tawasulPersonIDCreator` INT(10) UNSIGNED ZEROFILL NOT NULL,
        `timestampCreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`tawasulAnnouncementID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS `tawasulAnnouncementRecipient` (
        `tawasulAnnouncementRecipientID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
        `tawasulAnnouncementID` INT(10) UNSIGNED ZEROFILL NOT NULL,
        `tawasulPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
        `timestampRead` DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`tawasulAnnouncementRecipientID`),
        UNIQUE KEY `announcementPerson` (`tawasulAnnouncementID`, `tawasulPersonID`),
        KEY `person` (`tawasulPersonID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$tawasulSetting = [
    "INSERT INTO `tawasulSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Tawasul OS Tools', 'aiServiceURL', 'Announcement Service URL', 'Address of the Tawasul OS drafting service.', 'https://project--55c16ee0-218e-4db6-ae36-b1bdc4fa57b6.lovable.app/api/public/announcement-draft');",
    "INSERT INTO `tawasulSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Tawasul OS Tools', 'aiServiceToken', 'Announcement Service Token', 'Shared access token for the drafting service.', '');",
    "INSERT INTO `tawasulSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Tawasul OS Tools', 'brandUseCustom', 'Use Custom Colours', 'Apply the custom login and portal colours below.', 'N');",
    "INSERT INTO `tawasulSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Tawasul OS Tools', 'brandPrimary', 'Main Colour', 'Sidebar, login background and headings.', '#1f4a33');",
    "INSERT INTO `tawasulSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Tawasul OS Tools', 'brandAccent', 'Accent Colour', 'Buttons and highlights.', '#e8664f');",
    "INSERT INTO `tawasulSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Tawasul OS Tools', 'brandHighlight', 'Highlight Colour', 'Focus rings, badges and timetable periods.', '#e9b949');",
];

$actionRows = [
    [
        'name' => 'Announcement Writer', 'precedence' => '0', 'category' => 'Communication',
        'description' => 'Draft polished Arabic and English staff announcements with AI.',
        'URLList' => 'announcements.php,announcementsSendProcess.php', 'entryURL' => 'announcements.php',
        'entrySidebar' => 'Y', 'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N',
        'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N',
        'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N',
    ],
    [
        'name' => 'Login Branding', 'precedence' => '0', 'category' => 'Settings',
        'description' => 'Customise the login page school name, logo and accent colours with a live preview.',
        'URLList' => 'branding.php', 'entryURL' => 'branding.php',
        'entrySidebar' => 'Y', 'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N',
        'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N',
        'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N',
    ],
    [
        'name' => 'Announcement Service Settings', 'precedence' => '0', 'category' => 'Settings',
        'description' => 'Connect the module to the Tawasul OS drafting service.',
        'URLList' => 'settings.php', 'entryURL' => 'settings.php',
        'entrySidebar' => 'Y', 'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N',
        'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N',
        'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N',
    ],
    [
        'name' => 'Announcements Inbox', 'precedence' => '0', 'category' => 'Communication',
        'description' => 'Read announcements sent to you, with read status.',
        'URLList' => 'inbox.php,inbox_view.php', 'entryURL' => 'inbox.php',
        'entrySidebar' => 'Y', 'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'Y', 'defaultPermissionStudent' => 'N',
        'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'Y',
        'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N',
    ],
    [
        'name' => 'Sent Announcements', 'precedence' => '0', 'category' => 'Communication',
        'description' => 'See who has read each announcement.',
        'URLList' => 'announcements_sent.php', 'entryURL' => 'announcements_sent.php',
        'entrySidebar' => 'Y', 'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N',
        'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N',
        'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N',
    ],
];

$hooks = [];
