<?php
namespace Tos\Module\TawasulChat\Support;

/**
 * The shipped Transport: a long poll.
 *
 * The request sleeps in short slices and re-checks the database, returning as
 * soon as anything the person cares about has changed. Two details matter for
 * this to work on a shared host rather than only on a dedicated server:
 *
 *  1. The session is closed before sleeping. PHP serialises requests that share
 *     a session cookie, so a poll left holding the session would stop the user
 *     doing anything else in another tab for the length of the timeout.
 *
 *  2. The loop checks the clock rather than sleeping once. A single sleep would
 *     make delivery latency equal to the whole timeout, which defeats the point.
 *
 * Polling the database costs one indexed range scan per slice. The slice length
 * trades that cost against latency; at 400ms a two-second wait is about five
 * scans, which is far cheaper than the request that eventually returns the data.
 */
class LongPollTransport implements Transport
{
    /** Seconds between database checks while waiting. */
    const POLL_INTERVAL = 0.4;

    /** How long a typing indicator stays meaningful. */
    const TYPING_TTL = 6;

    /** @var \PDO */
    private $db;

    /** @var Settings */
    private $settings;

    /** @var Clock */
    private $clock;

    public function __construct(\PDO $db, Settings $settings, Clock $clock)
    {
        $this->db = $db;
        $this->settings = $settings;
        $this->clock = $clock;
    }

    public function wait(string $personID, string $since, int $timeoutSeconds): ?array
    {
        $deadline = microtime(true) + $timeoutSeconds;

        // Anything that has already happened is returned immediately rather than
        // making the client wait out a timeout for a backlog it already missed.
        $first = $this->changesSince($personID, $since);
        if ($this->hasContent($first)) {
            return $first;
        }

        // Let other requests from this session through while we wait.
        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        do {
            usleep((int) (self::POLL_INTERVAL * 1000000));

            $change = $this->changesSince($personID, $since);
            if ($this->hasContent($change)) {
                return $change;
            }
        } while (microtime(true) < $deadline);

        // Nothing happened. Still return the payload, with the advanced clock:
        // the client uses serverTime to move its own cursor forward, and
        // swallowing it here would make the next poll re-scan the same window.
        return $change ?? $this->changesSince($personID, $since);
    }

