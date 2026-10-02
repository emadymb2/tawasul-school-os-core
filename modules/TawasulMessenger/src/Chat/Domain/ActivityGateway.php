<?php
namespace Tos\Module\TawasulChat\Domain;

/**
 * Read receipts and presence.
 *
 * Both answer "what has happened to a message" and "is this person around",
 * which is why they share a gateway: every presence refresh happens as part of
 * marking messages delivered, and separating them would mean two objects
 * carrying the same person ID around.
 */
class ActivityGateway
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Open a receipt row for every recipient of a message.
     *
     * The sender is excluded: their own read is implicit, and a row saying so
     * would make "everyone has read this" always true.
     */
    public function openReceipts(int $messageID, string $senderID, array $recipientIDs): void
    {
        $senderID = ChatGateway::pad($senderID);

        $stmt = $this->db->prepare(
            'INSERT INTO `tawasulMessengerChatReceipt` (tawasulChatMessageID, tawasulPersonID) VALUES (:messageID, :personID)
             ON DUPLICATE KEY UPDATE tawasulChatMessageID = tawasulChatMessageID'
        );

        foreach ($recipientIDs as $personID) {
            $personID = ChatGateway::pad($personID);
            if ($personID === $senderID) {
                continue;
            }
            $stmt->execute(['messageID' => $messageID, 'personID' => $personID]);
        }
    }

    /** Mark everything up to $messageID delivered for one recipient. */
    public function markDelivered(string $personID, int $chatID, int $messageID): void
    {
        $personID = ChatGateway::pad($personID);

        $this->db->prepare(
            'UPDATE `tawasulMessengerChatReceipt` r
               JOIN `tawasulMessengerChatMessage` m ON (m.tawasulChatMessageID = r.tawasulChatMessageID)
                SET r.deliveredAt = NOW()
             WHERE r.tawasulPersonID = :personID
               AND m.tawasulChatID = :chatID
               AND r.tawasulChatMessageID <= :messageID
               AND r.deliveredAt IS NULL'
        )->execute(['personID' => $personID, 'chatID' => $chatID, 'messageID' => $messageID]);

        $this->db->prepare(
            'UPDATE `tawasulMessengerChatParticipant`
                SET lastDeliveredMessageID = :messageID, lastDeliveredTimestamp = NOW()
              WHERE tawasulChatID = :chatID AND tawasulPersonID = :personID'
        )->execute(['messageID' => $messageID, 'chatID' => $chatID, 'personID' => $personID]);
    }

    /** Mark everything up to $messageID read for one recipient. */
    public function markRead(string $personID, int $chatID, int $messageID): void
    {
        $personID = ChatGateway::pad($personID);

        $this->db->prepare(
            'UPDATE `tawasulMessengerChatReceipt` r
               JOIN `tawasulMessengerChatMessage` m ON (m.tawasulChatMessageID = r.tawasulChatMessageID)
                SET r.deliveredAt = COALESCE(r.deliveredAt, NOW()), r.readAt = NOW()
             WHERE r.tawasulPersonID = :personID
               AND m.tawasulChatID = :chatID
               AND r.tawasulChatMessageID <= :messageID
               AND r.readAt IS NULL'
        )->execute(['personID' => $personID, 'chatID' => $chatID, 'messageID' => $messageID]);

        // Advancing lastReadMessageID is what the chat list's unread count is
        // built from, so it has to move even when no receipt rows changed.
        $this->db->prepare(
            'UPDATE `tawasulMessengerChatParticipant`
                SET lastReadMessageID = :messageID, lastReadTimestamp = NOW(),
                    lastDeliveredMessageID = GREATEST(COALESCE(lastDeliveredMessageID, 0), :messageID),
                    lastDeliveredTimestamp = NOW()
              WHERE tawasulChatID = :chatID AND tawasulPersonID = :personID'
        )->execute(['messageID' => $messageID, 'chatID' => $chatID, 'personID' => $personID]);
    }

    /** How each recipient's tick stands on one message, for the sender's bubble. */
    public function receiptSummary(int $messageID): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.tawasulPersonID, r.deliveredAt, r.readAt,
                    p.preferredName, p.surname
               FROM `tawasulMessengerChatReceipt` r
               JOIN `tawasulPerson` p ON (p.tawasulPersonID = r.tawasulPersonID)
              WHERE r.tawasulChatMessageID = :messageID'
        );
        $stmt->execute(['messageID' => $messageID]);

        return $stmt->fetchAll();
    }

    /**
     * Everyone the message went to, and what state their copy is in.
     *
     * Left-joined from the conversation's membership rather than from the
     * receipts, so somebody who joined after the message was sent still appears:
     * they did not receive it, and omitting them would make the screen silently
     * under-report a message that really did not reach everyone. The author is
     * excluded, because they are not a recipient of their own message and
     * counting them would make "read by 2" mean two people plus themselves.
     */
    public function receiptDetail(int $messageID, int $chatID, string $authorID): array
    {
        $stmt = $this->db->prepare(
            'SELECT cp.tawasulPersonID, cp.role, cp.joinedAt,
                    p.preferredName, p.surname, p.image_240,
                    r.deliveredAt, r.readAt
               FROM `tawasulMessengerChatParticipant` cp
               JOIN `tawasulPerson` p ON (p.tawasulPersonID = cp.tawasulPersonID)
               LEFT JOIN `tawasulMessengerChatReceipt` r
                 ON (r.tawasulChatMessageID = :messageID AND r.tawasulPersonID = cp.tawasulPersonID)
              WHERE cp.tawasulChatID = :chatID AND cp.leftAt IS NULL
                AND cp.tawasulPersonID <> :authorID
              ORDER BY FIELD(cp.role, \'owner\', \'admin\', \'member\'), p.surname, p.preferredName'
        );
        $stmt->execute([
            'messageID' => $messageID,
            'chatID' => $chatID,
            'authorID' => ChatGateway::pad($authorID),
        ]);

        $rows = $stmt->fetchAll();

        $delivered = 0;
        $read = 0;
        foreach ($rows as $row) {
            if ($row['deliveredAt'] !== null) {
                $delivered++;
            }
            if ($row['readAt'] !== null) {
                $read++;
            }
        }

        return [
            'recipients' => $rows,
            'deliveredCount' => $delivered,
            'readCount' => $read,
        ];
    }

    /**
     * Record that a person is present, and when they were last around.
     *
     * Presence is derived from the freshness of lastPingAt rather than stored as
     * a flag that something has to clear, so a browser closed without warning
     * still ages out instead of leaving a permanent ghost online.
     */
    public function ping(string $personID, string $status): void
    {
        $this->db->prepare(
            'INSERT INTO `tawasulMessengerChatPresence` (tawasulPersonID, status, lastSeenAt, lastPingAt)
             VALUES (:personID, :status, NOW(), NOW())
             ON DUPLICATE KEY UPDATE status = VALUES(status), lastSeenAt = VALUES(lastSeenAt), lastPingAt = VALUES(lastPingAt)'
        )->execute(['personID' => ChatGateway::pad($personID), 'status' => $status]);
    }

    public function setTyping(string $personID, ?int $chatID): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `tawasulMessengerChatPresence`
                SET typingChatID = :chatID, typingAt = IF(:chatID IS NULL, NULL, NOW())
              WHERE tawasulPersonID = :personID'
        );
        $stmt->execute(['chatID' => $chatID, 'personID' => ChatGateway::pad($personID)]);
    }

    /** Presence for one group of people, with stale rows reported as offline. */
    public function presenceFor(array $personIDs, int $timeoutSeconds): array
    {
        $personIDs = array_values(array_unique(array_map(
            fn ($id) => ChatGateway::pad((string) $id),
            array_filter($personIDs, fn ($id) => $id !== null && $id !== '')
        )));
        if ($personIDs === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($personIDs), '?'));
        $stmt = $this->db->prepare(
            'SELECT pr.tawasulPersonID, pr.status, pr.lastSeenAt, pr.lastPingAt,
                    p.preferredName, p.surname, p.image_240
               FROM `tawasulMessengerChatPresence` pr
               JOIN `tawasulPerson` p ON (p.tawasulPersonID = pr.tawasulPersonID)
              WHERE pr.tawasulPersonID IN ('.$placeholders.')
                AND pr.lastPingAt >= DATE_SUB(NOW(), INTERVAL '.(int) $timeoutSeconds.' SECOND)'
        );
        $stmt->execute($personIDs);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            // The freshness test is in the query above: a stored "online" whose
            // ping has gone stale is not someone who is here, and doing that
            // comparison in PHP would mean trusting two clocks to agree.
            $out[] = [
                'tawasulPersonID' => (string) $row['tawasulPersonID'],
                'status' => $row['status'],
                'lastSeenAt' => $row['lastSeenAt'],
                'preferredName' => $row['preferredName'],
                'surname' => $row['surname'],
                'image_240' => $row['image_240'],
            ];
        }

        return $out;
    }

    /** Who is typing in a given chat right now. */
    public function typingIn(int $chatID, int $withinSeconds = 6): array
    {
        $stmt = $this->db->prepare(
            'SELECT pr.tawasulPersonID, pr.typingAt, p.preferredName, p.surname
               FROM `tawasulMessengerChatPresence` pr
               JOIN `tawasulPerson` p ON (p.tawasulPersonID = pr.tawasulPersonID)
               JOIN `tawasulMessengerChatParticipant` cp
                 ON (cp.tawasulChatID = pr.typingChatID AND cp.tawasulPersonID = pr.tawasulPersonID)
              WHERE pr.typingChatID = :chatID AND cp.leftAt IS NULL
                AND pr.typingAt IS NOT NULL
                AND pr.typingAt >= DATE_SUB(NOW(), INTERVAL '.(int) $withinSeconds.' SECOND)'
        );
        $stmt->execute(['chatID' => $chatID]);

        return $stmt->fetchAll();
    }

    /**
     * People a given person can start a chat with.
     *
     * Restricted to status 'Full' so the picker does not offer someone who has
     * left or is still pending approval, and joined against a primary role so a
     * person with no active role is not offered.
     */
    public function contactsFor(string $personID, string $search = '', int $limit = 50): array
    {
        $params = ['personID' => ChatGateway::pad($personID)];
        $where = "p.tawasulPersonID <> :personID AND p.status = 'Full' AND p.tawasulRoleIDPrimary IS NOT NULL";

        if ($search !== '') {
            $where .= ' AND (p.preferredName LIKE :search OR p.surname LIKE :search OR CONCAT(p.preferredName, " ", p.surname) LIKE :search)';
            $params['search'] = '%'.$search.'%';
        }

        $stmt = $this->db->prepare(
            'SELECT p.tawasulPersonID, p.preferredName, p.surname, p.image_240,
                    r.name AS roleName, r.category AS roleCategory
               FROM `tawasulPerson` p
               LEFT JOIN `tawasulRole` r ON (r.tawasulRoleID = p.tawasulRoleIDPrimary)
              WHERE '.$where.'
              ORDER BY p.surname, p.preferredName
              LIMIT '.(int) $limit
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
