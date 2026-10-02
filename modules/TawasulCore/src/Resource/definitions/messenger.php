<?php
/*
Messenger Chat: real-time messaging between individuals and groups.
Includes chats, participants, messages and file attachments.
All resources sit behind the messenger scope and use the TawasulMessenger 'Chat' action
for role-permission enforcement.
*/

return [

    'chats' => [
        'title' => 'Chats',
        'group' => 'Messenger',
        'scope' => 'messenger',
        'module' => 'TawasulMessenger',
        'action' => 'Chat',
        'table' => 'tos_chat',
        'primaryKey' => 'chat_id',
        'description' => 'Individual and group chat containers. Type is "individual" or "group".',
        'select' => "SELECT c.chat_id, c.type, c.name, c.tawasulGroupID, c.createdBy, c.timestampCreated, c.timestampModified, c.active,
                            creator.preferredName AS creatorName, creator.surname AS creatorSurname,
                            (SELECT COUNT(*) FROM tos_chat_participant cp WHERE cp.chat_id = c.chat_id AND cp.archived = 'N') AS participantCount,
                            (SELECT COUNT(*) FROM tos_chat_message m WHERE m.chat_id = c.chat_id
                             AND m.timestampCreated > (SELECT COALESCE(lastReadTimestamp, '0000-01-01') FROM tos_chat_participant
                                                     WHERE chat_id = c.chat_id AND tawasulPersonID = :currentPersonID)) AS unreadCount
                     FROM tos_chat c
                     LEFT JOIN tawasulPerson creator ON (c.createdBy = creator.tawasulPersonID)",
        'filters' => ['type' => 'c.type', 'active' => 'c.active', 'createdBy' => 'c.createdBy'],
        'search' => ['c.name'],
        'sort' => ['timestampModified' => 'c.timestampModified DESC', 'timestampCreated' => 'c.timestampCreated DESC'],
        'defaultSort' => 'c.timestampModified DESC',
        'writable' => ['type', 'name', 'tawasulGroupID', 'active'],
        'required' => ['type'],
        'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
        'where' => ['c.chat_id IN (SELECT chat_id FROM tos_chat_participant WHERE tawasulPersonID = :currentPersonID AND archived = \'N\')'],
    ],

    'chat-participants' => [
        'title' => 'Chat Participants',
        'group' => 'Messenger',
        'scope' => 'messenger',
        'module' => 'TawasulMessenger',
        'action' => 'Chat',
        'table' => 'tos_chat_participant',
        'primaryKey' => 'chat_participant_id',
        'description' => 'Members of a chat with their role (owner, admin, member) and read state.',
        'select' => "SELECT cp.chat_participant_id, cp.chat_id, cp.tawasulPersonID, cp.role, cp.joinedAt, cp.mutedUntil, cp.lastReadMessageID, cp.lastReadTimestamp, cp.archived,
                            p.preferredName, p.surname, p.email, p.image_240, r.name AS roleName, r.category AS roleCategory
                     FROM tos_chat_participant cp
                     JOIN tawasulPerson p ON (p.tawasulPersonID = cp.tawasulPersonID)
                     JOIN tawasulRole r ON (p.tawasulRoleIDPrimary = r.tawasulRoleID)",
        'filters' => ['chat_id' => 'cp.chat_id', 'tawasulPersonID' => 'cp.tawasulPersonID', 'role' => 'cp.role', 'archived' => 'cp.archived'],
        'sort' => ['joinedAt' => 'cp.joinedAt'],
        'defaultSort' => 'cp.joinedAt',
        'writable' => ['chat_id', 'tawasulPersonID', 'role', 'mutedUntil', 'lastReadMessageID', 'lastReadTimestamp', 'archived'],
        'required' => ['chat_id', 'tawasulPersonID'],
        'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
        'where' => ['cp.chat_id IN (SELECT chat_id FROM tos_chat_participant WHERE tawasulPersonID = :currentPersonID AND archived = \'N\')'],
    ],

    'chat-messages' => [
        'title' => 'Chat Messages',
        'group' => 'Messenger',
        'scope' => 'messenger',
        'module' => 'TawasulMessenger',
        'action' => 'Chat',
        'table' => 'tos_chat_message',
        'primaryKey' => 'chat_message_id',
        'description' => 'Messages within a chat: text, images, files and system notices.',
        'select' => "SELECT m.chat_message_id, m.chat_id, m.tawasulPersonID, m.type, m.content, m.timestampCreated, m.timestampModified, m.status, m.replyToMessageID, m.fileAttachment, m.fileMimeType, m.fileSize, m.student_id, m.relatedPersonIDs,
                            p.preferredName, p.surname, p.image_240
                     FROM tos_chat_message m
                     JOIN tawasulPerson p ON (p.tawasulPersonID = m.tawasulPersonID)",
        'filters' => ['chat_id' => 'm.chat_id', 'tawasulPersonID' => 'm.tawasulPersonID', 'type' => 'm.type', 'status' => 'm.status'],
        'sort' => ['timestampCreated' => 'm.timestampCreated'],
        'defaultSort' => 'm.timestampCreated DESC',
        'writable' => ['chat_id', 'tawasulPersonID', 'type', 'content', 'fileAttachment', 'fileMimeType', 'fileSize', 'student_id', 'relatedPersonIDs', 'replyToMessageID', 'status'],
        'required' => ['chat_id', 'tawasulPersonID'],
        'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
        'sensitive' => ['fileAttachment'],
        'where' => ['m.chat_id IN (SELECT chat_id FROM tos_chat_participant WHERE tawasulPersonID = :currentPersonID AND archived = \'N\')'],
    ],

    'chat-attachments' => [
        'title' => 'Chat Attachments',
        'group' => 'Messenger',
        'scope' => 'messenger',
        'module' => 'TawasulMessenger',
        'action' => 'Chat',
        'table' => 'tos_chat_attachment',
        'primaryKey' => 'chat_attachment_id',
        'description' => 'File attachments linked to chat messages, tied to a specific student for relationship validation.',
        'select' => "SELECT ca.chat_attachment_id, ca.chat_message_id, ca.student_id, ca.filePath, ca.fileName, ca.fileSize, ca.fileMimeType, ca.uploadedBy, ca.timestampUploaded,
                            uploader.preferredName AS uploaderName, uploader.surname AS uploaderSurname,
                            student.preferredName AS studentName, student.surname AS studentSurname
                     FROM tos_chat_attachment ca
                     JOIN tawasulPerson uploader ON (uploader.tawasulPersonID = ca.uploadedBy)
                     JOIN tawasulPerson student ON (student.tawasulPersonID = ca.student_id)",
        'filters' => ['chat_message_id' => 'ca.chat_message_id', 'student_id' => 'ca.student_id', 'uploadedBy' => 'ca.uploadedBy'],
        'sort' => ['timestampUploaded' => 'ca.timestampUploaded'],
        'defaultSort' => 'ca.timestampUploaded DESC',
        'writable' => ['chat_message_id', 'student_id', 'filePath', 'fileName', 'fileSize', 'fileMimeType'],
        'required' => ['chat_message_id', 'student_id', 'filePath', 'fileName', 'fileSize', 'fileMimeType'],
        'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
        'sensitive' => ['filePath'],
        'where' => ['ca.chat_message_id IN (SELECT m.chat_message_id FROM tos_chat_message m JOIN tos_chat_participant cp ON (cp.chat_id = m.chat_id) WHERE cp.tawasulPersonID = :currentPersonID AND cp.archived = \'N\')'],
    ],

];
