<?php
namespace Tos\Module\TawasulChat\Service;

use Tos\Module\TawasulChat\Domain\ActivityGateway;
use Tos\Module\TawasulChat\Domain\ChatGateway;
use Tos\Module\TawasulChat\Http\ChatException;
use Tos\Module\TawasulChat\Support\Settings;

/**
 * Starting and shaping conversations.
 */
class ChatService
{
    /** @var \PDO */
    private $db;

    /** @var ChatGateway */
    private $chats;

    /** @var ActivityGateway */
    private $activity;

    /** @var Settings */
    private $settings;

    public function __construct(\PDO $db, ChatGateway $chats, ActivityGateway $activity, Settings $settings)
    {
        $this->db = $db;
        $this->chats = $chats;
        $this->activity = $activity;
        $this->settings = $settings;
    }

    /**
     * Open, or re-find, the one-to-one chat between two people.
     *
     * Reuses the existing conversation rather than making a second one: two
     * parallel threads between the same two people is the single most confusing
     * thing a chat system can do, and the lookup is a cheap indexed query.
     */
    public function openDirectChat(string $actorID, string $otherID): int
    {
        if (ChatGateway::pad($actorID) === ChatGateway::pad($otherID)) {
            throw new ChatException('You cannot start a conversation with yourself.', 'other');
        }

        $other = $this->assertContactable($otherID);

        $existing = $this->chats->findIndividualChat($actorID, $otherID);
        if ($existing !== null) {
            return $existing;
        }

        $this->db->beginTransaction();
        try {
            $chatID = $this->chats->create('individual', '', '', $actorID);
            $this->chats->addParticipant($chatID, $actorID, 'owner');
            $this->chats->addParticipant($chatID, $other['tawasulPersonID'], 'member');
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $chatID;
    }

    /**
     * Create a group.
     *
     * The people are validated before anything is written, so a group is never
     * created in a state where somebody in it cannot receive messages.
     */
    public function createGroup(string $actorID, string $name, array $memberIDs, int $disappearingMinutes = 0): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new ChatException('Give the group a name.', 'name');
        }
        if (mb_strlen($name) > 100) {
            throw new ChatException('That group name is too long.', 'name');
        }

        $memberIDs = array_values(array_unique(array_map(
            'strval',
            array_filter($memberIDs, fn ($id) => trim((string) $id) !== '' && ChatGateway::pad((string) $id) !== ChatGateway::pad($actorID))
        )));

        $max = $this->settings->getInt('maxGroupSize', 2, 1000);
        if (count($memberIDs) + 1 > $max) {
            throw new ChatException(sprintf('A group can have at most %d people.', $max), 'members');
        }

        $people = array_map(fn ($id) => $this->assertContactable($id), $memberIDs);

