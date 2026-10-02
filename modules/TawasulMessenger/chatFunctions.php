<?php
/*
TawasulChat — WhatsApp-style messaging for TawasulOS.

Module-level helpers. The service wiring lives here rather than in each page so
that the pages, the AJAX endpoint and any REST resource all build the same
objects the same way.
*/

require_once __DIR__.'/src/Chat/bootstrap.php';

use TawasulOS\Session\Session;
use Tos\Module\TawasulChat\Domain\ActivityGateway;
use Tos\Module\TawasulChat\Domain\ChatGateway;
use Tos\Module\TawasulChat\Domain\MessageGateway;
use Tos\Module\TawasulChat\Service\ChatService;
use Tos\Module\TawasulChat\Service\Housekeeper;
use Tos\Module\TawasulChat\Service\MessageService;
use Tos\Module\TawasulChat\Service\Presence;
use Tos\Module\TawasulChat\Support\AttachmentStore;
use Tos\Module\TawasulChat\Support\Clock;
use Tos\Module\TawasulChat\Support\LongPollTransport;
use Tos\Module\TawasulChat\Support\Settings;

/**
 * Build every chat service from the live PDO handle.
 *
 * Deliberately a plain factory rather than an entry in the platform container:
 * the container is built once per request from core's service definitions, and
 * adding module services to it would mean core knowing about this module. The
 * objects are cheap — four gateways holding one connection between them.
 *
 * @return array{clock:Clock, settings:Settings, store:AttachmentStore, chats:ChatGateway, messages:MessageGateway, activity:ActivityGateway, chatService:ChatService, messageService:MessageService, presence:Presence, housekeeper:Housekeeper, transport:LongPollTransport}
 */
function chatServices(PDO $db, string $absolutePath): array
{
    // TawasulOS opens its connections as utf8mb3, which cannot store a 4-byte
    // character at all — so an emoji reaction, or an emoji typed into a
    // message, arrives as question marks. The chat tables are utf8mb4, so the
    // connection is moved up to match them.
    //
    // This is a widening, not a change of meaning: utf8mb4 reads every
    // utf8mb3 value correctly, so the platform's own tables in the same session
    // are unaffected. Best effort, because a server compiled without utf8mb4
    // would refuse the statement.
    try {
        $db->exec("SET NAMES utf8mb4");
    } catch (PDOException $e) {
        error_log('TawasulMessenger chat: could not switch the connection to utf8mb4; emoji will not render.');
    }

    $clock = new Clock($db);
    $settings = new Settings($db);
    $store = new AttachmentStore($absolutePath, $settings);

    $chats = new ChatGateway($db);
    $messages = new MessageGateway($db);
    $activity = new ActivityGateway($db);

    return [
        'clock' => $clock,
        'settings' => $settings,
        'store' => $store,
        'chats' => $chats,
        'messages' => $messages,
        'activity' => $activity,
        'chatService' => new ChatService($db, $chats, $activity, $settings),
        'messageService' => new MessageService($db, $chats, $messages, $activity, $settings, $store, $clock),
        'presence' => new Presence($activity, $chats, $settings),
        'housekeeper' => new Housekeeper($db, $settings, $store),
        'transport' => new LongPollTransport($db, $settings, $clock),
    ];
}

/**
 * The acting person's ID, always zero-padded to the column width.
 *
 * The session holds it zero-padded already, but going through this means a
 * caller passing an int or an unpadded string cannot produce a query that
 * silently matches nothing.
 *
 * Typed against the session class rather than array: the platform passes the
 * Session object itself, so an array hint here is a fatal on every page load
 * rather than a caught error.
 */
function chatPersonID(Session $session): string
{
    $personID = $session->get('tawasulPersonID');

    return str_pad((string) $personID, 10, '0', STR_PAD_LEFT);
}

/**
 * Whether the acting person may manage a conversation's membership.
 *
 * Read as data by the pages so the controls they render and the check the
 * services perform cannot disagree.
 */
function chatCanManage(int $chatID): bool
{
    if ($chatID <= 0) {
        return false;
    }

    global $guid, $connection2;

    return isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/messenger_chat_group_manage.php')
        || isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/messenger_chat_group_manage.php', 'Manage Chat Group');
}

/**
 * The avatar URL for a person, falling back to their initials.
 *
 * Returns null when there is no photo so the caller can render initials rather
 * than a broken image.
 *
 * Idempotent: the stored paths are relative, but the same values are also
 * handed to the client as JSON, where a relative path resolves against
 * index.php rather than the site root and every avatar 404s. Values that are
 * already absolute are therefore returned untouched, so it does not matter
 * whether a given payload has been through here.
 */
function chatAvatar(?string $path): ?string
{
    if ($path === null || trim($path) === '') {
        return null;
    }

    if (preg_match('#^(https?:)?//#i', $path) === 1) {
        return $path;
    }

    global $session;

    return $session->get('absoluteURL').'/'.ltrim(str_replace('\\', '/', $path), '/');
}

/**
 * Make every avatar path in a JSON payload absolute, in place.
 *
 * The services read the paths straight out of tawasulPerson and tawasulMessengerChat and
 * have no session, so the paths are relative at that point. Rewriting them here
 * keeps one rule for the whole module: anything leaving chat_ajax.php is
 * already an absolute URL, whether it is rendered by chat.php or by the client.
 */
function chatAbsolutizeAvatars(array $payload)
{
    foreach ($payload as $key => $value) {
        if (is_array($value)) {
            $payload[$key] = chatAbsolutizeAvatars($value);
        } elseif (($key === 'avatar' || $key === 'iconPath' || $key === 'image_240') && is_string($value)) {
            $payload[$key] = chatAvatar($value);
        }
    }

    return $payload;
}

/**
 * The two-letter fallback shown when a person has no photo.
 */
function chatInitials(string $preferredName, string $surname): string
{
    $first = mb_substr(trim($preferredName) === '' ? $surname : $preferredName, 0, 1, 'UTF-8');
    $last = mb_substr(trim($surname), 0, 1, 'UTF-8');

    return mb_strtoupper($first.$last, 'UTF-8');
}
