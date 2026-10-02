<?php
/*
 * TawasulMessenger — TawasulOS module
 *
 * Licensed under the GNU General Public License v3 or later.
 *
 * This module carries two related things:
 *
 *   * one-way messages: bulk mail to staff, families and course groups, mailing
 *     lists and canned responses (the messenger_*.php pages that predate this
 *     module's chat half);
 *   * live chat: one-to-one and group conversations with real-time delivery,
 *     media, voice notes, replies, reactions, read receipts, pinned messages,
 *     drafts, presence and disappearing messages (messenger_chat*.php and
 *     src/Chat/).
 *
 * The chat half was its own TawasulChat module. It was merged in here so the
 * school has one messaging module in the menu rather than two, and so the chat
 * tables live under the tawasulMessengerChat* prefix alongside the messenger
 * tables they sit next to.
 *
 * Two decisions from the chat module are worth restating, because they still
 * shape the code:
 *
 *   * Messages are stored and delivered as readable text. This is deliberate and
 *     consistent with every other module here. It is what makes school records
 *     searchable and auditable. Encryption in transit is enforced at the TLS
 *     layer instead.
 *
 *   * Chat delivery is a long poll. The platform has no persistent connection
 *     layer, so chat holds a request open until something changes. The transport
 *     sits behind an interface (src/Chat/Support/Transport.php) so a WebSocket
 *     relay can replace it later without touching message logic.
 */

$name = 'TawasulMessenger';
$description = 'Unified messaging: bulk messages, mailing lists and canned responses, plus live one-to-one and group chat with real-time delivery, media, voice notes, replies, reactions, read receipts, pinned messages, drafts and presence.';
$entryURL = 'messenger_chat.php';
$type = 'Core';
$category = 'Academic';
$version = '32.0.00';
$author = 'TawasulOS';
$url = 'https://tos.fiksutiliratkaisut.fi';

// Module permissions. Chat is open to every role; the bulk-message half is not,
// and the per-action defaults below are what actually decide. See the note on
// $actionRows.
$defaultPermissionAdmin = 'Y';
$defaultPermissionTeacher = 'Y';
$defaultPermissionStudent = 'Y';
$defaultPermissionParent = 'Y';
$defaultPermissionSupport = 'Y';

// The chat half's tables, now prefixed tawasulMessengerChat*. Kept in schema.php
// so a fresh install creates them from one place, exactly as when this was
// still TawasulChat.
$moduleTables = require __DIR__.'/schema.php';

// Chat's settings, re-scoped from 'TawasulChat' to 'TawasulMessenger'. The rows
// already in the database were renamed to match, so both a fresh install and an
// upgraded one read the same scope.
$tawasulSetting = [
    "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulMessenger', 'pollTimeoutSeconds', 'Long Poll Timeout', 'Seconds a delivery poll is held open before returning, when nothing arrives. Capped by the PHP max_execution_time.', '25');",
    "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulMessenger', 'presenceTimeoutSeconds', 'Presence Timeout', 'Seconds after which a user with no ping is shown as offline.', '60');",
    "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulMessenger', 'attachmentMaxSizeMB', 'Maximum Attachment Size', 'Largest file or voice note a person can send, in megabytes.', '25');",
    "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulMessenger', 'voiceNotesEnabled', 'Voice Notes Enabled', 'Allow recording and sending voice notes. When off the record button is hidden.', 'Y');",
    "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulMessenger', 'typingIndicatorEnabled', 'Typing Indicator Enabled', 'Show others that you are typing, and show when they are.', 'Y');",
    "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulMessenger', 'readReceiptsEnabled', 'Read Receipts Enabled', 'Send the second tick when a message has been read. Turning this off is closer to older messaging systems.', 'Y');",
    "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulMessenger', 'editingEnabled', 'Editing Enabled', 'Allow a sent message to be edited, which marks it as edited.', 'Y');",
    "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulMessenger', 'messageRetentionDays', 'Message Retention', 'Days a message is kept before it is purged by housekeeping. Set to 0 to keep messages forever.', '0');",
    "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulMessenger', 'maxGroupSize', 'Maximum Group Size', 'Largest number of people in a single group chat.', '100');",
    "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`) VALUES ('TawasulMessenger', 'unreadBadgeEnabled', 'Unread Badge', 'Show the total unread message count in the navigation.', 'Y');",
];

