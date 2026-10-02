<?php
namespace Tos\Module\TawasulChat\Domain;

/**
 * Messages and their attachments.
 *
 * Reads that the chat window performs come back as one flat list rather than a
 * message row plus a second query per message for its attachments: a screen of
 * the last 50 messages would otherwise be 51 queries.
 */
class MessageGateway
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function insert(array $message): int
    {
        $this->db->prepare(
            'INSERT INTO `tawasulMessengerChatMessage`
                (tawasulChatID, tawasulPersonID, type, status, content, replyToMessageID,
                 forwardedFromID, relatedPersonIDs, locationLat, locationLng, locationName,
                 durationSeconds, waveform)
             VALUES
                (:tawasulChatID, :tawasulPersonID, :type, :status, :content, :replyToMessageID,
                 :forwardedFromID, :relatedPersonIDs, :locationLat, :locationLng, :locationName,
                 :durationSeconds, :waveform)'
        )->execute([
            'tawasulChatID' => $message['tawasulChatID'],
            'tawasulPersonID' => ChatGateway::pad($message['tawasulPersonID']),
            'type' => $message['type'],
            'status' => $message['status'] ?? 'sent',
            'content' => $message['content'] ?? null,
            'replyToMessageID' => $message['replyToMessageID'] ?? null,
            'forwardedFromID' => $message['forwardedFromID'] ?? null,
            'relatedPersonIDs' => $message['relatedPersonIDs'] ?? null,
            'locationLat' => $message['locationLat'] ?? null,
            'locationLng' => $message['locationLng'] ?? null,
            'locationName' => $message['locationName'] ?? null,
            'durationSeconds' => $message['durationMinutes'] ?? $message['durationSeconds'] ?? null,
            'waveform' => $message['waveform'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function find(int $messageID): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `tawasulMessengerChatMessage` WHERE tawasulChatMessageID = :id');
        $stmt->execute(['id' => $messageID]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function belongsToChat(int $messageID, int $chatID): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM `tawasulMessengerChatMessage` WHERE tawasulChatMessageID = :id AND tawasulChatID = :chatID'
        );
        $stmt->execute(['id' => $messageID, 'chatID' => $chatID]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * A page of the conversation, oldest first, with attachments and reactions
     * folded in.
     *
     * $beforeID pages backwards: the client asks for everything older than the
     * oldest message it holds, which is stable in a way that paging by timestamp
     * is not — two messages written in the same second would otherwise make a
     * timestamp cursor skip or repeat one of them.
     */
    public function listForChat(int $chatID, ?int $beforeID, string $personID, int $limit = 50): array
    {
        $where = 'm.tawasulChatID = :chatID';
        $params = ['chatID' => $chatID, 'personID' => ChatGateway::pad($personID)];
        if ($beforeID !== null) {
            $where .= ' AND m.tawasulChatMessageID < :beforeID';
            $params['beforeID'] = $beforeID;
        }

        // Messages this person chose to hide from their own view are not
        // returned to them. Applied here rather than in the service so the limit
        // still counts messages they can actually see: without it a page of 50
        // could come back holding fewer than 50 visible rows and the client
        // would stop paging.
        $where .= ' AND NOT EXISTS (SELECT 1 FROM `tawasulMessengerChatMessageHidden` h
                                 WHERE h.tawasulChatMessageID = m.tawasulChatMessageID
                                   AND h.tawasulPersonID = :personID)';

        $stmt = $this->db->prepare(
            'SELECT m.*, p.preferredName AS senderPreferredName, p.surname AS senderSurname, p.image_240 AS senderImage,
                    (SELECT COUNT(*) FROM `tawasulMessengerChatReceipt` r
                      WHERE r.tawasulChatMessageID = m.tawasulChatMessageID AND r.deliveredAt IS NOT NULL) AS deliveredByCount,
                    (SELECT COUNT(*) FROM `tawasulMessengerChatReceipt` r
                      WHERE r.tawasulChatMessageID = m.tawasulChatMessageID AND r.readAt IS NOT NULL) AS readByCount,
                    (SELECT COUNT(*) FROM `tawasulMessengerChatParticipant` c
                      WHERE c.tawasulChatID = m.tawasulChatID AND c.leftAt IS NULL) AS participantCount
               FROM `tawasulMessengerChatMessage` m
               JOIN `tawasulPerson` p ON (p.tawasulPersonID = m.tawasulPersonID)
              WHERE '.$where.'
              ORDER BY m.tawasulChatMessageID DESC
              LIMIT '.(int) $limit
        );
        $stmt->execute($params);
        $rows = array_reverse($stmt->fetchAll());

        if ($rows === []) {
            return [];
        }

        $ids = array_column($rows, 'tawasulChatMessageID');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $attachments = $this->db->prepare(
            'SELECT tawasulChatMessageID, tawasulChatAttachmentID, filePath, thumbnailPath, fileName,
                    fileMimeType, fileSize, width, height, durationSeconds
               FROM `tawasulMessengerChatAttachment` WHERE tawasulChatMessageID IN ('.$placeholders.')'
        );
        $attachments->execute($ids);

        $reactions = $this->db->prepare(
            'SELECT tawasulChatMessageID, emoji, tawasulPersonID
               FROM `tawasulMessengerChatReaction` WHERE tawasulChatMessageID IN ('.$placeholders.')
              ORDER BY tawasulChatReactionID'
        );
        $reactions->execute($ids);

        $byMessageAttachments = [];
        foreach ($attachments->fetchAll() as $row) {
            $byMessageAttachments[$row['tawasulChatMessageID']][] = $row;
        }
        $byMessageReactions = [];
        foreach ($reactions->fetchAll() as $row) {
            $byMessageReactions[$row['tawasulChatMessageID']][] = $row;
        }

        foreach ($rows as &$row) {
            $row['attachments'] = $byMessageAttachments[$row['tawasulChatMessageID']] ?? [];
            $row['reactions'] = $byMessageReactions[$row['tawasulChatMessageID']] ?? [];
            $row['starredByMe'] = false;
        }
        unset($row);

        return $rows;
    }

    /**
     * The message a reply quotes, including its author, so the client can render
     * the strip above the composer without a second request.
     */
    public function replyContext(?int $messageID): ?array
    {
        if ($messageID === null) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT m.tawasulChatMessageID, m.content, m.type, m.deletedAt,
                    p.preferredName AS senderPreferredName, p.surname AS senderSurname
               FROM `tawasulMessengerChatMessage` m
               JOIN `tawasulPerson` p ON (p.tawasulPersonID = m.tawasulPersonID)
              WHERE m.tawasulChatMessageID = :id'
        );
        $stmt->execute(['id' => $messageID]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function updateContent(int $messageID, string $content): void
    {
        $this->db->prepare(
            'UPDATE `tawasulMessengerChatMessage`
                SET content = :content, editedAt = NOW(), timestampModified = NOW()
              WHERE tawasulChatMessageID = :id'
        )->execute(['content' => $content, 'id' => $messageID]);
    }

    /**
     * Delete for everyone.
     *
     * The row survives with its content cleared: the client keys messages by ID
     * and a reply points at this row, so removing it would leave a dangling
     * reference and reorder everything after it.
     */
    public function deleteForEveryone(int $messageID): void
    {
        $this->db->prepare(
            'UPDATE `tawasulMessengerChatMessage`
                SET content = NULL, locationLat = NULL, locationLng = NULL, locationName = NULL,
                    waveform = NULL, durationSeconds = NULL,
                    deletedAt = NOW(), timestampModified = NOW()
              WHERE tawasulChatMessageID = :id'
        )->execute(['id' => $messageID]);
    }

    /**
     * Delete for one person only. There is no table for this: the sender simply
     * never sends the message, because the sender is the only person for whom a
     * message can be removed from their own view.
     */
    public function deleteOwn(int $messageID): void
    {
        $this->db->prepare('DELETE FROM `tawasulMessengerChatMessage` WHERE tawasulChatMessageID = :id')
            ->execute(['id' => $messageID]);
    }

    /**
     * Delete a message and everything hanging off it.
     *
     * Used when a sender removes their own message outright. Children go first
     * so a failure part way through cannot leave an attachment row pointing at a
     * message that no longer exists.
     */
    public function deleteCompletely(int $messageID): void
    {
        $this->db->prepare('DELETE FROM `tawasulMessengerChatAttachment` WHERE tawasulChatMessageID = :id')->execute(['id' => $messageID]);
        $this->db->prepare('DELETE FROM `tawasulMessengerChatReceipt` WHERE tawasulChatMessageID = :id')->execute(['id' => $messageID]);
        $this->db->prepare('DELETE FROM `tawasulMessengerChatReaction` WHERE tawasulChatMessageID = :id')->execute(['id' => $messageID]);
        $this->db->prepare('DELETE FROM `tawasulMessengerChatStar` WHERE tawasulChatMessageID = :id')->execute(['id' => $messageID]);
        $this->db->prepare('DELETE FROM `tawasulMessengerChatPin` WHERE tawasulChatMessageID = :id')->execute(['id' => $messageID]);
        $this->db->prepare('DELETE FROM `tawasulMessengerChatMessageHidden` WHERE tawasulChatMessageID = :id')->execute(['id' => $messageID]);
        $this->db->prepare('DELETE FROM `tawasulMessengerChatMessage` WHERE tawasulChatMessageID = :id')->execute(['id' => $messageID]);
    }

    /**
     * Hide a message from one person's view of a conversation.
     *
     * Per person on purpose: the message stays exactly where it is for everyone
     * else. The unique key on the pair means a second request is a no-op rather
     * than an error, which matters because the client can send it twice.
     */
    public function setHidden(int $messageID, string $personID, bool $hidden): void
    {
        if ($hidden) {
            $this->db->prepare(
                'INSERT INTO `tawasulMessengerChatMessageHidden` (tawasulChatMessageID, tawasulPersonID)
                 VALUES (:messageID, :personID)
                 ON DUPLICATE KEY UPDATE tawasulChatMessageID = tawasulChatMessageID'
            )->execute(['messageID' => $messageID, 'personID' => ChatGateway::pad($personID)]);

            return;
        }

        $this->db->prepare(
            'DELETE FROM `tawasulMessengerChatMessageHidden` WHERE tawasulChatMessageID = :messageID AND tawasulPersonID = :personID'
        )->execute(['messageID' => $messageID, 'personID' => ChatGateway::pad($personID)]);
    }

    /** Every attachment path belonging to a message, so the files can be removed. */
    public function attachmentPaths(int $messageID): array
    {
        $stmt = $this->db->prepare(
            'SELECT filePath, thumbnailPath FROM `tawasulMessengerChatAttachment` WHERE tawasulChatMessageID = :id'
        );
        $stmt->execute(['id' => $messageID]);

        $paths = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!empty($row['filePath'])) {
                $paths[] = $row['filePath'];
            }
            if (!empty($row['thumbnailPath'])) {
                $paths[] = $row['thumbnailPath'];
            }
        }

        return $paths;
    }

    public function addAttachment(int $messageID, array $file, string $uploadedBy): int
    {
        $this->db->prepare(
            'INSERT INTO `tawasulMessengerChatAttachment`
                (tawasulChatMessageID, filePath, thumbnailPath, fileName, fileMimeType,
                 fileSize, width, height, durationSeconds, uploadedBy)
             VALUES
                (:messageID, :filePath, :thumbnailPath, :fileName, :fileMimeType,
                 :fileSize, :width, :height, :durationSeconds, :uploadedBy)'
        )->execute([
            'messageID' => $messageID,
            'filePath' => $file['filePath'],
            'thumbnailPath' => $file['thumbnailPath'] ?? null,
            'fileName' => $file['fileName'],
            'fileMimeType' => $file['fileMimeType'] ?? '',
            'fileSize' => (int) ($file['fileSize'] ?? 0),
            'width' => $file['width'] ?? null,
            'height' => $file['height'] ?? null,
            'durationSeconds' => $file['durationSeconds'] ?? null,
            'uploadedBy' => ChatGateway::pad($uploadedBy),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function messageIDsOlderThan(int $chatID, string $cutoff): array
    {
        $stmt = $this->db->prepare(
            'SELECT tawasulChatMessageID FROM `tawasulMessengerChatMessage`
              WHERE tawasulChatID = :chatID AND timestampCreated < :cutoff'
        );
        $stmt->execute(['chatID' => $chatID, 'cutoff' => $cutoff]);

        return array_column($stmt->fetchAll(), 'tawasulChatMessageID');
    }

    /**
     * Full-text-ish search restricted to the person's own conversations.
     *
     * The scoping is the important part: searching all of chat and filtering
     * afterwards would let a search term reveal that a message exists in a
     * conversation the person was removed from.
     */
    public function search(string $personID, string $needle, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.tawasulChatMessageID, m.tawasulChatID, m.content, m.timestampCreated, m.deletedAt,
                    p.preferredName AS senderPreferredName, p.surname AS senderSurname
               FROM `tawasulMessengerChatMessage` m
               JOIN `tawasulMessengerChatParticipant` cp ON (cp.tawasulChatID = m.tawasulChatID AND cp.tawasulPersonID = :personID)
               JOIN `tawasulPerson` p ON (p.tawasulPersonID = m.tawasulPersonID)
              WHERE cp.leftAt IS NULL
                AND m.deletedAt IS NULL
                AND m.content LIKE :needle
                AND NOT EXISTS (SELECT 1 FROM `tawasulMessengerChatMessageHidden` h
                                 WHERE h.tawasulChatMessageID = m.tawasulChatMessageID
                                   AND h.tawasulPersonID = :personID)
              ORDER BY m.timestampCreated DESC
              LIMIT '.(int) $limit
        );
        $stmt->execute(['personID' => ChatGateway::pad($personID), 'needle' => '%'.$needle.'%']);

        return $stmt->fetchAll();
    }

    /** Messages this person has starred, newest first. */
    public function starredFor(string $personID, int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.tawasulChatMessageID, m.tawasulChatID, m.content, m.type, m.timestampCreated,
                    p.preferredName AS senderPreferredName, p.surname AS senderSurname,
                    s.tawasulChatStarID
               FROM `tawasulMessengerChatStar` s
               JOIN `tawasulMessengerChatMessage` m ON (m.tawasulChatMessageID = s.tawasulChatMessageID)
               JOIN `tawasulMessengerChatParticipant` cp
                 ON (cp.tawasulChatID = m.tawasulChatID AND cp.tawasulPersonID = s.tawasulPersonID)
               JOIN `tawasulPerson` p ON (p.tawasulPersonID = m.tawasulPersonID)
              WHERE s.tawasulPersonID = :personID AND cp.leftAt IS NULL
                AND m.deletedAt IS NULL
                AND NOT EXISTS (SELECT 1 FROM `tawasulMessengerChatMessageHidden` h
                                 WHERE h.tawasulChatMessageID = m.tawasulChatMessageID
                                   AND h.tawasulPersonID = :personID)
              ORDER BY s.tawasulChatStarID DESC
              LIMIT '.(int) $limit
        );
        $stmt->execute(['personID' => ChatGateway::pad($personID)]);

        return $stmt->fetchAll();
    }

    public function starredBy(string $personID, array $messageIDs): array
    {
        if ($messageIDs === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($messageIDs), '?'));
        $stmt = $this->db->prepare(
            'SELECT tawasulChatMessageID FROM `tawasulMessengerChatStar`
              WHERE tawasulPersonID = ? AND tawasulChatMessageID IN ('.$placeholders.')'
        );
        $stmt->execute(array_merge([ChatGateway::pad($personID)], array_values($messageIDs)));

        return array_column($stmt->fetchAll(), 'tawasulChatMessageID');
    }

    public function setStarred(int $messageID, string $personID, bool $starred): void
    {
        if ($starred) {
            $this->db->prepare(
                'INSERT INTO `tawasulMessengerChatStar` (tawasulChatMessageID, tawasulPersonID) VALUES (:messageID, :personID)
                 ON DUPLICATE KEY UPDATE tawasulChatMessageID = tawasulChatMessageID'
            )->execute(['messageID' => $messageID, 'personID' => ChatGateway::pad($personID)]);

            return;
        }

        $this->db->prepare(
            'DELETE FROM `tawasulMessengerChatStar` WHERE tawasulChatMessageID = :messageID AND tawasulPersonID = :personID'
        )->execute(['messageID' => $messageID, 'personID' => ChatGateway::pad($personID)]);
    }

    public function setReaction(int $messageID, string $personID, ?string $emoji): void
    {
        if ($emoji === null || $emoji === '') {
            $this->db->prepare(
                'DELETE FROM `tawasulMessengerChatReaction` WHERE tawasulChatMessageID = :messageID AND tawasulPersonID = :personID'
            )->execute(['messageID' => $messageID, 'personID' => ChatGateway::pad($personID)]);

            return;
        }

        $this->db->prepare(
            'INSERT INTO `tawasulMessengerChatReaction` (tawasulChatMessageID, tawasulPersonID, emoji) VALUES (:messageID, :personID, :emoji)
             ON DUPLICATE KEY UPDATE emoji = VALUES(emoji)'
        )->execute(['messageID' => $messageID, 'personID' => ChatGateway::pad($personID), 'emoji' => $emoji]);
    }

    // ------------------------------------------------------------ pinning

    /**
     * Pin a message to the conversation, or unpin it.
     *
     * The ON DUPLICATE KEY is on (chat, message) so that pinning an already
     * pinned message is a no-op rather than an error: the client cannot tell
     * whether another member pinned it in the same second, and failing here
     * would turn a harmless double click into an error message.
     */
    public function setPinned(int $chatID, int $messageID, string $personID, bool $pinned): void
    {
        if ($pinned) {
            $this->db->prepare(
                'INSERT INTO `tawasulMessengerChatPin` (tawasulChatID, tawasulChatMessageID, pinnedBy)
                 VALUES (:chatID, :messageID, :personID)
                 ON DUPLICATE KEY UPDATE pinnedBy = pinnedBy'
            )->execute([
                'chatID' => $chatID,
                'messageID' => $messageID,
                'personID' => ChatGateway::pad($personID),
            ]);

            return;
        }

        $this->db->prepare(
            'DELETE FROM `tawasulMessengerChatPin` WHERE tawasulChatID = :chatID AND tawasulChatMessageID = :messageID'
        )->execute(['chatID' => $chatID, 'messageID' => $messageID]);
    }

    /**
     * The pinned messages in a conversation, newest pin first.
     *
     * Joined to the message rather than read from the pin table alone, because
     * the strip has to show what was pinned. A pin whose message has since been
     * deleted for everyone is dropped here rather than rendered as a blank.
     */
    public function pinnedForChat(int $chatID, int $limit = 3): array
    {
        $stmt = $this->db->prepare(
            'SELECT pin.tawasulChatMessageID, pin.timestampCreated,
                    m.content, m.type, m.deletedAt, m.timestampCreated AS messageCreated,
                    p.preferredName AS senderPreferredName, p.surname AS senderSurname,
                    p.tawasulPersonID AS senderPersonID
               FROM `tawasulMessengerChatPin` pin
               JOIN `tawasulMessengerChatMessage` m ON (m.tawasulChatMessageID = pin.tawasulChatMessageID)
               JOIN `tawasulPerson` p ON (p.tawasulPersonID = m.tawasulPersonID)
              WHERE pin.tawasulChatID = :chatID AND m.deletedAt IS NULL
              ORDER BY pin.timestampCreated DESC, pin.tawasulChatPinID DESC
              LIMIT '.(int) $limit
        );
        $stmt->execute(['chatID' => $chatID]);

        return $stmt->fetchAll();
    }

    /** Which of these messages are pinned, so the bubbles can show the marker. */
    public function pinsForMessages(array $messageIDs): array
    {
        if ($messageIDs === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($messageIDs), '?'));
        $stmt = $this->db->prepare(
            'SELECT tawasulChatMessageID FROM `tawasulMessengerChatPin`
              WHERE tawasulChatMessageID IN ('.$placeholders.')'
        );
        $stmt->execute(array_values($messageIDs));

        return array_column($stmt->fetchAll(), 'tawasulChatMessageID');
    }

    /** How many messages are already pinned, so the cap can be enforced. */
    public function pinCount(int $chatID): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM `tawasulMessengerChatPin` WHERE tawasulChatID = :chatID');
        $stmt->execute(['chatID' => $chatID]);

        return (int) $stmt->fetchColumn();
    }

    // ------------------------------------------------------------- drafts

    /**
     * The person's unsent text for a conversation, or null.
     *
     * Reads the row rather than a cached copy because a draft is also how the
     * chat list tells you where you left off: the composer is populated from
     * here whenever a conversation is opened.
     */
    public function draftFor(int $chatID, string $personID): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT content, replyToMessageID, timestampModified FROM `tawasulMessengerChatDraft`
              WHERE tawasulChatID = :chatID AND tawasulPersonID = :personID'
        );
        $stmt->execute(['chatID' => $chatID, 'personID' => ChatGateway::pad($personID)]);
        $row = $stmt->fetch();

        if ($row === false || $row['content'] === null || trim((string) $row['content']) === '') {
            return null;
        }

        $row['replyToMessageID'] = $row['replyToMessageID'] === null ? null : (int) $row['replyToMessageID'];

        return $row;
    }

    /**
     * Save or clear a draft.
     *
     * Clearing deletes the row rather than storing an empty string, so a chat
     * with an empty draft does not accumulate a row per conversation that
     * every query then has to filter out.
     */
    public function setDraft(int $chatID, string $personID, string $content, ?int $replyToMessageID): void
    {
        if (trim($content) === '') {
            $this->clearDraft($chatID, $personID);

            return;
        }

        $this->db->prepare(
            'INSERT INTO `tawasulMessengerChatDraft` (tawasulChatID, tawasulPersonID, content, replyToMessageID)
             VALUES (:chatID, :personID, :content, :replyToMessageID)
             ON DUPLICATE KEY UPDATE content = VALUES(content), replyToMessageID = VALUES(replyToMessageID)'
        )->execute([
            'chatID' => $chatID,
            'personID' => ChatGateway::pad($personID),
            'content' => $content,
            'replyToMessageID' => $replyToMessageID,
        ]);
    }

    public function clearDraft(int $chatID, string $personID): void
    {
        $this->db->prepare(
            'DELETE FROM `tawasulMessengerChatDraft` WHERE tawasulChatID = :chatID AND tawasulPersonID = :personID'
        )->execute(['chatID' => $chatID, 'personID' => ChatGateway::pad($personID)]);
    }

    public function reactionsForPerson(array $messageIDs, string $personID): array
    {
        if ($messageIDs === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($messageIDs), '?'));
        $stmt = $this->db->prepare(
            'SELECT tawasulChatMessageID FROM `tawasulMessengerChatReaction`
              WHERE tawasulPersonID = ? AND tawasulChatMessageID IN ('.$placeholders.')'
        );
        $stmt->execute(array_merge([ChatGateway::pad($personID)], array_values($messageIDs)));

        return array_column($stmt->fetchAll(), 'tawasulChatMessageID');
    }
}
