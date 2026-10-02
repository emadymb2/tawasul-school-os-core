<?php
/**
 * The TawasulChat schema, in one place.
 *
 * manifest.php and CHANGEDB.php both read this file rather than each carrying
 * its own copy of the DDL, so a fresh install and an upgraded install cannot
 * drift apart. Every statement is written to be safe to replay: the installer
 * may run them against a database where a previous install already created
 * some of these tables.
 */

return [

// A chat is either between two people or a named group. lastMessageID and
// lastMessagePreview are deliberately denormalised onto the chat: the chat list
// is the first thing every open client renders, and recomputing it with a
// subquery per row is the difference between one index scan and a correlated
// query per conversation.
<<<'SQL'
CREATE TABLE IF NOT EXISTS `tawasulMessengerChat` (
    `tawasulChatID` int(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `type` enum('individual','group') NOT NULL DEFAULT 'individual',
    `name` varchar(100) NOT NULL DEFAULT '',
    `description` varchar(255) NOT NULL DEFAULT '',
    `iconPath` varchar(255) NULL DEFAULT NULL,
    `createdBy` int(10) UNSIGNED ZEROFILL NOT NULL,
    `disappearingMinutes` int NOT NULL DEFAULT 0,
    `lastMessageID` int(12) UNSIGNED ZEROFILL NULL DEFAULT NULL,
    `lastMessagePreview` varchar(255) NOT NULL DEFAULT '',
    `timestampModified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampCreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `active` enum('Y','N') NOT NULL DEFAULT 'Y',
    PRIMARY KEY (`tawasulChatID`),
    KEY `type` (`type`),
    KEY `modified` (`timestampModified`),
    KEY `createdBy` (`createdBy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

// Membership and per-person state. archived/pinned/muted/notify are the four
// WhatsApp chat-list behaviours, and all of them are per person rather than
// per chat, which is why they live here rather than on the chat itself. The
// lastRead/lastDelivered pair is what produces the single and double ticks.
<<<'SQL'
CREATE TABLE IF NOT EXISTS `tawasulMessengerChatParticipant` (
    `tawasulChatParticipantID` int(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `tawasulChatID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `tawasulPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `role` enum('owner','admin','member') NOT NULL DEFAULT 'member',
    `notify` enum('all','mentions','none') NOT NULL DEFAULT 'all',
    `archived` enum('Y','N') NOT NULL DEFAULT 'N',
    `pinned` enum('Y','N') NOT NULL DEFAULT 'N',
    `mutedUntil` datetime NULL DEFAULT NULL,
    `lastReadMessageID` int(12) UNSIGNED ZEROFILL NULL DEFAULT NULL,
    `lastReadTimestamp` datetime NULL DEFAULT NULL,
    `lastDeliveredMessageID` int(12) UNSIGNED ZEROFILL NULL DEFAULT NULL,
    `lastDeliveredTimestamp` datetime NULL DEFAULT NULL,
    `joinedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `leftAt` datetime NULL DEFAULT NULL,
    PRIMARY KEY (`tawasulChatParticipantID`),
    UNIQUE KEY `chatPerson` (`tawasulChatID`,`tawasulPersonID`),
    KEY `person` (`tawasulPersonID`,`archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

// One row per message. A deleted message keeps its row with the content
// cleared and deletedAt set: removing the row entirely would shift the
// timestamps of everything after it and break both the client's message-id
// bookkeeping and the reply chain pointing at it.
<<<'SQL'
CREATE TABLE IF NOT EXISTS `tawasulMessengerChatMessage` (
    `tawasulChatMessageID` int(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `tawasulChatID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `tawasulPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `type` enum('text','image','video','audio','document','location','system') NOT NULL DEFAULT 'text',
    `status` enum('pending','sent') NOT NULL DEFAULT 'sent',
    `content` text NULL DEFAULT NULL,
    `replyToMessageID` int(12) UNSIGNED ZEROFILL NULL DEFAULT NULL,
    `forwardedFromID` int(12) UNSIGNED ZEROFILL NULL DEFAULT NULL,
    `relatedPersonIDs` varchar(255) NULL DEFAULT NULL,
    `locationLat` decimal(10,7) NULL DEFAULT NULL,
    `locationLng` decimal(10,7) NULL DEFAULT NULL,
    `locationName` varchar(255) NULL DEFAULT NULL,
    `durationSeconds` int NULL DEFAULT NULL,
    `waveform` varchar(255) NULL DEFAULT NULL,
    `editedAt` datetime NULL DEFAULT NULL,
    `deletedAt` datetime NULL DEFAULT NULL,
    `timestampModified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampCreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tawasulChatMessageID`),
    KEY `chatTime` (`tawasulChatID`,`timestampCreated`),
    KEY `person` (`tawasulPersonID`),
    KEY `created` (`timestampCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

// Files are kept on disk and referenced by relative path, as everywhere else in
// TawasulOS. width/height/duration are stored rather than derived so the client
// can lay out a media bubble before the bytes have loaded.
<<<'SQL'
CREATE TABLE IF NOT EXISTS `tawasulMessengerChatAttachment` (
    `tawasulChatAttachmentID` int(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `tawasulChatMessageID` int(12) UNSIGNED ZEROFILL NOT NULL,
    `filePath` varchar(255) NOT NULL,
    `thumbnailPath` varchar(255) NULL DEFAULT NULL,
    `fileName` varchar(255) NOT NULL,
    `fileMimeType` varchar(100) NOT NULL DEFAULT '',
    `fileSize` int UNSIGNED NOT NULL DEFAULT 0,
    `width` int NULL DEFAULT NULL,
    `height` int NULL DEFAULT NULL,
    `durationSeconds` int NULL DEFAULT NULL,
    `uploadedBy` int(10) UNSIGNED ZEROFILL NOT NULL,
    `timestampUploaded` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tawasulChatAttachmentID`),
    KEY `message` (`tawasulChatMessageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

// Per-recipient state for the ticks. A row exists for every participant other
// than the author, so the counts behind the ticks are one index range scan.
<<<'SQL'
CREATE TABLE IF NOT EXISTS `tawasulMessengerChatReceipt` (
    `tawasulChatReceiptID` int(14) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `tawasulChatMessageID` int(12) UNSIGNED ZEROFILL NOT NULL,
    `tawasulPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `deliveredAt` datetime NULL DEFAULT NULL,
    `readAt` datetime NULL DEFAULT NULL,
    PRIMARY KEY (`tawasulChatReceiptID`),
    UNIQUE KEY `messagePerson` (`tawasulChatMessageID`,`tawasulPersonID`),
    KEY `personUnread` (`tawasulPersonID`,`readAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

// One reaction per person per message: reacting again replaces the emoji rather
// than adding a second row, which is why the unique key is on the pair.
<<<'SQL'
CREATE TABLE IF NOT EXISTS `tawasulMessengerChatReaction` (
    `tawasulChatReactionID` int(14) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `tawasulChatMessageID` int(12) UNSIGNED ZEROFILL NOT NULL,
    `tawasulPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `emoji` varchar(16) NOT NULL,
    `timestampCreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tawasulChatReactionID`),
    UNIQUE KEY `messagePerson` (`tawasulChatMessageID`,`tawasulPersonID`),
    KEY `message` (`tawasulChatMessageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

// Starred messages are per person, so they cannot live on the message itself.
<<<'SQL'
CREATE TABLE IF NOT EXISTS `tawasulMessengerChatStar` (
    `tawasulChatStarID` int(14) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `tawasulChatMessageID` int(12) UNSIGNED ZEROFILL NOT NULL,
    `tawasulPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `timestampCreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tawasulChatStarID`),
    UNIQUE KEY `messagePerson` (`tawasulChatMessageID`,`tawasulPersonID`),
    KEY `person` (`tawasulPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

// Messages one person has hidden from their own view. "Delete for me" has to be
// stored per person, because the alternative — removing the row — is visible to
// everybody else in the conversation, which is the opposite of what was asked
// for. A row here means "do not show me this", and is honoured by every query
// that returns messages.
<<<'SQL'
CREATE TABLE IF NOT EXISTS `tawasulMessengerChatMessageHidden` (
    `tawasulChatMessageHiddenID` int(14) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `tawasulChatMessageID` int(12) UNSIGNED ZEROFILL NOT NULL,
    `tawasulPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `timestampCreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tawasulChatMessageHiddenID`),
    UNIQUE KEY `messagePerson` (`tawasulChatMessageID`,`tawasulPersonID`),
    KEY `person` (`tawasulPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

// Pinned messages. A pin belongs to the conversation rather than to the person
// who set it: everybody in a group sees the same pinned strip, which is what
// makes it usable as a notice board. The unique key on the pair is what stops
// the same message being pinned twice.
<<<'SQL'
CREATE TABLE IF NOT EXISTS `tawasulMessengerChatPin` (
    `tawasulChatPinID` int(14) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `tawasulChatID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `tawasulChatMessageID` int(12) UNSIGNED ZEROFILL NOT NULL,
    `pinnedBy` int(10) UNSIGNED ZEROFILL NOT NULL,
    `timestampCreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tawasulChatPinID`),
    UNIQUE KEY `chatMessage` (`tawasulChatID`,`tawasulChatMessageID`),
    KEY `chat` (`tawasulChatID`),
    KEY `message` (`tawasulChatMessageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

// Drafts. One row per person per conversation, holding the text that was typed
// but not sent. Stored on the server rather than in the browser so a draft
// survives switching devices, which is the whole reason people are annoyed by
// localStorage-only drafts.
<<<'SQL'
CREATE TABLE IF NOT EXISTS `tawasulMessengerChatDraft` (
    `tawasulChatDraftID` int(14) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `tawasulChatID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `tawasulPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `content` text NULL DEFAULT NULL,
    `replyToMessageID` int(12) UNSIGNED ZEROFILL NULL DEFAULT NULL,
    `timestampCreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`tawasulChatDraftID`),
    UNIQUE KEY `chatPerson` (`tawasulChatID`,`tawasulPersonID`),
    KEY `person` (`tawasulPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

// Presence is a single row per person, keyed by the person rather than by a
// session, so a person signed in on two devices still shows one state. The
// client refreshes lastPingAt on every poll; a person whose ping has gone stale
// reads as offline without anything needing to clean up on logout.
<<<'SQL'
CREATE TABLE IF NOT EXISTS `tawasulMessengerChatPresence` (
    `tawasulPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `status` enum('online','away','offline') NOT NULL DEFAULT 'offline',
    `lastSeenAt` datetime NULL DEFAULT NULL,
    `typingChatID` int(10) UNSIGNED ZEROFILL NULL DEFAULT NULL,
    `typingAt` datetime NULL DEFAULT NULL,
    `lastPingAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tawasulPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

];