    public function changesSince(string $personID, string $since): array
    {
        // Read the clock first and bound every query by it. See the interface
        // note: this ordering is what makes polling lossless.
        //
        // The clock is the database's, not PHP's. This cursor is compared
        // against columns MySQL stamped with NOW(), and on a host where PHP's
        // timezone and MySQL's disagree the difference is an hour — which would
        // either skip everything sent in that hour or replay it forever.
        $serverTime = $this->clock->now();

        $personID = str_pad($personID, 10, '0', STR_PAD_LEFT);

        $messages = $this->db->prepare(
            'SELECT m.tawasulChatMessageID, m.tawasulChatID, m.tawasulPersonID, m.type, m.status, m.content,
                    m.replyToMessageID, m.forwardedFromID, m.locationLat, m.locationLng, m.locationName,
                    m.durationSeconds, m.editedAt, m.deletedAt, m.timestampModified, m.timestampCreated,
                    p.preferredName AS senderPreferredName, p.surname AS senderSurname
               FROM `tawasulMessengerChatMessage` m
               JOIN `tawasulMessengerChatParticipant` cp ON (cp.tawasulChatID = m.tawasulChatID
                                                     AND cp.tawasulPersonID = :personID
                                                     AND cp.leftAt IS NULL)
               JOIN `tawasulPerson` p ON (p.tawasulPersonID = m.tawasulPersonID)
              WHERE m.timestampCreated > :since AND m.timestampCreated <= :now
                AND NOT EXISTS (SELECT 1 FROM `tawasulMessengerChatMessageHidden` h
                                 WHERE h.tawasulChatMessageID = m.tawasulChatMessageID
                                   AND h.tawasulPersonID = :personID)
              ORDER BY m.tawasulChatMessageID
              LIMIT 200'
        );
        $messages->execute(['personID' => $personID, 'since' => $since, 'now' => $serverTime]);
        $messageRows = $messages->fetchAll();

        // A message can be edited or deleted without changing timestampCreated,
        // so those edits are tracked off timestampModified as well.
        $edits = $this->db->prepare(
            'SELECT m.tawasulChatMessageID, m.tawasulChatID, m.tawasulPersonID, m.type, m.status, m.content,
                    m.replyToMessageID, m.forwardedFromID, m.locationLat, m.locationLng, m.locationName,
                    m.durationSeconds, m.editedAt, m.deletedAt, m.timestampModified, m.timestampCreated,
                    p.preferredName AS senderPreferredName, p.surname AS senderSurname
               FROM `tawasulMessengerChatMessage` m
               JOIN `tawasulMessengerChatParticipant` cp ON (cp.tawasulChatID = m.tawasulChatID
                                                     AND cp.tawasulPersonID = :personID
                                                     AND cp.leftAt IS NULL)
               JOIN `tawasulPerson` p ON (p.tawasulPersonID = m.tawasulPersonID)
              WHERE m.timestampModified > :since AND m.timestampModified <= :now
                AND NOT (m.timestampCreated > :since AND m.timestampCreated <= :now)
                AND NOT EXISTS (SELECT 1 FROM `tawasulMessengerChatMessageHidden` h
                                 WHERE h.tawasulChatMessageID = m.tawasulChatMessageID
                                   AND h.tawasulPersonID = :personID)
              ORDER BY m.tawasulChatMessageID
              LIMIT 200'
        );
        $edits->execute(['personID' => $personID, 'since' => $since, 'now' => $serverTime]);
        $messageRows = array_merge($messageRows, $edits->fetchAll());

        if ($messageRows !== []) {
            $ids = array_column($messageRows, 'tawasulChatMessageID');
            $attachments = $this->attachmentsFor($ids);
            $reactions = $this->reactionsFor($ids);
            foreach ($messageRows as &$row) {
                $id = $row['tawasulChatMessageID'];
                $row['attachments'] = $attachments[$id] ?? [];
                $row['reactions'] = $reactions[$id] ?? [];
            }
            unset($row);
        }

        // Tick updates on the person's own messages, so the sender sees their
        // messages turn from one grey tick to two blue ones.
        $receipts = $this->db->prepare(
            'SELECT r.tawasulChatReceiptID, r.tawasulChatMessageID, r.tawasulPersonID,
                    r.deliveredAt, r.readAt, m.tawasulChatID
               FROM `tawasulMessengerChatReceipt` r
               JOIN `tawasulMessengerChatMessage` m ON (m.tawasulChatMessageID = r.tawasulChatMessageID)
              WHERE m.tawasulPersonID = :personID
                AND ((r.deliveredAt > :since AND r.deliveredAt <= :now)
                  OR (r.readAt > :since AND r.readAt <= :now))
              ORDER BY r.tawasulChatReceiptID
              LIMIT 200'
        );
        $receipts->execute(['personID' => $personID, 'since' => $since, 'now' => $serverTime]);

        // Presence and typing for the people the person actually shares a chat
        // with. Reporting the presence of the whole school would be both useless
        // and a slow query.
        $presence = $this->db->prepare(
            'SELECT pr.tawasulPersonID, pr.status, pr.lastSeenAt, pr.lastPingAt,
                    p.preferredName, p.surname, p.image_240
               FROM `tawasulMessengerChatPresence` pr
               JOIN `tawasulPerson` p ON (p.tawasulPersonID = pr.tawasulPersonID)
              WHERE pr.tawasulPersonID <> :personID
                AND pr.lastPingAt > :since AND pr.lastPingAt <= :now
                AND EXISTS (SELECT 1
                              FROM `tawasulMessengerChatParticipant` mine
                              JOIN `tawasulMessengerChatParticipant` theirs
                                ON (theirs.tawasulChatID = mine.tawasulChatID AND theirs.leftAt IS NULL)
                             WHERE mine.tawasulPersonID = :personID AND mine.leftAt IS NULL
                               AND theirs.tawasulPersonID = pr.tawasulPersonID)
              ORDER BY pr.tawasulPersonID
              LIMIT 200'
        );
        $presence->execute(['personID' => $personID, 'since' => $since, 'now' => $serverTime]);

        $typing = $this->db->prepare(
            'SELECT pr.tawasulPersonID, pr.typingChatID, pr.typingAt
               FROM `tawasulMessengerChatPresence` pr
              WHERE pr.tawasulPersonID <> :personID
                AND pr.typingChatID IS NOT NULL
                AND pr.typingAt > :since AND pr.typingAt <= :now
                AND EXISTS (SELECT 1
                              FROM `tawasulMessengerChatParticipant` mine
                              JOIN `tawasulMessengerChatParticipant` theirs
                                ON (theirs.tawasulChatID = mine.tawasulChatID AND theirs.leftAt IS NULL)
                             WHERE mine.tawasulPersonID = :personID AND mine.leftAt IS NULL
                               AND theirs.tawasulPersonID = pr.tawasulPersonID)
              ORDER BY pr.tawasulPersonID
              LIMIT 50'
        );
        $typing->execute(['personID' => $personID, 'since' => $since, 'now' => $serverTime]);

        return [
            // Deliberately rewound by a second rather than handed back as read.
            //
            // The cursor is a datetime, so it has one-second resolution, and the
            // bound used by the next poll is strict: `timestampCreated > since`.
            // A message written in the same second the cursor was read therefore
            // satisfies neither bound of the window it should have been in — it
            // is neither after the cursor nor before the clock that excluded it —
            // and the client would never see it. Backing the cursor off by a
            // second re-includes that message on the next poll.
            //
            // The overlap makes the next window repeat the last second, which is
            // why the client treats everything here as at-least-once: it keys
            // bubbles and receipts by id and replaces rather than appends. That
            // is a cheaper price than a message that silently never arrives.
            'serverTime' => $this->clock->ago(1),
            'messages' => $this->decorate($messageRows),
            'receipts' => $receipts->fetchAll(),
            'presence' => $presence->fetchAll(),
            'typing' => $this->expireTyping($typing->fetchAll()),
        ];
    }

