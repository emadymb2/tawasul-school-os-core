<?php
/*
TawasulMessenger (chat half) — versioned schema migrations.

These were written when chat was its own TawasulChat module; they travelled here
with it. The table DDL in schema.php and the table names it creates have both
changed to the tawasulMessengerChat* prefix, but the statements below are
earlier migrations already applied to a live database and are deliberately left
as they were written.

Each entry is [version, ";end"-delimited SQL], applied in order by
System Admin > Manage Modules > Update.

The table DDL is not repeated here. It lives in schema.php, which manifest.php
also reads, so a fresh install and an upgraded install cannot end up with
different tables. This migration exists for the case where they are missing: a
module restored onto a database that already carried its module row, or an
install where a manifest statement failed and left the module inactive. Every
statement below is safe to replay.
*/

$sql = [];
$count = 0;

// Small helper so the literal values below stay readable. Doubling the quote is
// MySQL's escape, which is enough for the fixed strings written here.
$quote = function ($value) {
    return "'".str_replace("'", "''", $value)."'";
};

$sql[$count][0] = '1.0.00';
$statements = [];

foreach (require __DIR__.'/schema.php' as $createTable) {
    $statements[] = rtrim(trim($createTable), ';');
}

// Settings are inserted only when absent. The scope is the module name, which is
// also what the uninstaller deletes by, so replaying this after an install does
// not overwrite values an administrator has already edited.
$chatSettings = [
    ['pollTimeoutSeconds', 'Long Poll Timeout', 'Seconds a delivery poll is held open before returning, when nothing arrives. Capped by the PHP max_execution_time.', '25'],
    ['presenceTimeoutSeconds', 'Presence Timeout', 'Seconds after which a user with no ping is shown as offline.', '60'],
    ['attachmentMaxSizeMB', 'Maximum Attachment Size', 'Largest file or voice note a person can send, in megabytes.', '25'],
    ['voiceNotesEnabled', 'Voice Notes Enabled', 'Allow recording and sending voice notes. When off the record button is hidden.', 'Y'],
    ['typingIndicatorEnabled', 'Typing Indicator Enabled', 'Show others that you are typing, and show when they are.', 'Y'],
    ['readReceiptsEnabled', 'Read Receipts Enabled', 'Send the second tick when a message has been read. Turning this off is closer to older messaging systems.', 'Y'],
    ['editingEnabled', 'Editing Enabled', 'Allow a sent message to be edited, which marks it as edited.', 'Y'],
    ['messageRetentionDays', 'Message Retention', 'Days a message is kept before it is purged by housekeeping. Set to 0 to keep messages forever.', '0'],
    ['maxGroupSize', 'Maximum Group Size', 'Largest number of people in a single group chat.', '100'],
    ['unreadBadgeEnabled', 'Unread Badge', 'Show the total unread message count in the navigation.', 'Y'],
];

foreach ($chatSettings as [$settingName, $settingDisplay, $settingDescription, $settingValue]) {
    $statements[] = "INSERT INTO `tawasulSetting` (`scope`,`name`,`nameDisplay`,`description`,`value`)
        SELECT ".$quote('TawasulChat').", ".$quote($settingName).", ".$quote($settingDisplay).", ".$quote($settingDescription).", ".$quote($settingValue)."\n        FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM `tawasulSetting` WHERE scope = ".$quote('TawasulChat')." AND name = ".$quote($settingName).")";
}

