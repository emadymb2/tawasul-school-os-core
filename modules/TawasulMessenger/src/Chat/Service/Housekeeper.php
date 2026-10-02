<?php
namespace Tos\Module\TawasulChat\Service;

use Tos\Module\TawasulChat\Domain\ChatGateway;
use Tos\Module\TawasulChat\Support\AttachmentStore;
use Tos\Module\TawasulChat\Support\Settings;

/**
 * Expiring messages, called opportunistically rather than on a schedule.
 *
 * A chat system that needs a cron job to delete old messages will, on a school
 * server, never have one. So the work is done by whoever happens to be using the
 * system: each send and each poll does a bounded amount of cleanup, and because
 * every client is polling, the total rate of cleanup rises with usage instead of
 * needing its own timer.
 *
 * The bound matters more than the eagerness. Without a cap, one unlucky request
 * could decide to purge a year of attachments, and the person who then sent a
 * message would be the one who waited for it.
 */
class Housekeeper
{
    /** Messages purged per invocation. */
    const BATCH = 40;

    /** How often the same chat may be swept, so two people talking do not both
     *  pay for the same sweep. */
    const SWEEP_COOLDOWN_MINUTES = 5;

    /** @var \PDO */
    private $db;

    /** @var Settings */
    private $settings;

    /** @var AttachmentStore */
    private $store;

    /** @var array<string,bool> chat ids already swept during this request */
    private $swept = [];

    public function __construct(\PDO $db, Settings $settings, AttachmentStore $store)
    {
        $this->db = $db;
        $this->settings = $settings;
        $this->store = $store;
    }

    /**
     * Housekeeping for one conversation, done as a side effect of using it.
     *
     * @param int $chatID
     */
    public function sweep(int $chatID): void
    {
        if (isset($this->swept[$chatID])) {
            return;
        }
        $this->swept[$chatID] = true;

        $stmt = $this->db->prepare('SELECT disappearingMinutes FROM `tawasulMessengerChat` WHERE tawasulChatID = :chatID');
        $stmt->execute(['chatID' => $chatID]);
        $minutes = (int) $stmt->fetchColumn();

        // The cutoff is computed by MySQL rather than in PHP. PHP's timezone
        // comes from the installation settings and MySQL's from the host, and
        // where they differ by an hour this is what decides whether a message is
        // expired or not.
        $expired = [];
        if ($minutes > 0) {
            $find = $this->db->prepare(
                'SELECT tawasulChatMessageID FROM `tawasulMessengerChatMessage`
                  WHERE tawasulChatID = :chatID
                    AND timestampCreated < DATE_SUB(NOW(), INTERVAL '.(int) $minutes.' MINUTE)
                  ORDER BY tawasulChatMessageID LIMIT '.self::BATCH
            );
            $find->execute(['chatID' => $chatID]);
            $expired = array_column($find->fetchAll(), 'tawasulChatMessageID');
        }

        $retentionDays = $this->settings->getInt('messageRetentionDays', 0, 3650);
        if ($retentionDays > 0) {
            $find = $this->db->prepare(
                'SELECT tawasulChatMessageID FROM `tawasulMessengerChatMessage`
                  WHERE tawasulChatID = :chatID
                    AND timestampCreated < DATE_SUB(NOW(), INTERVAL '.(int) $retentionDays.' DAY)
                  ORDER BY tawasulChatMessageID LIMIT '.self::BATCH
            );
            $find->execute(['chatID' => $chatID]);
            $expired = array_merge($expired, array_column($find->fetchAll(), 'tawasulChatMessageID'));
        }

        $expired = array_values(array_unique(array_map('intval', $expired)));
        if ($expired === []) {
            return;
        }

        // Remove the files first. If this fails the rows are still here, and a
        // row with a missing file renders as a broken attachment for a while
        // rather than leaving orphans on disk forever.
        foreach ($expired as $messageID) {
            $paths = $this->db->prepare(
                'SELECT filePath, thumbnailPath FROM `tawasulMessengerChatAttachment` WHERE tawasulChatMessageID = :id'
            );
            $paths->execute(['id' => $messageID]);
            foreach ($paths->fetchAll() as $attachment) {
                $this->store->remove($attachment['filePath']);
                $this->store->remove($attachment['thumbnailPath']);
            }
        }

        // All positional. PDO refuses a statement that mixes named and
        // positional placeholders, so the chat id is bound as the first '?' of
        // the same list rather than as a named parameter alongside the others.
        $placeholders = implode(',', array_fill(0, count($expired), '?'));
        // tawasulMessengerChatPin and tawasulMessengerChatDraft carry the message id in a
        // tawasulChatMessageID column, so they clear with the rest. A pin left
        // pointing at a purged message would render as a blank strip entry, and
        // a draft quoting one would reopen as an empty quote.
        foreach ([
            'tawasulMessengerChatAttachment', 'tawasulMessengerChatReceipt', 'tawasulMessengerChatReaction',
            'tawasulMessengerChatStar', 'tawasulMessengerChatPin', 'tawasulMessengerChatMessage',
        ] as $table) {
            $this->db->prepare(
                'DELETE FROM `'.$table.'` WHERE tawasulChatMessageID IN ('.$placeholders.')'
            )->execute($expired);
        }

        // A reply pointing at a purged message would render as an empty quote. The same
        // applies to a draft quoting one, which would reopen with a quote strip
        // showing nothing.
        $this->db->prepare(
            'UPDATE `tawasulMessengerChatMessage` SET replyToMessageID = NULL
              WHERE tawasulChatID = ? AND replyToMessageID IN ('.$placeholders.')'
        )->execute(array_merge([$chatID], $expired));

        $this->db->prepare(
            'UPDATE `tawasulMessengerChatDraft` SET replyToMessageID = NULL
              WHERE tawasulChatID = ? AND replyToMessageID IN ('.$placeholders.')'
        )->execute(array_merge([$chatID], $expired));

        $this->reindexChat($chatID);
    }

