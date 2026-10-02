<?php
namespace Tos\Module\TawasulChat\Domain;

/**
 * Chats and their membership.
 *
 * Chats and participants are read together almost always — a chat is never
 * useful without knowing who is in it — so they share a gateway rather than
 * forcing every caller to join two tables itself.
 */
class ChatGateway
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function create(string $type, string $name, string $description, string $createdBy, int $disappearingMinutes = 0): int
    {
        $this->db->prepare(
            'INSERT INTO `tawasulMessengerChat` (type, name, description, createdBy, disappearingMinutes)
             VALUES (:type, :name, :description, :createdBy, :disappearingMinutes)'
        )->execute([
            'type' => $type,
            'name' => $name,
            'description' => $description,
            'createdBy' => $createdBy,
            'disappearingMinutes' => $disappearingMinutes,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function find(int $chatID): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `tawasulMessengerChat` WHERE tawasulChatID = :chatID');
        $stmt->execute(['chatID' => $chatID]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Add a participant, or revive one who had left.
     *
     * A person who leaves and is re-added must land back in the chat, so this
     * clears leftAt rather than refusing. The caller decides whether that is
     * appropriate; re-adding someone to a group is not always.
     */
    public function addParticipant(int $chatID, string $personID, string $role = 'member'): void
    {
        $personID = self::pad($personID);

        $this->db->prepare(
            'INSERT INTO `tawasulMessengerChatParticipant` (tawasulChatID, tawasulPersonID, role)
             VALUES (:chatID, :personID, :role)
             ON DUPLICATE KEY UPDATE role = VALUES(role), leftAt = NULL'
        )->execute(['chatID' => $chatID, 'personID' => $personID, 'role' => $role]);
    }

    public function removeParticipant(int $chatID, string $personID): void
    {
        // Marked rather than deleted: the unread count of the chat list depends
        // on membership history, and a hard delete would also silently drop the
        // lastRead cursor and silently re-notify everyone.
        $this->db->prepare(
            'UPDATE `tawasulMessengerChatParticipant`
                SET leftAt = NOW()
              WHERE tawasulChatID = :chatID AND tawasulPersonID = :personID'
        )->execute(['chatID' => $chatID, 'personID' => self::pad($personID)]);
    }

    public function isParticipant(int $chatID, string $personID): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM `tawasulMessengerChatParticipant`
              WHERE tawasulChatID = :chatID AND tawasulPersonID = :personID AND leftAt IS NULL'
        );
        $stmt->execute(['chatID' => $chatID, 'personID' => self::pad($personID)]);

        return $stmt->fetchColumn() !== false;
    }

    /** @return array<int,array> */
    public function participantIDs(int $chatID): array
    {
        $stmt = $this->db->prepare(
            'SELECT tawasulPersonID FROM `tawasulMessengerChatParticipant`
              WHERE tawasulChatID = :chatID AND leftAt IS NULL
              ORDER BY tawasulChatParticipantID'
        );
        $stmt->execute(['chatID' => $chatID]);

        return array_map('strval', array_column($stmt->fetchAll(), 'tawasulPersonID'));
    }

    public function participantCount(int $chatID): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `tawasulMessengerChatParticipant`
              WHERE tawasulChatID = :chatID AND leftAt IS NULL'
        );
        $stmt->execute(['chatID' => $chatID]);

        return (int) $stmt->fetchColumn();
    }

    public function roleOf(int $chatID, string $personID): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT role FROM `tawasulMessengerChatParticipant`
              WHERE tawasulChatID = :chatID AND tawasulPersonID = :personID AND leftAt IS NULL'
        );
        $stmt->execute(['chatID' => $chatID, 'personID' => self::pad($personID)]);
        $role = $stmt->fetchColumn();

        return $role === false ? null : (string) $role;
    }

    /**
     * The chat list: every chat the person is currently in, ordered the way a
     * phone orders it — pinned first, then most recent.
     *
     * The unread count is a correlated subquery rather than a stored column.
     * A stored count would have to be maintained by every path that changes
     * membership, delivery or reads, and each of those would need to remember
     * to touch it; the subquery is one index range scan on a table that is
     * small by construction.
     */
    public function listForPerson(string $personID): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.tawasulChatID, c.type, c.name, c.description, c.iconPath, c.disappearingMinutes,
                    c.lastMessageID, c.lastMessagePreview, c.timestampModified, c.timestampCreated,
                    p.pinned, p.archived, p.mutedUntil, p.notify, p.lastReadMessageID, p.role,
                    p.joinedAt,
                    (SELECT COUNT(*) FROM `tawasulMessengerChatMessage` m
                      WHERE m.tawasulChatID = c.tawasulChatID
                        AND m.tawasulChatMessageID > COALESCE(p.lastReadMessageID, 0)
                        AND m.tawasulPersonID <> p.tawasulPersonID) AS unreadCount,
                    (p.mutedUntil IS NOT NULL AND p.mutedUntil > NOW()) AS isMuted
               FROM `tawasulMessengerChat` c
               JOIN `tawasulMessengerChatParticipant` p
                 ON (p.tawasulChatID = c.tawasulChatID AND p.tawasulPersonID = :personID)
              WHERE p.leftAt IS NULL AND c.active = \'Y\'
              ORDER BY p.pinned DESC, c.timestampModified DESC, c.tawasulChatID DESC'
        );
        $stmt->execute(['personID' => self::pad($personID)]);

        return $stmt->fetchAll();
    }

    /** Unread messages across every chat, for the navigation badge. */
    public function totalUnread(string $personID): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `tawasulMessengerChatMessage` m
               JOIN `tawasulMessengerChatParticipant` p ON (p.tawasulChatID = m.tawasulChatID AND p.tawasulPersonID = :personID)
              WHERE p.leftAt IS NULL
                AND m.tawasulChatMessageID > COALESCE(p.lastReadMessageID, 0)
                AND m.tawasulPersonID <> p.tawasulPersonID'
        );
        $stmt->execute(['personID' => self::pad($personID)]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Find the existing one-to-one chat between two people.
     *
     * The two-person participant set is compared against the whole membership
     * rather than by hashing the pair into a column, because membership is the
     * thing that can change: a third person being added turns an individual chat
     * into something that must no longer match.
     */
    public function findIndividualChat(string $personA, string $personB): ?int
    {
        $personA = self::pad($personA);
        $personB = self::pad($personB);

        $stmt = $this->db->prepare(
            'SELECT c.tawasulChatID
               FROM `tawasulMessengerChat` c
              WHERE c.type = \'individual\'
                AND EXISTS (SELECT 1 FROM `tawasulMessengerChatParticipant` x
                             WHERE x.tawasulChatID = c.tawasulChatID AND x.tawasulPersonID = :personA AND x.leftAt IS NULL)
                AND EXISTS (SELECT 1 FROM `tawasulMessengerChatParticipant` y
                             WHERE y.tawasulChatID = c.tawasulChatID AND y.tawasulPersonID = :personB AND y.leftAt IS NULL)
                AND (SELECT COUNT(*) FROM `tawasulMessengerChatParticipant` z
                      WHERE z.tawasulChatID = c.tawasulChatID AND z.leftAt IS NULL) = 2
              ORDER BY c.tawasulChatID DESC
              LIMIT 1'
        );
        $stmt->execute(['personA' => $personA, 'personB' => $personB]);
        $chatID = $stmt->fetchColumn();

        return $chatID === false ? null : (int) $chatID;
    }

    /** Members with the display fields the chat header and group pages need. */
    public function participantsWithPeople(int $chatID): array
    {
        $stmt = $this->db->prepare(
            'SELECT cp.tawasulPersonID, cp.role, cp.joinedAt, cp.leftAt,
                    p.preferredName, p.surname, p.image_240, p.status, p.tawasulRoleIDPrimary,
                    r.name AS roleName, r.category AS roleCategory
               FROM `tawasulMessengerChatParticipant` cp
               JOIN `tawasulPerson` p ON (p.tawasulPersonID = cp.tawasulPersonID)
               LEFT JOIN `tawasulRole` r ON (r.tawasulRoleID = p.tawasulRoleIDPrimary)
              WHERE cp.tawasulChatID = :chatID AND cp.leftAt IS NULL
              ORDER BY FIELD(cp.role, \'owner\', \'admin\', \'member\'), p.surname, p.preferredName'
        );
        $stmt->execute(['chatID' => $chatID]);

        return $stmt->fetchAll();
    }

    public function updateMeta(int $chatID, string $name, string $description): void
    {
        $this->db->prepare(
            'UPDATE `tawasulMessengerChat` SET name = :name, description = :description WHERE tawasulChatID = :chatID'
        )->execute(['name' => $name, 'description' => $description, 'chatID' => $chatID]);
    }

    public function updateDisappearing(int $chatID, int $minutes): void
    {
        $this->db->prepare(
            'UPDATE `tawasulMessengerChat` SET disappearingMinutes = :minutes WHERE tawasulChatID = :chatID'
        )->execute(['minutes' => $minutes, 'chatID' => $chatID]);
    }

    public function setRole(int $chatID, string $personID, string $role): void
    {
        $this->db->prepare(
            'UPDATE `tawasulMessengerChatParticipant` SET role = :role
              WHERE tawasulChatID = :chatID AND tawasulPersonID = :personID'
        )->execute(['role' => $role, 'chatID' => $chatID, 'personID' => self::pad($personID)]);
    }

    /**
     * A conversation list for the forward picker.
     *
     * Archived chats are included: someone forwarding a message to a person
     * they had archived still expects that conversation to receive it, and
     * hiding it would make the message appear to go nowhere. The same list
     * doubles as the "recent chats" row above the contacts, which is what makes
     * forwarding a one-tap action rather than a search task.
     */
    public function forwardTargets(string $personID, int $limit = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.tawasulChatID, c.type, c.name, c.description, c.iconPath, p.pinned,
                    c.timestampModified,
                    (SELECT GROUP_CONCAT(cp2.tawasulPersonID ORDER BY cp2.tawasulPersonID)
                       FROM `tawasulMessengerChatParticipant` cp2
                      WHERE cp2.tawasulChatID = c.tawasulChatID AND cp2.leftAt IS NULL
                        AND cp2.tawasulPersonID <> :personID) AS memberIDs
               FROM `tawasulMessengerChat` c
               JOIN `tawasulMessengerChatParticipant` p
                 ON (p.tawasulChatID = c.tawasulChatID AND p.tawasulPersonID = :personID)
              WHERE p.leftAt IS NULL AND c.active = \'Y\'
              ORDER BY p.pinned DESC, c.timestampModified DESC, c.tawasulChatID DESC
              LIMIT '.(int) $limit
        );
        $stmt->execute(['personID' => self::pad($personID)]);

        $rows = $stmt->fetchAll();

        // One lookup for every member of every listed chat, rather than a query
        // per chat: the picker shows names, and N+1 here is a query for each of
        // the user's conversations on a screen they open often.
        $memberIDs = [];
        foreach ($rows as $row) {
            $memberIDs = array_merge($memberIDs, explode(',', (string) $row['memberIDs']));
        }
        $names = $this->namesFor($memberIDs);

        foreach ($rows as &$row) {
            $row['tawasulChatID'] = (int) $row['tawasulChatID'];
            $row['pinned'] = $row['pinned'] === 'Y';

            $members = array_values(array_filter(explode(',', (string) $row['memberIDs'])));
            $labels = [];
            foreach ($members as $memberID) {
                if (isset($names[$memberID])) {
                    $labels[] = $names[$memberID];
                }
            }

            // memberIDs is kept: the client matches contact rows against a
            // conversation to avoid offering both a person and their existing
            // one-to-one chat as separate destinations for the same forward.
            $row['title'] = $row['type'] === 'group'
                ? $row['name']
                : implode(', ', $labels);
            $row['memberIDs'] = $members;

            unset($row['name']);
        }
        unset($row);

        return $rows;
    }

    /** @param  array<int,string> $personIDs
     *  @return array<string,string> person id => display name
     */
    private function namesFor(array $personIDs): array
    {
        $personIDs = array_values(array_unique(array_map([self::class, 'pad'], array_filter(
            $personIDs,
            fn ($id) => $id !== null && trim((string) $id) !== ''
        ))));
        if ($personIDs === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($personIDs), '?'));
        $stmt = $this->db->prepare(
            'SELECT tawasulPersonID, preferredName, surname FROM `tawasulPerson`
              WHERE tawasulPersonID IN ('.$placeholders.')'
        );
        $stmt->execute($personIDs);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['tawasulPersonID']] = trim($row['preferredName'].' '.$row['surname']);
        }

        return $out;
    }

    /**
     * This person's per-conversation settings: archive, pin, mute and
     * notification level.
     *
     * Read as a set because the details panel preselects all four controls from
     * them, and a payload that carried only some of them left the notification
     * dropdown stuck on "all" however the person had set it.
     *
     * @return array{archived:string,pinned:string,mutedUntil:?string,notify:string}
     */
    public function participantFlags(int $chatID, string $personID): array
    {
        $stmt = $this->db->prepare(
            'SELECT archived, pinned, mutedUntil, notify
               FROM `tawasulMessengerChatParticipant`
              WHERE tawasulChatID = :chatID AND tawasulPersonID = :personID'
        );
        $stmt->execute(['chatID' => $chatID, 'personID' => self::pad($personID)]);
        $row = $stmt->fetch() ?: [];

        $mutedUntil = $row['mutedUntil'] ?? null;

        return [
            'archived'  => (string) ($row['archived'] ?? 'N'),
            'pinned'    => (string) ($row['pinned'] ?? 'N'),
            'notify'    => (string) ($row['notify'] ?? 'all'),
            // The client wants a simple yes/no, and a mute that has already
            // expired is not a mute.
            'muted'     => ($mutedUntil !== null && $mutedUntil > date('Y-m-d H:i:s')) ? 'Y' : 'N',
        ];
    }

    /** Per-person chat-list settings: archive, pin, mute and notification level. */
    public function setParticipantFlag(int $chatID, string $personID, string $column, string $value): void
    {
        $allowed = ['archived', 'pinned', 'mutedUntil', 'notify'];
        if (!in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException('Not a participant setting: '.$column);
        }

        $this->db->prepare(
            'UPDATE `tawasulMessengerChatParticipant` SET '.$column.' = :value
              WHERE tawasulChatID = :chatID AND tawasulPersonID = :personID'
        )->execute(['value' => $value, 'chatID' => $chatID, 'personID' => self::pad($personID)]);
    }

    /**
     * Bump the chat's denormalised ordering fields after a message lands.
     *
     * timestampModified is what orders the chat list and what the transport
     * watches, so it has to move on every message or the conversation will not
     * float to the top and a poll will not wake up for it.
     */
    public function touchWithMessage(int $chatID, int $messageID, string $preview): void
    {
        $this->db->prepare(
            'UPDATE `tawasulMessengerChat`
                SET lastMessageID = :messageID, lastMessagePreview = :preview, timestampModified = NOW()
              WHERE tawasulChatID = :chatID'
        )->execute(['messageID' => $messageID, 'preview' => mb_substr($preview, 0, 255), 'chatID' => $chatID]);
    }

    public static function pad(string $personID): string
    {
        return str_pad($personID, 10, '0', STR_PAD_LEFT);
    }
}