        $this->db->beginTransaction();
        try {
            $chatID = $this->chats->create('group', $name, '', $actorID, $this->assertDisappearing($disappearingMinutes));
            $this->chats->addParticipant($chatID, $actorID, 'owner');
            foreach ($people as $person) {
                $this->chats->addParticipant($chatID, $person['tawasulPersonID'], 'member');
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $chatID;
    }

    /** People who may be added to a conversation, as chat-shaped rows. */
    public function addMembers(int $chatID, string $actorID, array $personIDs): array
    {
        $chat = $this->requireGroup($chatID);
        $this->requireAdmin($chatID, $actorID);

        $max = $this->settings->getInt('maxGroupSize', 2, 1000);
        $added = [];

        foreach ($personIDs as $personID) {
            $personID = (string) $personID;
            if (trim($personID) === '') {
                continue;
            }
            if ($this->chats->isParticipant($chatID, $personID)) {
                continue;
            }
            if ($this->chats->participantCount($chatID) >= $max) {
                throw new ChatException(sprintf('A group can have at most %d people.', $max));
            }

            $person = $this->assertContactable($personID);
            $this->chats->addParticipant($chatID, $person['tawasulPersonID'], 'member');
            $added[] = $person['tawasulPersonID'];
        }

        return $added;
    }

    /**
     * Remove someone, or leave.
     *
     * Leaving is always allowed, including as the last person in the group:
     * someone who wants out of a conversation must never be trapped in it.
     *
     * Removing somebody else is admin-only, and refused in two cases. The second
     * is the one that actually strands a group: the owner is the only role that
     * can promote an admin or rename the chat, so a group whose owner has been
     * removed can never be brought under control again — by anybody, ever. The
     * first is the same rule applied to an admin removing the last other member
     * of a two-person group, which would leave a conversation with one person in
     * it and nothing to do with.
     */
    public function removeMember(int $chatID, string $actorID, string $targetID): void
    {
        $this->requireGroup($chatID);

        // Membership first: removing somebody who is not in the group is a
        // no-op, and answering it with a refusal would describe a request that
        // asked for nothing.
        if (!$this->chats->isParticipant($chatID, $targetID)) {
            return;
        }

        $isSelf = ChatGateway::pad($actorID) === ChatGateway::pad($targetID);
        if (!$isSelf) {
            $this->requireAdmin($chatID, $actorID);

            if ($this->chats->participantCount($chatID) <= 1) {
                throw new ChatException('A group must keep at least one person.');
            }

            if ($this->chats->roleOf($chatID, $targetID) === 'owner') {
                throw new ChatException('The group owner cannot be removed. Leave the group instead.');
            }
        }

        $this->chats->removeParticipant($chatID, $targetID);
    }

    public function renameGroup(int $chatID, string $actorID, string $name, string $description = ''): void
    {
        $this->requireGroup($chatID);
        $this->requireAdmin($chatID, $actorID);

        $name = trim($name);
        if ($name === '') {
            throw new ChatException('Give the group a name.', 'name');
        }
        if (mb_strlen($name) > 100) {
            throw new ChatException('That group name is too long.', 'name');
        }

        $this->chats->updateMeta($chatID, $name, mb_substr(trim($description), 0, 255));
    }

    /**
     * Turn disappearing messages on or off.
     *
     * The window is per chat and applies to everything already in it as well as
     * to what arrives later, which is what people expect when they set a timer.
     */
    public function setDisappearing(int $chatID, string $actorID, int $minutes): void
    {
        $this->requireGroup($chatID);
        $this->requireAdmin($chatID, $actorID);

        $this->chats->updateDisappearing($chatID, $this->assertDisappearing($minutes));
    }

    public function promote(int $chatID, string $actorID, string $targetID, string $role): void
    {
        $this->requireGroup($chatID);

        $actorRole = $this->chats->roleOf($chatID, $actorID);
        if ($actorRole !== 'owner') {
            throw new ChatException('Only the group owner can change roles.', 'role');
        }
        if (!in_array($role, ['admin', 'member'], true)) {
            throw new ChatException('Unknown role.', 'role');
        }
        if (!$this->chats->isParticipant($chatID, $targetID)) {
            throw new ChatException('That person is not in this group.');
        }

        $this->chats->setRole($chatID, $targetID, $role);
    }

    /**
     * The chat list, prepared for the client: unread totals, per-chat unread,
     * and who else is present.
     */
    public function inbox(string $personID): array
    {
        $chats = $this->chats->listForPerson($personID);
        $timeout = $this->settings->getInt('presenceTimeoutSeconds', 15, 3600);

        $peopleToCheck = [];
        foreach ($chats as $chat) {
            if ($chat['type'] === 'individual') {
                $others = array_diff($this->chats->participantIDs((int) $chat['tawasulChatID']), [ChatGateway::pad($personID)]);
                $peopleToCheck = array_merge($peopleToCheck, $others);
            }
        }
        $presence = $this->activity->presenceFor($peopleToCheck, $timeout);
        $presenceByPerson = [];
        foreach ($presence as $row) {
            $presenceByPerson[(string) $row['tawasulPersonID']] = $row;
        }

        $out = [];
        foreach ($chats as $chat) {
            $chatID = (int) $chat['tawasulChatID'];
            $chat['tawasulChatID'] = $chatID;
            $chat['unreadCount'] = (int) $chat['unreadCount'];
            $chat['lastMessageID'] = $chat['lastMessageID'] === null ? null : (int) $chat['lastMessageID'];
            $chat['lastReadMessageID'] = $chat['lastReadMessageID'] === null ? null : (int) $chat['lastReadMessageID'];
            $chat['disappearingMinutes'] = (int) $chat['disappearingMinutes'];
            $chat['muted'] = (bool) $chat['isMuted'];

            if ($chat['type'] === 'individual') {
                $others = array_values(array_diff($this->chats->participantIDs($chatID), [ChatGateway::pad($personID)]));
                $chat['otherPerson'] = $others === [] ? null : ($presenceByPerson[$others[0]] ?? ['tawasulPersonID' => $others[0]]);
                $chat['title'] = $this->personTitle($chat['otherPerson']);
                $chat['avatar'] = $chat['otherPerson']['image_240'] ?? null;
            } else {
                $chat['title'] = $chat['name'];
                $chat['avatar'] = $chat['iconPath'];
                $chat['otherPerson'] = null;
            }

            unset($chat['notify'], $chat['joinedAt']);
            $out[] = $chat;
        }

        return [
            'chats' => $out,
            'totalUnread' => $this->chats->totalUnread($personID),
        ];
    }

    /** @return array the chat header: people, roles and presence. */
    public function header(int $chatID, string $personID): array
    {
        if (!$this->chats->isParticipant($chatID, $personID)) {
            throw ChatException::notAMember();
        }

        $chat = $this->chats->find($chatID);
        if ($chat === null) {
            throw ChatException::chatNotFound();
        }

        $timeout = $this->settings->getInt('presenceTimeoutSeconds', 15, 3600);
        $participants = $this->chats->participantsWithPeople($chatID);

        $presence = $this->activity->presenceFor(
            array_column($participants, 'tawasulPersonID'),
            $timeout
        );
        $presenceByPerson = [];
        foreach ($presence as $row) {
            $presenceByPerson[(string) $row['tawasulPersonID']] = $row;
        }

        $me = ChatGateway::pad($personID);
        foreach ($participants as &$person) {
            $person['tawasulPersonID'] = (string) $person['tawasulPersonID'];
            $person['isMe'] = $person['tawasulPersonID'] === $me;
            $presence = $presenceByPerson[$person['tawasulPersonID']] ?? ['status' => 'offline', 'lastSeenAt' => null];
            $person['status'] = $presence['status'];
            $person['lastSeenAt'] = $presence['lastSeenAt'] ?? null;
            $person['title'] = $this->personTitle($person);
        }
        unset($person);

        $title = $chat['type'] === 'group'
            ? $chat['name']
            : $this->personTitle($participants[0] ?? []);

        return [
            'tawasulChatID' => (int) $chat['tawasulChatID'],
            'type' => $chat['type'],
            'name' => $chat['name'],
            'description' => $chat['description'],
            'title' => $title,
            'disappearingMinutes' => (int) $chat['disappearingMinutes'],
            'myRole' => $this->chats->roleOf($chatID, $personID),
            'participantCount' => count($participants),
            'participants' => $participants,
            'typing' => $this->activity->typingIn($chatID, Presence::TYPING_TTL),
            'settings' => [
                'readReceipts' => $this->settings->isOn('readReceiptsEnabled'),
                'typingIndicator' => $this->settings->isOn('typingIndicatorEnabled'),
                'voiceNotes' => $this->settings->isOn('voiceNotesEnabled'),
                'editing' => $this->settings->isOn('editingEnabled'),
                'unreadBadge' => $this->settings->isOn('unreadBadgeEnabled'),
            ],
        ];
    }

    /** Conversations a message can be forwarded to, ready for the picker.
     *
     * @return array<int,array{chatID:int,type:string,title:string,avatar:?string,pinned:bool,memberIDs:array<int,string>}>
     */
    public function forwardTargets(string $personID): array
    {
        $rows = $this->chats->forwardTargets($personID);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'chatID' => $row['tawasulChatID'],
                'type' => $row['type'],
                'title' => $row['title'],
                'avatar' => $row['type'] === 'group' ? $row['iconPath'] : null,
                'pinned' => $row['pinned'],
                'memberIDs' => $row['memberIDs'],
            ];
        }