    /**
     * The chat list's newest-message fields now point at a message that is
     * gone, so they have to be rebuilt from whatever is actually left.
     */
    private function reindexChat(int $chatID): void
    {
        $latest = $this->db->prepare(
            'SELECT tawasulChatMessageID, content FROM `tawasulMessengerChatMessage`
              WHERE tawasulChatID = :chatID ORDER BY tawasulChatMessageID DESC LIMIT 1'
        );
        $latest->execute(['chatID' => $chatID]);
        $row = $latest->fetch();

        if ($row === false) {
            $this->db->prepare(
                'UPDATE `tawasulMessengerChat` SET lastMessageID = NULL, lastMessagePreview = \'\', timestampModified = NOW()
                  WHERE tawasulChatID = :chatID'
            )->execute(['chatID' => $chatID]);

            // Nobody's read cursor can point past the end of the conversation.
            $this->db->prepare(
                'UPDATE `tawasulMessengerChatParticipant` SET lastReadMessageID = NULL, lastReadTimestamp = NULL
                  WHERE tawasulChatID = :chatID'
            )->execute(['chatID' => $chatID]);

            return;
        }

        $this->db->prepare(
            'UPDATE `tawasulMessengerChat` SET lastMessageID = :id, lastMessagePreview = :preview, timestampModified = NOW()
              WHERE tawasulChatID = :chatID'
        )->execute([
            'id' => $row['tawasulChatMessageID'],
            'preview' => mb_substr(preg_replace('/\s+/u', ' ', (string) $row['content']), 0, 255),
            'chatID' => $chatID,
        ]);

        // Read cursors ahead of the newest surviving message would hide messages
        // that are still there.
        $this->db->prepare(
            'UPDATE `tawasulMessengerChatParticipant` SET lastReadMessageID = :id, lastReadTimestamp = NOW()
              WHERE tawasulChatID = :chatID
                AND (lastReadMessageID IS NULL OR lastReadMessageID > :id)'
        )->execute(['id' => $row['tawasulChatMessageID'], 'chatID' => $chatID]);
    }

    /**
     * Clear typing indicators that will never be read.
     *
     * A person who closes the tab mid-sentence leaves a row that says they are
     * typing forever. Readers already ignore indicators older than a few
     * seconds, so this only tidies storage.
     */
    public function clearStaleTyping(): void
    {
        $this->db->exec(
            'UPDATE `tawasulMessengerChatPresence` SET typingChatID = NULL, typingAt = NULL
              WHERE typingChatID IS NOT NULL
                AND typingAt < DATE_SUB(NOW(), INTERVAL 60 SECOND)'
        );
    }

    /** Remove a person's presence row. Used when chat is switched off for everyone. */
    public function forget(string $personID): void
    {
        $this->db->prepare('DELETE FROM `tawasulMessengerChatPresence` WHERE tawasulPersonID = :personID')
            ->execute(['personID' => ChatGateway::pad($personID)]);
    }
}