/*
 * The chat actions, carried over from TawasulChat's manifest with their URLs
 * rewritten to messenger_chat*.php. Action names are unchanged on purpose: the
 * live permission rows and the isActionAccessible(..., 'Manage Chat Group')
 * call in chatFunctions.php both key off them, so renaming would silently drop
 * every existing grant.
 *
 * Keys are explicit because the installer reads each field by name; a positional
 * array here installs actions with a NULL name.
 */
$actionRows = [
    [
        'name' => 'Chat',
        'precedence' => 0,
        'category' => 'Chat',
        'description' => 'Send and receive messages in one-to-one and group chats.',
        'URLList' => 'messenger_chat.php,messenger_chat_ajax.php',
        'entryURL' => 'messenger_chat.php',
        'entrySidebar' => 'Y',
        'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y',
        'defaultPermissionTeacher' => 'Y',
        'defaultPermissionStudent' => 'Y',
        'defaultPermissionParent' => 'Y',
        'defaultPermissionSupport' => 'Y',
        'categoryPermissionStaff' => 'Y',
        'categoryPermissionStudent' => 'Y',
        'categoryPermissionParent' => 'Y',
        'categoryPermissionOther' => 'Y',
    ],
    [
        'name' => 'Start New Chat',
        'precedence' => 1,
        'category' => 'Chat',
        'description' => 'Start a new one-to-one or group conversation.',
        'URLList' => 'messenger_chat_new.php,messenger_chat_newProcess.php',
        'entryURL' => 'messenger_chat_new.php',
        'entrySidebar' => 'Y',
        'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y',
        'defaultPermissionTeacher' => 'Y',
        'defaultPermissionStudent' => 'Y',
        'defaultPermissionParent' => 'Y',
        'defaultPermissionSupport' => 'Y',
        'categoryPermissionStaff' => 'Y',
        'categoryPermissionStudent' => 'Y',
        'categoryPermissionParent' => 'Y',
        'categoryPermissionOther' => 'Y',
    ],
    [
        'name' => 'Manage Chat Group',
        'precedence' => 2,
        'category' => 'Chat',
        'description' => 'Add or remove group members, change the group name and leave a group.',
        'URLList' => 'messenger_chat_group_manage.php,messenger_chat_group_manageProcess.php',
        'entryURL' => 'messenger_chat_group_manage.php',
        'entrySidebar' => 'N',
        'menuShow' => 'N',
        'defaultPermissionAdmin' => 'Y',
        'defaultPermissionTeacher' => 'Y',
        'defaultPermissionStudent' => 'Y',
        'defaultPermissionParent' => 'Y',
        'defaultPermissionSupport' => 'Y',
        'categoryPermissionStaff' => 'Y',
        'categoryPermissionStudent' => 'Y',
        'categoryPermissionParent' => 'Y',
        'categoryPermissionOther' => 'Y',
    ],
    [
        'name' => 'View Starred Messages',
        'precedence' => 3,
        'category' => 'Chat',
        'description' => 'See the messages you have starred across every conversation.',
        'URLList' => 'messenger_chat_starred.php',
        'entryURL' => 'messenger_chat_starred.php',
        'entrySidebar' => 'Y',
        'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y',
        'defaultPermissionTeacher' => 'Y',
        'defaultPermissionStudent' => 'Y',
        'defaultPermissionParent' => 'Y',
        'defaultPermissionSupport' => 'Y',
        'categoryPermissionStaff' => 'Y',
        'categoryPermissionStudent' => 'Y',
        'categoryPermissionParent' => 'Y',
        'categoryPermissionOther' => 'Y',
    ],
    [
        'name' => 'Chat Settings',
        'precedence' => 4,
        'category' => 'Settings',
        'description' => 'Configure attachment limits, retention, read receipts and typing indicators.',
        'URLList' => 'messenger_chat_settings.php,messenger_chat_settingsProcess.php',
        'entryURL' => 'messenger_chat_settings.php',
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
    ],
];

$hooks = [];