        return $out;
    }

    /** @return array rows for the people picker */
    public function contacts(string $personID, string $search = '', int $limit = 50): array
    {
        $rows = $this->activity->contactsFor($personID, trim($search), $limit);

        foreach ($rows as &$row) {
            $row['tawasulPersonID'] = (string) $row['tawasulPersonID'];
            $row['title'] = trim($row['preferredName'].' '.$row['surname']);
        }
        unset($row);

        return $rows;
    }

    private function personTitle(array $person): string
    {
        if ($person === []) {
            return '';
        }

        return trim(($person['preferredName'] ?? '').' '.($person['surname'] ?? ''));
    }

    private function requireGroup(int $chatID): array
    {
        $chat = $this->chats->find($chatID);
        if ($chat === null) {
            throw ChatException::chatNotFound();
        }
        if ($chat['type'] !== 'group') {
            throw new ChatException('That conversation is not a group.', 'tawasulChatID');
        }

        return $chat;
    }

    /** Owners and admins may change a group; members may not. */
    private function requireAdmin(int $chatID, string $personID): void
    {
        $role = $this->chats->roleOf($chatID, $personID);
        if ($role !== 'owner' && $role !== 'admin') {
            throw new ChatException('Only a group admin can do that.');
        }
    }

    /**
     * A person may only be messaged if they are active and have a role.
     */
    private function assertContactable(string $personID): array
    {
        $stmt = $this->db->prepare(
            'SELECT tawasulPersonID, preferredName, surname FROM `tawasulPerson`
              WHERE tawasulPersonID = :personID AND status = \'Full\' AND tawasulRoleIDPrimary IS NOT NULL'
        );
        $stmt->execute(['personID' => ChatGateway::pad($personID)]);
        $person = $stmt->fetch();

        if ($person === false) {
            throw new ChatException('That person cannot be messaged.', 'other');
        }

        return $person;
    }

    /** The windows a school would plausibly want; 0 turns it off. */
    public const DISAPPEARING_WINDOWS = [0, 60, 3600, 14400, 86400, 604800];

    /**
     * Check a disappearing-message window.
     *
     * Rejects rather than clamping to 0. Silently turning the feature off when
     * asked for an unlisted window would leave an administrator believing
     * messages were still disappearing when they were not — and that is the
     * opposite of the promise a disappearing timer makes.
     */
    private function assertDisappearing(int $minutes): int
    {
        if (!in_array($minutes, self::DISAPPEARING_WINDOWS, true)) {
            throw new ChatException(
                __('Choose one of the offered time limits.'),
                'disappearingMinutes'
            );
        }

        return $minutes;
    }
}
