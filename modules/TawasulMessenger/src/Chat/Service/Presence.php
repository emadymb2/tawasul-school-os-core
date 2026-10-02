<?php
namespace Tos\Module\TawasulChat\Service;

use Tos\Module\TawasulChat\Domain\ActivityGateway;
use Tos\Module\TawasulChat\Domain\ChatGateway;
use Tos\Module\TawasulChat\Support\Settings;

/**
 * Presence and the typing indicator.
 *
 * Neither is stored as a durable fact. A person is "online" while their ping is
 * fresh and ages out on its own, and a typing indicator is a timestamp that the
 * reader treats as stale after a few seconds. That means nothing has to run on
 * logout, on a crashed browser, or on a timer to clean up after someone — the
 * state expires because it is defined in terms of time rather than set.
 */
class Presence
{
    /** A typing indicator older than this is not shown. */
    const TYPING_TTL = 6;

    /** How long a ping stays meaningful before the user reads as away. */
    const AWAY_AFTER = 300;

    /** @var ActivityGateway */
    private $activity;

    /** @var ChatGateway */
    private $chats;

    /** @var Settings */
    private $settings;

    public function __construct(ActivityGateway $activity, ChatGateway $chats, Settings $settings)
    {
        $this->activity = $activity;
        $this->chats = $chats;
        $this->settings = $settings;
    }

    /**
     * Record that a person's client is alive.
     *
     * @param bool $active whether they interacted just now, which is what
     *                     separates "at the keyboard" from "tab left open"
     */
    public function beat(string $personID, bool $active = false): void
    {
        $this->activity->ping($personID, $active ? 'online' : 'away');
    }

    public function typing(int $chatID, string $personID): void
    {
        if (!$this->settings->isOn('typingIndicatorEnabled')) {
            return;
        }
        if (!$this->chats->isParticipant($chatID, $personID)) {
            return;
        }

        $this->activity->setTyping($personID, $chatID);
    }

    /** Clear the indicator, which the client sends when the composer empties. */
    public function stoppedTyping(string $personID): void
    {
        $this->activity->setTyping($personID, null);
    }

    /** @return array rows of who is typing, excluding the person asking */
    public function whoIsTyping(int $chatID, string $personID): array
    {
        if (!$this->settings->isOn('typingIndicatorEnabled')) {
            return [];
        }

        $me = ChatGateway::pad($personID);
        $rows = [];
        foreach ($this->activity->typingIn($chatID, self::TYPING_TTL) as $row) {
            if ((string) $row['tawasulPersonID'] === $me) {
                continue;
            }
            $row['tawasulPersonID'] = (string) $row['tawasulPersonID'];
            $rows[] = $row;
        }

        return $rows;
    }

    /** Presence for a set of people, with stale pings already resolved to offline. */
    public function forPeople(array $personIDs): array
    {
        return $this->activity->presenceFor(
            $personIDs,
            $this->settings->getInt('presenceTimeoutSeconds', 15, 3600)
        );
    }

    /**
     * How a presence row should be described.
     *
     * Returns a token rather than a finished sentence. The client renders it
     * because "5 minutes ago" has to be recalculated as time passes, and because
     * the server cannot produce it in the reader's language without a
     * translator for every unit. lastSeenAt is passed through untouched so the
     * browser can say "last seen 5 minutes ago" in the language it is set to.
     */
    public static function describe(array $presence): array
    {
        $status = $presence['status'] ?? 'offline';

        return [
            'status' => $status,
            'lastSeenAt' => $presence['lastSeenAt'] ?? null,
            'label' => match ($status) {
                'online' => __('online'),
                'away' => __('away'),
                default => __('offline'),
            },
        ];
    }
}
