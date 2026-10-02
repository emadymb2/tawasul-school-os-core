<?php
namespace Tos\Module\TawasulChat\Service;

use Tos\Module\TawasulChat\Domain\ActivityGateway;
use Tos\Module\TawasulChat\Domain\ChatGateway;
use Tos\Module\TawasulChat\Domain\MessageGateway;
use Tos\Module\TawasulChat\Http\ChatException;
use Tos\Module\TawasulChat\Support\AttachmentStore;
use Tos\Module\TawasulChat\Support\Clock;
use Tos\Module\TawasulChat\Support\Settings;

/**
 * Sending, changing and receiving messages.
 *
 * Every method here takes the acting person as its first argument and checks
 * that they may act, because the transport and the HTTP layer are not the only
 * callers: housekeeping, forwarding and the group pages all come through here.
 * A check that only existed in the AJAX endpoint would be a check that could be
 * skipped by the next caller written.
 */
class MessageService
{
    /** Longest a single message may be. Long enough for a letter, short enough
     *  that the chat list preview column stays useful. */
    const MAX_CONTENT_LENGTH = 4096;

    /** A quote is a preview, not a copy of the whole conversation. */
    const MAX_QUOTE_LENGTH = 200;

    /** How many messages may be pinned to one conversation at a time. */
    const MAX_PINS = 3;

    /** How often a message is offered as the WhatsApp-style quick reaction. */
    const QUICK_REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

    /** @var \PDO */
    private $db;

    /** @var ChatGateway */
    private $chats;

    /** @var MessageGateway */
    private $messages;

    /** @var ActivityGateway */
    private $activity;

    /** @var Settings */
    private $settings;

    /** @var AttachmentStore */
    private $store;

    /** @var Clock */
    private $clock;

    public function __construct(
        \PDO $db,
        ChatGateway $chats,
        MessageGateway $messages,
        ActivityGateway $activity,
        Settings $settings,
        AttachmentStore $store,
        Clock $clock
    ) {
        $this->db = $db;
        $this->clock = $clock;
        $this->chats = $chats;
        $this->messages = $messages;
        $this->activity = $activity;
        $this->settings = $settings;
        $this->store = $store;
    }

