<?php
/*
Messenger Chat: real-time messaging between individuals and groups.
Includes chats, participants, messages and file attachments.
All resources sit behind the messenger scope and use the TawasulMessenger 'Chat'
action for role-permission enforcement.

Every one of these is scoped to the caller with 'personFilter', which
QueryBuilder applies as an extra WHERE clause bound to the signed-in person. Chat
is private: without that clause a caller could list every conversation in the
school, including other people's messages, which is why a request with no
person behind it is refused rather than returned unscoped.

The upstream definitions selected several columns this platform does not have —
tawasulGroupID on the chat, and fileAttachment/fileMimeType/fileSize/tawasulStudentID
on the message, where attachments are a separate table keyed by message. Those are
dropped rather than faked, so the response describes what is actually stored.
*/

return [

    'chats' => [
        'title' => 'Chats',
        'group' => 'Messenger',
        'scope' => 'messenger',
        'module' => 'TawasulMessenger',
        'action' => 'Chat',
        'table' => 'tawasulMessengerChat',
        'primaryKey' => 'tawasulChatID',
        'description' => 'Individual and group chat containers. Type is "individual" or "group".',
        'select' => "SELECT c.tawasulChatID, c.type, c.name, c.description, c.iconPath, c.createdBy, c.disappearingMinutes,
                            c.lastMessageID, c.lastMessagePreview, c.active, c.timestampCreated, c.timestampModified,
                            creator.preferredName AS creatorName, creator.surname AS creatorSurname,
                            (SELECT COUNT(*) FROM tawasulMessengerChatParticipant cp
                              WHERE cp.tawasulChatID = c.tawasulChatID AND cp.archived = 'N') AS participantCount
                     FROM tawasulMessengerChat c
                     LEFT JOIN tawasulPerson creator ON (c.createdBy = creator.tawasulPersonID)",
        'filters' => ['type' => 'c.type', 'active' => 'c.active', 'createdBy' => 'c.createdBy'],
        'search' => ['c.name'],
        'sort' => ['timestampModified' => 'c.timestampModified DESC', 'timestampCreated' => 'c.timestampCreated DESC'],
        'defaultSort' => 'c.timestampModified DESC',
        'writable' => ['type', 'name', 'description', 'iconPath', 'disappearingMinutes', 'active'],
        'required' => ['type'],
        'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
        'personFilter' => "c.tawasulChatID IN (SELECT tawasulChatID FROM tawasulMessengerChatParticipant WHERE tawasulPersonID = :currentPersonID AND archived = 'N')",
    ],

    'chat-participants' => [
        'title' => 'Chat Participants',
        'group' => 'Messenger',
        'scope' => 'messenger',
        'module' => 'TawasulMessenger',
        'action' => 'Chat',
        'table' => 'tawasulMessengerChatParticipant',
        'primaryKey' => 'tawasulChatParticipantID',
        'description' => 'Members of a chat with their role (owner, admin, member) and read state.',
        'select' => "SELECT cp.tawasulChatParticipantID, cp.tawasulChatID, cp.tawasulPersonID, cp.role, cp.notify, cp.pinned,
                            cp.archived, cp.mutedUntil, cp.lastReadMessageID, cp.lastReadTimestamp, cp.joinedAt, cp.leftAt,
                            p.preferredName, p.surname, p.email, p.image_240, r.name AS roleName, r.category AS roleCategory
                     FROM tawasulMessengerChatParticipant cp
                     JOIN tawasulPerson p ON (p.tawasulPersonID = cp.tawasulPersonID)
                     JOIN tawasulRole r ON (p.tawasulRoleIDPrimary = r.tawasulRoleID)",
        'filters' => ['tawasulChatID' => 'cp.tawasulChatID', 'tawasulPersonID' => 'cp.tawasulPersonID', 'role' => 'cp.role', 'archived' => 'cp.archived'],
        'sort' => ['joinedAt' => 'cp.joinedAt'],
        'defaultSort' => 'cp.joinedAt',
        'writable' => ['tawasulChatID', 'tawasulPersonID', 'role', 'notify', 'pinned', 'mutedUntil', 'lastReadMessageID', 'lastReadTimestamp', 'archived'],
        'required' => ['tawasulChatID', 'tawasulPersonID'],
        'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
        'personFilter' => "cp.tawasulChatID IN (SELECT tawasulChatID FROM tawasulMessengerChatParticipant WHERE tawasulPersonID = :currentPersonID AND archived = 'N')",
    ],

    'chat-messages' => [
        'title' => 'Chat Messages',
        'group' => 'Messenger',
        'scope' => 'messenger',
        'module' => 'TawasulMessenger',
        'action' => 'Chat',
        'table' => 'tawasulMessengerChatMessage',
        'primaryKey' => 'tawasulChatMessageID',
        'description' => 'Messages within a chat: text, images, location and voice notes, plus system notices. Files live in chat-attachments.',
        'select' => "SELECT m.tawasulChatMessageID, m.tawasulChatID, m.tawasulPersonID, m.type, m.status, m.content,
                            m.replyToMessageID, m.forwardedFromID, m.relatedPersonIDs, m.locationLat, m.locationLng,
                            m.locationName, m.durationSeconds, m.editedAt, m.deletedAt, m.timestampCreated, m.timestampModified,
                            p.preferredName, p.surname, p.image_240
                     FROM tawasulMessengerChatMessage m
                     JOIN tawasulPerson p ON (p.tawasulPersonID = m.tawasulPersonID)",
        'filters' => ['tawasulChatID' => 'm.tawasulChatID', 'tawasulPersonID' => 'm.tawasulPersonID', 'type' => 'm.type', 'status' => 'm.status'],
        'sort' => ['timestampCreated' => 'm.timestampCreated'],
        'defaultSort' => 'm.timestampCreated DESC',
        'writable' => ['tawasulChatID', 'tawasulPersonID', 'type', 'status', 'content', 'replyToMessageID', 'forwardedFromID', 'relatedPersonIDs', 'durationSeconds'],
        'required' => ['tawasulChatID', 'tawasulPersonID'],
        'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
        'personFilter' => "m.tawasulChatID IN (SELECT tawasulChatID FROM tawasulMessengerChatParticipant WHERE tawasulPersonID = :currentPersonID AND archived = 'N')",
    ],

    'chat-attachments' => [
        'title' => 'Chat Attachments',
        'group' => 'Messenger',
        'scope' => 'messenger',
        'module' => 'TawasulMessenger',
        'action' => 'Chat',
        'table' => 'tawasulMessengerChatAttachment',
        'primaryKey' => 'tawasulChatAttachmentID',
        'description' => 'Files attached to chat messages. Reachable only through a chat the caller belongs to.',
        'select' => "SELECT ca.tawasulChatAttachmentID, ca.tawasulChatMessageID, ca.filePath, ca.thumbnailPath, ca.fileName,
                            ca.fileMimeType, ca.fileSize, ca.width, ca.height, ca.durationSeconds, ca.uploadedBy, ca.timestampUploaded,
                            uploader.preferredName AS uploaderName, uploader.surname AS uploaderSurname
                     FROM tawasulMessengerChatAttachment ca
                     JOIN tawasulPerson uploader ON (uploader.tawasulPersonID = ca.uploadedBy)",
        'filters' => ['tawasulChatMessageID' => 'ca.tawasulChatMessageID', 'uploadedBy' => 'ca.uploadedBy', 'fileMimeType' => 'ca.fileMimeType'],
        'sort' => ['timestampUploaded' => 'ca.timestampUploaded'],
        'defaultSort' => 'ca.timestampUploaded DESC',
        'writable' => ['tawasulChatMessageID', 'filePath', 'thumbnailPath', 'fileName', 'fileMimeType', 'fileSize', 'width', 'height', 'durationSeconds'],
        'required' => ['tawasulChatMessageID', 'filePath', 'fileName', 'fileSize', 'fileMimeType'],
        'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
        'sensitive' => ['filePath'],
        'personFilter' => "ca.tawasulChatMessageID IN (SELECT m.tawasulChatMessageID FROM tawasulMessengerChatMessage m
                            JOIN tawasulMessengerChatParticipant cp ON (cp.tawasulChatID = m.tawasulChatID)
                            WHERE cp.tawasulPersonID = :currentPersonID AND cp.archived = 'N')",
    ],

];