    /**
     * A typing indicator older than a few seconds is stale, not news.
     *
     * Age is measured against the database clock, for the same reason the poll
     * cursor is.
     */
    private function expireTyping(array $rows): array
    {
        $cutoff = $this->clock->ago(self::TYPING_TTL);

        return array_values(array_filter($rows, fn ($row) => $row['typingAt'] >= $cutoff));
    }

    private function hasContent(array $change): bool
    {
        return $change['messages'] !== []
            || $change['receipts'] !== []
            || $change['presence'] !== []
            || $change['typing'] !== [];
    }

    /**
     * @param  array<int,string> $messageIDs
     * @return array<string,array> keyed by message ID
     */
    private function attachmentsFor(array $messageIDs): array
    {
        if ($messageIDs === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($messageIDs), '?'));
        $stmt = $this->db->prepare(
            'SELECT tawasulChatMessageID, tawasulChatAttachmentID, filePath, thumbnailPath, fileName,
                    fileMimeType, fileSize, width, height, durationSeconds
               FROM `tawasulMessengerChatAttachment`
              WHERE tawasulChatMessageID IN ('.$placeholders.')'
        );
        $stmt->execute(array_values($messageIDs));

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['tawasulChatMessageID']][] = $row;
        }

        return $out;
    }

    /** @return array<string,array> keyed by message ID */
    private function reactionsFor(array $messageIDs): array
    {
        if ($messageIDs === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($messageIDs), '?'));
        $stmt = $this->db->prepare(
            'SELECT tawasulChatMessageID, emoji, tawasulPersonID
               FROM `tawasulMessengerChatReaction`
              WHERE tawasulChatMessageID IN ('.$placeholders.')
              ORDER BY tawasulChatReactionID'
        );
        $stmt->execute(array_values($messageIDs));

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['tawasulChatMessageID']][] = $row;
        }

        return $out;
    }

    /**
     * Normalise the rows for JSON: the client compares message ids as numbers
     * and builds "time ago" labels from timestamps, and PHP would otherwise emit
     * "1" for the zerofilled person id "0000000001".
     */
    private function decorate(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['tawasulChatMessageID'] = (int) $row['tawasulChatMessageID'];
            $row['tawasulChatID'] = (int) $row['tawasulChatID'];
            $row['tawasulPersonID'] = (string) $row['tawasulPersonID'];
            $row['replyToMessageID'] = $row['replyToMessageID'] === null ? null : (int) $row['replyToMessageID'];
            $row['forwardedFromID'] = $row['forwardedFromID'] === null ? null : (int) $row['forwardedFromID'];
            foreach ($row['attachments'] ?? [] as &$attachment) {
                $attachment['tawasulChatAttachmentID'] = (int) $attachment['tawasulChatAttachmentID'];
                $attachment['fileSize'] = (int) $attachment['fileSize'];
            }
            unset($attachment);
            foreach ($row['reactions'] ?? [] as &$reaction) {
                $reaction['tawasulPersonID'] = (string) $reaction['tawasulPersonID'];
            }
            unset($reaction);
        }

        return $rows;
    }
}