// The action rows are repeated here for the same reason. Without them a restored
// module would install and then deny everyone access, because isActionAccessible
// matches on the action row rather than on the files in the module folder.
// Fields per row, in the order the INSERT below expects:
//   name, precedence, category, description, URLList, entryURL, entrySidebar,
//   menuShow, then defaultPermission for Admin/Teacher/Student/Parent/Support,
//   then categoryPermission for Staff/Student/Parent/Other.
$chatActions = [
    ['Chat', 0, 'Chat', 'Send and receive messages in one-to-one and group chats.', 'chat.php,chat_ajax.php', 'chat.php',
        'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y'],
    ['Start New Chat', 1, 'Chat', 'Start a new one-to-one or group conversation.', 'chat_new.php,chat_newProcess.php', 'chat_new.php',
        'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y'],
    ['Manage Chat Group', 2, 'Chat', 'Add or remove group members, change the group name and leave a group.', 'chat_group_manage.php,chat_group_manageProcess.php', 'chat_group_manage.php',
        'N', 'N', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N'],
    ['View Starred Messages', 3, 'Chat', 'See the messages you have starred across every conversation.', 'chat_starred.php', 'chat_starred.php',
        'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y'],
    ['Chat Settings', 4, 'Settings', 'Configure attachment limits, retention, read receipts and typing indicators.', 'chat_settings.php,chat_settingsProcess.php', 'chat_settings.php',
        'Y', 'Y', 'Y', 'N', 'N', 'N', 'Y', 'N', 'N', 'N', 'N'],
];

foreach ($chatActions as [$actionName, $precedence, $category, $description, $urlList, $entryUrl, $entrySidebar, $menuShow, $admin, $teacher, $student, $parent, $support, $catStaff, $catStudent, $catParent, $catOther]) {
    $statements[] = "INSERT INTO `tawasulAction` (`tawasulModuleID`,`name`,`precedence`,`category`,`description`,`helpURL`,`URLList`,`entryURL`,`entrySidebar`,`menuShow`,`defaultPermissionAdmin`,`defaultPermissionTeacher`,`defaultPermissionStudent`,`defaultPermissionParent`,`defaultPermissionSupport`,`categoryPermissionStaff`,`categoryPermissionStudent`,`categoryPermissionParent`,`categoryPermissionOther`)
        SELECT m.tawasulModuleID, ".$quote($actionName).", ".$precedence.", ".$quote($category).", ".$quote($description).", '', ".$quote($urlList).", ".$quote($entryUrl).", ".$quote($entrySidebar).", ".$quote($menuShow).", ".$quote($admin).", ".$quote($teacher).", ".$quote($student).", ".$quote($parent).", ".$quote($support).", ".$quote($catStaff).", ".$quote($catStudent).", ".$quote($catParent).", ".$quote($catOther)."\n        FROM `tawasulModule` m
        WHERE m.name = ".$quote('TawasulChat')."\n          AND NOT EXISTS (SELECT 1 FROM `tawasulAction` a WHERE a.tawasulModuleID = m.tawasulModuleID AND a.name = ".$quote($actionName).")";
}

// Every role that can see the chat should reach the module, but the per-capability
// actions stay with administrators. Granted by role category so an installation
// with custom staff roles is covered too.
foreach ([
    ['Chat', 'Staff'], ['Chat', 'Student'], ['Chat', 'Parent'], ['Chat', 'Other'],
    ['Start New Chat', 'Staff'], ['Start New Chat', 'Student'], ['Start New Chat', 'Parent'], ['Start New Chat', 'Other'],
    ['View Starred Messages', 'Staff'], ['View Starred Messages', 'Student'], ['View Starred Messages', 'Parent'], ['View Starred Messages', 'Other'],
] as [$actionName, $roleCategory]) {
    $statements[] = "INSERT INTO `tawasulPermission` (`tawasulRoleID`,`tawasulActionID`)
        SELECT r.tawasulRoleID, a.tawasulActionID
        FROM `tawasulRole` r
        JOIN `tawasulAction` a ON (a.name = ".$quote($actionName).")
        JOIN `tawasulModule` m ON (m.tawasulModuleID = a.tawasulModuleID)
        WHERE m.name = ".$quote('TawasulChat')." AND r.category = ".$quote($roleCategory)."
          AND NOT EXISTS (SELECT 1 FROM `tawasulPermission` p WHERE p.tawasulActionID = a.tawasulActionID AND p.tawasulRoleID = r.tawasulRoleID)";
}

$sql[$count][1] = implode(';end', $statements);
$count++;

// 1.1.00 adds the four WhatsApp features that were missing: the message-info
// screen, pinned messages, server-side drafts and a real forward picker. Two of
// them need storage, so this migration is the two new tables rather than a
// change to any existing one: an upgrade should not rewrite a table that is
// already holding a school's chat history.
$sql[$count][0] = '1.1.00';
$statements = [];

foreach (require __DIR__.'/schema.php' as $createTable) {
    if (preg_match('/CREATE TABLE IF NOT EXISTS `tawasulMessengerChat(Pin|Draft)`/i', $createTable)) {
        $statements[] = rtrim(trim($createTable), ';');
    }
}

$sql[$count][1] = implode(';end', $statements);
$count++;

// 1.1.01 backs "delete for me" with per-person storage.
//
// "Delete for me" used to remove the message row outright, which deleted it for
// everybody in the conversation — so declining the "delete for everyone" prompt
// destroyed the message for the other people. The correct storage is one row
// per person per message meaning "do not show me this", which is a new table
// and no change to any existing one.
$sql[$count][0] = '1.1.01';
$statements = [];

foreach (require __DIR__.'/schema.php' as $createTable) {
    if (preg_match('/CREATE TABLE IF NOT EXISTS `tawasulMessengerChatMessageHidden`/i', $createTable)) {
        $statements[] = rtrim(trim($createTable), ';');
    }
}

$sql[$count][1] = implode(';end', $statements);
$count++;