    /**
     * Send a message.
     *
     * The whole send is one transaction. Without it, a message that lands
     * without its receipts would show a permanently grey tick to the sender,
     * and a chat list entry that never moves to the top.
     *
     * @param  array $payload type, content, replyToMessageID, attachment,
     *                         location, durationSeconds, waveform
     * @return array the stored message, as the client needs it back
     */
    public function send(int $chatID, string $personID, array $payload): array
    {
        $chat = $this->requireChat($chatID);
        if (!$this->chats->isParticipant($chatID, $personID)) {
            throw ChatException::notAMember();
        }

        $type = $this->normaliseType($payload['type'] ?? 'text');
        $content = trim((string) ($payload['content'] ?? ''));
        $attachments = $payload['attachments'] ?? [];

        if ($type === 'text' && $content === '') {
            throw new ChatException('A message cannot be empty.', 'content');
        }
        if ($type !== 'text' && $content !== '') {
            $content = mb_substr($content, 0, self::MAX_CONTENT_LENGTH);
        }
        if (mb_strlen($content) > self::MAX_CONTENT_LENGTH) {
            throw new ChatException('That message is too long.', 'content');
        }

        // A reply may only quote a message in the same conversation. Without this
        // check, a person could quote any message ID in the system into a chat
        // they are in and read it.
        $replyTo = null;
        if (!empty($payload['replyToMessageID'])) {
            $replyTo = (int) $payload['replyToMessageID'];
            if (!$this->messages->belongsToChat($replyTo, $chatID)) {
                throw new ChatException('The message you are replying to is not in this conversation.', 'replyToMessageID');
            }
        }

        if ($type === 'location' && $payload['locationLat'] === null) {
            throw new ChatException('That location could not be read.', 'location');
        }

        $this->db->beginTransaction();
        try {
            $messageID = $this->messages->insert([
                'tawasulChatID' => $chatID,
                'tawasulPersonID' => $personID,
                'type' => $type,
                'status' => 'sent',
                'content' => $content === '' ? null : $content,
                'replyToMessageID' => $replyTo,
                'forwardedFromID' => $payload['forwardedFromID'] ?? null,
                'relatedPersonIDs' => $payload['relatedPersonIDs'] ?? null,
                'locationLat' => $payload['locationLat'] ?? null,
                'locationLng' => $payload['locationLng'] ?? null,
                'locationName' => $payload['locationName'] ?? null,
                'durationSeconds' => $payload['durationSeconds'] ?? null,
                'waveform' => $payload['waveform'] ?? null,
            ]);

            foreach ($attachments as $file) {
                $this->messages->addAttachment($messageID, $file, $personID);
            }

            $this->activity->openReceipts($messageID, $personID, $this->chats->participantIDs($chatID));

            $this->chats->touchWithMessage($chatID, $messageID, $this->previewFor($type, $content, $attachments));

            // The draft has become a message. Leaving it behind would put the
            // sent text back in the composer the next time the chat is opened.
            $this->messages->clearDraft($chatID, $personID);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->messageForClient($messageID, $personID);
    }

    /**
     * Copy an existing message into another conversation.
     *
     * The original attachments are referenced rather than re-uploaded: the file
     * is already on disk, and duplicating it on every forward would let one
     * small document consume the server through repeated forwarding.
     */
    public function forward(int $messageID, int $targetChatID, string $personID, ?string $note = null): array
    {
        if (!$this->chats->isParticipant($targetChatID, $personID)) {
            throw ChatException::notAMember();
        }

        $source = $this->readableMessage($messageID, $personID);

        return $this->copyOf($source, $targetChatID, $personID, $note);
    }

    /**
     * Copy one message into several conversations at once.
     *
     * The source is resolved once for the whole batch. forward() re-checks it per
     * call, so a picker sending to five chats would scan every conversation the
     * person is in five times over — and that scan is exactly what stands between
     * a person and forwarding a message out of a conversation they were removed
     * from, so it is worth doing once rather than repeatedly.
     *
     * @param  array<int,int|string> $targetChatIDs
     * @return array<int,array> the messages created, in the order asked for
     */
    public function forwardTo(int $messageID, array $targetChatIDs, string $personID, ?string $note = null): array
    {
        $targets = [];
        foreach ($targetChatIDs as $chatID) {
            $chatID = (int) $chatID;
            if ($chatID > 0 && !in_array($chatID, $targets, true)) {
                $targets[] = $chatID;
            }
        }

        if ($targets === []) {
            throw new ChatException('Choose a conversation to forward to.');
        }

        // Every target is checked before anything is sent, so a request naming
        // one conversation the person is not in cannot deliver to the others and
        // then report a failure.
        foreach ($targets as $chatID) {
            if (!$this->chats->isParticipant($chatID, $personID)) {
                throw ChatException::notAMember();
            }
        }

        $source = $this->readableMessage($messageID, $personID);

        $sent = [];
        foreach ($targets as $chatID) {
            $sent[] = $this->copyOf($source, $chatID, $personID, $note);
        }

        return $sent;
    }

    /**
     * The message being forwarded, or a refusal.
     *
     * Reading the source at all requires being in its conversation. Without this,
     * a person who knows a message ID could forward the contents of a
     * conversation they were removed from into one they still are in.
     */
    private function readableMessage(int $messageID, string $personID): array
    {
        foreach ($this->chats->listForPerson($personID) as $chat) {
            if (!$this->messages->belongsToChat($messageID, (int) $chat['tawasulChatID'])) {
                continue;
            }

            $source = $this->messages->find($messageID);
            if ($source !== null && $source['deletedAt'] === null) {
                return $source;
            }
        }

        throw new ChatException('That message cannot be forwarded.');
    }

    /**
     * Write one resolved source message into a target conversation.
     *
     * The original attachments are referenced rather than re-uploaded: the file
     * is already on disk, and duplicating it on every forward would let one
     * small document consume the server through repeated forwarding.
     */
    private function copyOf(array $source, int $targetChatID, string $personID, ?string $note): array
    {
        return $this->send($targetChatID, $personID, [
            'type' => $source['type'],
            'content' => $note !== null && trim($note) !== ''
                ? mb_substr(trim($note), 0, self::MAX_CONTENT_LENGTH)
                : $source['content'],
            'forwardedFromID' => (int) $source['tawasulChatMessageID'],
            'locationLat' => $source['locationLat'],
            'locationLng' => $source['locationLng'],
            'locationName' => $source['locationName'],
            'durationSeconds' => $source['durationSeconds'],
            'attachments' => $this->attachmentsOf((int) $source['tawasulChatMessageID']),
        ]);
    }

    /** @return array<int,array> */
    public function attachmentsOf(int $messageID): array
    {
        $stmt = $this->db->prepare(
            'SELECT filePath, thumbnailPath, fileName, fileMimeType, fileSize, width, height, durationSeconds
               FROM `tawasulMessengerChatAttachment` WHERE tawasulChatMessageID = :id'
        );
        $stmt->execute(['id' => $messageID]);

        return $stmt->fetchAll();
    }

    /**
     * Edit a sent message.
     *
     * Restricted to the author and to messages still in the chat. The old text
     * is overwritten rather than kept: a chat transcript that can be edited by
     * anyone who still has a copy is not a record.
     */
    public function edit(int $messageID, string $personID, string $content): void
    {
        if (!$this->settings->isOn('editingEnabled')) {
            throw new ChatException('Editing messages is switched off in this TawasulOS.');
        }

        $message = $this->messages->find($messageID);
        if ($message === null || $message['deletedAt'] !== null) {
            throw new ChatException('That message can no longer be edited.');
        }
        if (ChatGateway::pad($personID) !== ChatGateway::pad((string) $message['tawasulPersonID'])) {
            throw ChatException::notYourMessage();
        }
        // Membership is checked as well as authorship, matching deleteForEveryone.
        // Without it a person who had been removed from a group kept the ability
        // to rewrite their old messages in the conversation they were removed from.
        if (!$this->chats->isParticipant((int) $message['tawasulChatID'], $personID)) {
            throw ChatException::notAMember();
        }

        $content = trim($content);
        if ($content === '') {
            throw new ChatException('An edited message cannot be empty.', 'content');
        }
        if (mb_strlen($content) > self::MAX_CONTENT_LENGTH) {
            throw new ChatException('That message is too long.', 'content');
        }

        $this->messages->updateContent($messageID, $content);
        $this->refreshPreview((int) $message['tawasulChatID']);
    }

    /**
     * Remove a message for everyone.
     *
     * Only the author may do this, and only within the window the chat allows;
     * after that the message is part of the record.
     */
    public function deleteForEveryone(int $messageID, string $personID, int $allowedMinutes = 60): void
    {
        $message = $this->messages->find($messageID);
        if ($message === null) {
            throw new ChatException('That message no longer exists.');
        }
        if (ChatGateway::pad($personID) !== ChatGateway::pad((string) $message['tawasulPersonID'])) {
            throw ChatException::notYourMessage();
        }
        if (!$this->chats->isParticipant((int) $message['tawasulChatID'], $personID)) {
            throw ChatException::notAMember();
        }

        // Measured by the database: PHP's clock and the column's were written by
        // different time zones, and comparing them here would silently shift the
        // window by an hour.
        $age = $this->clock->ageInSeconds((string) $message['timestampCreated']);
        if ($age !== null && $age > $allowedMinutes * 60) {
            throw new ChatException(sprintf(
                'Messages can only be removed for everyone within %d minutes of sending.',
                $allowedMinutes
            ));
        }

        $this->messages->deleteForEveryone($messageID);
        $this->refreshPreview((int) $message['tawasulChatID']);
    }

    /** Remove one of your own messages, for you only. */
    public function deleteOwn(int $messageID, string $personID): void
    {
        $message = $this->messages->find($messageID);
        if ($message === null) {
            return;
        }
        if (!$this->chats->isParticipant((int) $message['tawasulChatID'], $personID)) {
            throw ChatException::notAMember();
        }

        // Recorded against this person only.
        //
        // This used to delete the message row and its attachments outright,
        // which meant that declining the "delete for everyone" prompt erased the
        // message from every other participant's transcript — the opposite of
        // what was asked for — and, because authorship was never checked, let
        // any participant destroy anybody else's message by asking for scope
        // "me". A row in tawasulMessengerChatMessageHidden says what the person meant:
        // I do not want to see this. Everybody else still does, and the author
        // can still delete it for everyone through deleteForEveryone().
        $this->messages->setHidden($messageID, $personID, true);
    }

    /**
     * Who a message reached, and who has read it.
     *
     * Only the author may ask: this is the screen behind the second tick, and a
     * recipient seeing who else has read a message in a group is the kind of
     * social pressure a school should not be shipping by default. Recipients
     * get the delivery state of their own message from the ticks instead.
     */
    public function messageInfo(int $messageID, string $personID): array
    {
        $message = $this->messages->find($messageID);
        if ($message === null) {
            throw new ChatException('That message no longer exists.');
        }

        $chatID = (int) $message['tawasulChatID'];
        if (!$this->chats->isParticipant($chatID, $personID)) {
            throw ChatException::notAMember();
        }
        if (ChatGateway::pad($personID) !== ChatGateway::pad((string) $message['tawasulPersonID'])) {
            throw new ChatException('Only the person who sent a message can see who read it.');
        }

        // The author is already excluded by the gateway; the loop below only has to
        // normalise the rows for the client.
        $detail = $this->activity->receiptDetail($messageID, $chatID, $personID);

        foreach ($detail['recipients'] as &$recipient) {
            $recipient['tawasulPersonID'] = (string) $recipient['tawasulPersonID'];
            $recipient['title'] = trim(($recipient['preferredName'] ?? '').' '.($recipient['surname'] ?? ''));
            $recipient['isMe'] = false;
            unset($recipient['preferredName'], $recipient['surname']);
        }
        unset($recipient);

        return [
            'tawasulChatMessageID' => $messageID,
            'tawasulChatID' => $chatID,
            'timestampCreated' => $message['timestampCreated'],
            'type' => $message['type'],
            'deletedAt' => $message['deletedAt'],
            'recipientCount' => count($detail['recipients']),
            'deliveredCount' => $detail['deliveredCount'],
            'readCount' => $detail['readCount'],
            'recipients' => $detail['recipients'],
        ];
    }

    /**
     * Pin a message to the conversation, or take the pin off.
     *
     * Capped, because an uncapped pin list turns into a second message list
     * that nobody reads: the cap is small enough that a pinned message stays
     * worth pinning.
     */
    public function pin(int $messageID, string $personID, bool $pinned): void
    {
        $message = $this->messages->find($messageID);
        if ($message === null) {
            throw new ChatException('That message no longer exists.');
        }
        if ($message['deletedAt'] !== null) {
            throw new ChatException('A deleted message cannot be pinned.');
        }

        $chatID = (int) $message['tawasulChatID'];
        if (!$this->chats->isParticipant($chatID, $personID)) {
            throw ChatException::notAMember();
        }

        // Only pin what is actually still in the conversation: a message that has
        // already expired under a disappearing timer has no row to pin, and
        // checking here avoids reporting success for a pin nothing will show.
        if ($pinned && $this->messages->pinCount($chatID) >= self::MAX_PINS) {
            $already = in_array(
                $messageID,
                array_map('intval', array_column($this->messages->pinnedForChat($chatID, self::MAX_PINS), 'tawasulChatMessageID')),
                true
            );
            if (!$already) {
                throw new ChatException(sprintf(
                    'Only %d messages can be pinned in a conversation. Unpin one first.',
                    self::MAX_PINS
                ));
            }
        }

        $this->messages->setPinned($chatID, $messageID, $personID, $pinned);
    }

    /** The pinned strip for a conversation. */
    public function pinnedMessages(int $chatID, string $personID): array
    {
        if (!$this->chats->isParticipant($chatID, $personID)) {
            throw ChatException::notAMember();
        }

        $rows = $this->messages->pinnedForChat($chatID, self::MAX_PINS);
        foreach ($rows as &$row) {
            $row['tawasulChatMessageID'] = (int) $row['tawasulChatMessageID'];
            $row['senderPersonID'] = (string) $row['senderPersonID'];
            $row['senderTitle'] = trim(($row['senderPreferredName'] ?? '').' '.($row['senderSurname'] ?? ''));
            $row['preview'] = $this->previewOf((string) $row['content'], (string) $row['type']);
            unset($row['senderPreferredName'], $row['senderSurname'], $row['content']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Store what the person has typed but not sent.
     *
     * Called on every pause in typing, so it must be cheap and must never throw
     * a message the user has to dismiss: a draft is a convenience, and losing one
     * silently is better than interrupting a conversation with an error.
     */
    public function saveDraft(int $chatID, string $personID, string $content, ?int $replyToMessageID = null): void
    {
        if (!$this->chats->isParticipant($chatID, $personID)) {
            return;
        }

        $content = mb_substr((string) $content, 0, self::MAX_CONTENT_LENGTH);

        // A draft quoting a message that has since gone is not worth keeping: on
        // reopen it would render as an empty quote, which reads as a bug.
        if ($replyToMessageID !== null && !$this->messages->belongsToChat($replyToMessageID, $chatID)) {
            $replyToMessageID = null;
        }

        $this->messages->setDraft($chatID, $personID, $content, $replyToMessageID);
    }

    /** The person's draft for a conversation, or null. */
    public function draft(int $chatID, string $personID): ?array
    {
        if (!$this->chats->isParticipant($chatID, $personID)) {
            return null;
        }

        return $this->messages->draftFor($chatID, $personID);
    }

    public function clearDraft(int $chatID, string $personID): void
    {
        $this->messages->clearDraft($chatID, $personID);
    }

    public function react(int $messageID, string $personID, ?string $emoji): void
    {
        $message = $this->messages->find($messageID);
        if ($message === null || $message['deletedAt'] !== null) {
            throw new ChatException('That message can no longer be reacted to.');
        }
        if (!$this->chats->isParticipant((int) $message['tawasulChatID'], $personID)) {
            throw ChatException::notAMember();
        }

        // A reaction is one emoji or nothing. Multi-codepoint sequences would
        // render as several characters in the bubble.
        if ($emoji !== null && $emoji !== '') {
            $emoji = mb_substr($emoji, 0, 4);
        }

        $this->messages->setReaction($messageID, $personID, $emoji === '' ? null : $emoji);
    }

    public function star(int $messageID, string $personID, bool $starred): void
    {
        $message = $this->messages->find($messageID);
        if ($message === null) {
            throw new ChatException('That message no longer exists.');
        }
        if (!$this->chats->isParticipant((int) $message['tawasulChatID'], $personID)) {
            throw ChatException::notAMember();
        }

        $this->messages->setStarred($messageID, $personID, $starred);
    }

    /**
     * Mark a conversation read up to a point, and delivered up to the newest
     * message in it.
     */
    public function markRead(int $chatID, string $personID, ?int $upToMessageID = null): void
    {
        if (!$this->chats->isParticipant($chatID, $personID)) {
            throw ChatException::notAMember();
        }

        $chat = $this->requireChat($chatID);

        $latest = $upToMessageID ?? ($chat['lastMessageID'] === null ? 0 : (int) $chat['lastMessageID']);
        if ($latest <= 0) {
            return;
        }

        $this->activity->markDelivered($personID, $chatID, $latest);
        $this->activity->markRead($personID, $chatID, $latest);
    }

    /**
     * A page of the conversation, prepared for the client.
     *
     * Includes which messages the acting person has starred and reacted to, so
     * the composer and the bubbles can render their own state without a second
     * round trip on every page.
     */
    public function conversation(int $chatID, string $personID, ?int $beforeID = null, int $limit = 50): array
    {
        if (!$this->chats->isParticipant($chatID, $personID)) {
            throw ChatException::notAMember();
        }

        $chat = $this->requireChat($chatID);
        $rows = $this->messages->listForChat($chatID, $beforeID, $personID, $limit);

        $ids = array_map('intval', array_column($rows, 'tawasulChatMessageID'));
        $starred = array_flip(array_map('intval', $this->messages->starredBy($personID, $ids)));
        $reacted = array_flip(array_map('intval', $this->messages->reactionsForPerson($ids, $personID)));
        $pinned = array_flip(array_map('intval', $this->messages->pinsForMessages($ids)));

        foreach ($rows as &$row) {
            $row['tawasulChatMessageID'] = (int) $row['tawasulChatMessageID'];
            $row['tawasulChatID'] = (int) $row['tawasulChatID'];
            $row['tawasulPersonID'] = (string) $row['tawasulPersonID'];
            $row['isMine'] = $row['tawasulPersonID'] === ChatGateway::pad($personID);
            $row['starredByMe'] = isset($starred[(int) $row['tawasulChatMessageID']]);
            $row['reactedByMe'] = isset($reacted[(int) $row['tawasulChatMessageID']]);
            $row['pinned'] = isset($pinned[(int) $row['tawasulChatMessageID']]);
            $row['replyTo'] = $this->messages->replyContext(
                $row['replyToMessageID'] === null ? null : (int) $row['replyToMessageID']
            );
            if ($row['replyTo'] !== null) {
                $row['replyTo']['tawasulChatMessageID'] = (int) $row['replyTo']['tawasulChatMessageID'];
                $row['replyTo']['content'] = $row['replyTo']['deletedAt'] !== null
                    ? null
                    : mb_substr((string) $row['replyTo']['content'], 0, self::MAX_QUOTE_LENGTH);
            }
            foreach ($row['attachments'] as &$attachment) {
                $attachment['tawasulChatAttachmentID'] = (int) $attachment['tawasulChatAttachmentID'];
                $attachment['fileSize'] = (int) $attachment['fileSize'];
                $attachment['kind'] = AttachmentStore::kindOf(pathinfo($attachment['fileName'], PATHINFO_EXTENSION));
            }
            unset($attachment);
        }
        unset($row);

        return [
            'chat' => [
                'tawasulChatID' => (int) $chat['tawasulChatID'],
                'type' => $chat['type'],
                'name' => $chat['name'],
                'description' => $chat['description'],
                'disappearingMinutes' => (int) $chat['disappearingMinutes'],
                'myRole' => $this->chats->roleOf($chatID, $personID),
                'participantCount' => $this->chats->participantCount($chatID),
            ] + $this->chats->participantFlags($chatID, $personID),
            'messages' => $rows,
            'hasMore' => count($rows) === $limit,
            // The pinned strip and the draft are part of opening a conversation,
            // not separate screens: both are rendered in the first paint, and a
            // client that had to ask again would flash an empty bar on every open.
            'pinned' => $this->pinnedMessages($chatID, $personID),
            'draft' => $this->messages->draftFor($chatID, $personID),
        ];
    }

    /** Everything a newly opened conversation needs in one response. */
    public function messageForClient(int $messageID, string $personID): array
    {
        $chatID = (int) $this->db->query(
            'SELECT tawasulChatID FROM `tawasulMessengerChatMessage` WHERE tawasulChatMessageID = '.$messageID
        )->fetchColumn();

        $conversation = $this->conversation($chatID, $personID, null, 1);
        foreach (array_reverse($conversation['messages']) as $message) {
            if ((int) $message['tawasulChatMessageID'] === $messageID) {
                return $message;
            }
        }

        return ['tawasulChatMessageID' => $messageID, 'tawasulChatID' => $chatID];
    }

    private function requireChat(int $chatID): array
    {
        $chat = $this->chats->find($chatID);
        if ($chat === null || $chat['active'] !== 'Y') {
            throw ChatException::chatNotFound();
        }

        return $chat;
    }

    private function normaliseType(string $type): string
    {
        $allowed = ['text', 'image', 'video', 'audio', 'document', 'location', 'system'];

        return in_array($type, $allowed, true) ? $type : 'text';
    }

    /**
     * The one-line summary shown in the chat list.
     *
     * Derived from what the message actually carries, so an image shows as
     * "Photo" and a voice note shows as "Voice note" rather than both showing as
     * whatever the sender typed in the caption.
     */
    private function previewFor(string $type, string $content, array $attachments): string
    {
        if ($attachments === [] && trim($content) === '') {
            return '';
        }

        return $this->previewOf($content, $type, count($attachments));
    }

    /**
     * A label for a message that carries no text, used wherever one line has to
     * stand in for the whole message: the chat list, the pinned strip and
     * search results all need the same wording for the same thing.
     */
    private function previewOf(string $content, string $type, int $attachmentCount = 1): string
    {
        if (trim($content) !== '') {
            return mb_substr(preg_replace('/\s+/u', ' ', $content), 0, 120);
        }

        $label = match ($type) {
            'image' => __('Photo'),
            'video' => __('Video'),
            'audio' => __('Voice note'),
            'document' => __('Attachment'),
            'location' => __('Location'),
            'system' => '',
            default => __('Message'),
        };

        if ($label === '' || $attachmentCount <= 1) {
            return $label;
        }

        return $label.' ('.$attachmentCount.')';
    }

    /**
     * Recompute a chat's list entry after its newest message changed or went.
     *
     * Done on read rather than on every write: the chat list only ever shows the
     * newest message, so rewriting it on each edit would mean a database write
     * per keystroke-level operation for a row nobody is looking at yet.
     */
    private function refreshPreview(int $chatID): void
    {
        $latest = $this->db->prepare(
            'SELECT tawasulChatMessageID, type, content FROM `tawasulMessengerChatMessage`
              WHERE tawasulChatID = :chatID ORDER BY tawasulChatMessageID DESC LIMIT 1'
        );
        $latest->execute(['chatID' => $chatID]);
        $row = $latest->fetch();

        if ($row === false) {
            $this->chats->touchWithMessage($chatID, 0, '');

            return;
        }

        $attachments = $this->db->prepare(
            'SELECT COUNT(*) FROM `tawasulMessengerChatAttachment` WHERE tawasulChatMessageID = :id'
        );
        $attachments->execute(['id' => $row['tawasulChatMessageID']]);

        $preview = ($row['content'] ?? '') !== ''
            ? mb_substr(preg_replace('/\s+/u', ' ', (string) $row['content']), 0, 120)
            : $this->previewFor(
                $row['type'],
                '',
                array_fill(0, (int) $attachments->fetchColumn(), true)
            );

        $this->chats->touchWithMessage($chatID, (int) $row['tawasulChatMessageID'], $preview);
    }
}
