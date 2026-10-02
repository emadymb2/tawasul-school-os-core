<?php
/*
TawasulChat — the JSON endpoint the chat UI talks to.

One endpoint rather than one file per action, because the conversation screen
issues small, frequent requests and each of them would otherwise be a separate
file in the module's action URLList. The permission gate is still the platform's
own: chat.php, which owns the Chat action, is the URL checked below.

Two gates run before anything else:

  * isActionAccessible, so a person without the Chat action gets nothing. The
    services check membership per chat on top of that.
  * The CSRF token. tawasul.php only runs its own CSRF check for *Process.php
    files, and this endpoint is deliberately not one of those, so it validates
    the token itself. Skipping this would make every send endpoint a
    cross-site-write target.

The poll action is what makes this feel like a messaging app: it holds the
request open until something changes rather than asking the client to come back
in two seconds.
*/

use Tos\Module\TawasulChat\Http\ChatException;
use Tos\Module\TawasulChat\Support\AttachmentStore;

require_once __DIR__.'/../../tawasul.php';
require_once __DIR__.'/chatFunctions.php';

header('Content-Type: application/json; charset=utf-8');
// A poll can legitimately sit for the configured timeout; nothing here should
// be truncated by a stale buffer.
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    ob_end_clean();
}

function chatReply($payload, int $status = 200)
{
    // Avatar paths leave the services relative; rewrite them here so the client
    // and the server-rendered pages agree on what an avatar URL looks like.
    $payload = chatAbsolutizeAvatars($payload);

    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function chatFail(string $message, int $status = 400, string $field = '')
{
    chatReply(['ok' => false, 'error' => $message, 'field' => $field], $status);
}

if (!isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/messenger_chat.php')) {
    chatFail(__('You do not have access to this action.'), 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$request = $method === 'GET' ? $_GET : $_POST;

// Every write goes through the CSRF token the page already carries.
if ($method === 'POST') {
    $csrf = $session->get('csrftoken');
    if (empty($csrf) || !hash_equals((string) $csrf, (string) ($_POST['csrftoken'] ?? ''))) {
        chatFail(__('Your session has expired. Please reload the page.'), 403);
    }
}

$action = (string) ($request['chatAction'] ?? 'inbox');
$personID = chatPersonID($session);
$absolutePath = $session->get('absolutePath');

$services = chatServices($connection2, $absolutePath);
$settingGateway = $container->get(\TawasulOS\Domain\System\SettingGateway::class);

try {
    switch ($action) {

        // ---- reading -------------------------------------------------------

        case 'inbox':
            chatReply(['ok' => true] + $services['chatService']->inbox($personID));

        case 'header':
            $chatID = (int) ($request['chatID'] ?? 0);
            chatReply(['ok' => true] + $services['chatService']->header($chatID, $personID));

        case 'messages':
            $chatID = (int) ($request['chatID'] ?? 0);
            $before = isset($request['before']) && $request['before'] !== '' ? (int) $request['before'] : null;
            chatReply([
                'ok' => true,
                'typing' => $services['presence']->whoIsTyping($chatID, $personID),
            ] + $services['messageService']->conversation($chatID, $personID, $before, 50));

        case 'contacts':
            chatReply([
                'ok' => true,
                'contacts' => $services['chatService']->contacts($personID, (string) ($request['q'] ?? ''), 60),
            ]);

        case 'search':
            $needle = trim((string) ($request['q'] ?? ''));
            if (mb_strlen($needle) < 2) {
                chatReply(['ok' => true, 'results' => []]);
            }
            $results = $services['messages']->search($personID, $needle, 50);
            foreach ($results as &$result) {
                $result['tawasulChatMessageID'] = (int) $result['tawasulChatMessageID'];
                $result['tawasulChatID'] = (int) $result['tawasulChatID'];
                $result['tawasulPersonID'] = (string) $result['tawasulPersonID'];
            }
            unset($result);
            chatReply(['ok' => true, 'results' => $results]);

        case 'starred':
            $starred = $services['messages']->starredFor($personID, 100);
            foreach ($starred as &$row) {
                $row['tawasulChatMessageID'] = (int) $row['tawasulChatMessageID'];
                $row['tawasulChatID'] = (int) $row['tawasulChatID'];
            }
            unset($row);
            chatReply(['ok' => true, 'starred' => $starred]);

        // ---- the long poll -------------------------------------------------

        case 'poll':
            $since = (string) ($_POST['since'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
                // A missing or malformed cursor falls back to a short window
                // rather than to the beginning of time, so a client with a
                // broken clock cannot ask for the whole database. Sixty seconds
                // of the database's own clock, for the same reason.
                $since = $services['clock']->ago(60);
            }

            // Housekeeping rides along with the poll: every open client keeps the
            // expiring messages swept without needing a cron job.
            $services['housekeeper']->clearStaleTyping();

            $services['presence']->beat($personID, !empty($_POST['active']));

            $timeout = $services['settings']->pollTimeout();
            $change = $services['transport']->wait($personID, $since, $timeout);

            chatReply(['ok' => true, 'chats' => $services['chatService']->inbox($personID)] + $change);

        // ---- sending -------------------------------------------------------

        case 'send':
            $chatID = (int) ($_POST['chatID'] ?? 0);
            $services['housekeeper']->sweep($chatID);

            $storedFiles = [];
            $type = 'text';

            if (!empty($_FILES['attachment'])) {
                $file = $storedFiles[] = $services['store']->store($_FILES['attachment'], $personID);
                $type = AttachmentStore::kindOf(pathinfo($file['fileName'], PATHINFO_EXTENSION));
                if ($type === 'file') {
                    $type = 'document';
                }
            } else {
                // A shared location carries no text, so it has to be recognised
                // here or MessageService::send rejects it as an empty message and
                // the location button never works. Restricted to a known set, and
                // only when the coordinates are actually present, so a crafted
                // request cannot invent a message type the client cannot render.
                $requested = (string) ($_POST['type'] ?? 'text');
                if ($requested === 'location' && $_POST['locationLat'] !== '' && $_POST['locationLng'] !== '') {
                    $type = 'location';
                }
            }

            $message = $services['messageService']->send($chatID, $personID, [
                'type' => $type,
                'content' => (string) ($_POST['content'] ?? ''),
                'replyToMessageID' => ($_POST['replyToMessageID'] ?? '') !== '' ? (int) $_POST['replyToMessageID'] : null,
                'locationLat' => ($_POST['locationLat'] ?? '') !== '' ? (float) $_POST['locationLat'] : null,
                'locationLng' => ($_POST['locationLng'] ?? '') !== '' ? (float) $_POST['locationLng'] : null,
                'locationName' => (string) ($_POST['locationName'] ?? ''),
                'durationSeconds' => ($_POST['durationSeconds'] ?? '') !== '' ? (int) $_POST['durationSeconds'] : null,
                'attachments' => $storedFiles,
            ]);

            chatReply(['ok' => true, 'message' => $message]);

        /**
         * A recorded voice note. The audio is the request body rather than a
         * multipart field: a recorded clip is one blob the browser already
         * encoded, and putting it through multipart would re-encode it and force
         * the whole recording into memory before anything could be written.
         */
        case 'voice':
            if (!$services['settings']->isOn('voiceNotesEnabled')) {
                chatFail(__('Voice notes are switched off in this TawasulOS.'), 403);
            }

            $chatID = (int) ($_POST['chatID'] ?? 0);
            $extension = strtolower((string) ($_POST['extension'] ?? 'webm'));
            if (!in_array($extension, AttachmentStore::AUDIO_EXTENSIONS, true)) {
                chatFail(__('That audio format cannot be sent.'), 400);
            }

            $audio = null;
            if (isset($_FILES['audio']) && is_uploaded_file($_FILES['audio']['tmp_name'])) {
                // The browser records the clip in one blob and this is the shape
                // it arrives in. Reading php://input instead is not an option
                // for a multipart body: PHP leaves it empty, so every recording
                // came through as "The recording was empty."
                $audio = file_get_contents($_FILES['audio']['tmp_name']);
            } else {
                // A raw request body is still accepted, for any client that
                // streams the clip rather than using multipart.
                $audio = file_get_contents('php://input');
            }

            $file = $services['store']->storeBlob($audio, $extension, 'voice-note.'.$extension, $personID);
            $duration = ($_POST['durationSeconds'] ?? '') !== '' ? (int) $_POST['durationSeconds'] : null;

            $message = $services['messageService']->send($chatID, $personID, [
                'type' => 'audio',
                'content' => '',
                'durationSeconds' => $duration,
                'attachments' => [$file],
            ]);

            chatReply(['ok' => true, 'message' => $message]);

        case 'read':
            $chatID = (int) ($_POST['chatID'] ?? 0);
            $upTo = ($_POST['messageID'] ?? '') !== '' ? (int) $_POST['messageID'] : null;
            $services['messageService']->markRead($chatID, $personID, $upTo);
            $services['housekeeper']->sweep($chatID);
            chatReply(['ok' => true]);

        case 'typing':
            $chatID = (int) ($_POST['chatID'] ?? 0);
            if (!empty($_POST['typing'])) {
                $services['presence']->typing($chatID, $personID);
            } else {
                $services['presence']->stoppedTyping($personID);
            }
            chatReply(['ok' => true]);

        case 'beat':
            $services['presence']->beat($personID, !empty($_POST['active']));
            chatReply(['ok' => true]);

        // ---- changing a message --------------------------------------------

        case 'edit':
            $services['messageService']->edit(
                (int) ($_POST['messageID'] ?? 0),
                $personID,
                (string) ($_POST['content'] ?? '')
            );
            chatReply(['ok' => true]);

        case 'delete':
            $messageID = (int) ($_POST['messageID'] ?? 0);
            $scope = ($_POST['scope'] ?? 'me') === 'everyone' ? 'everyone' : 'me';
            if ($scope === 'everyone') {
                $services['messageService']->deleteForEveryone($messageID, $personID, 60);
            } else {
                $services['messageService']->deleteOwn($messageID, $personID);
            }
            chatReply(['ok' => true]);

        case 'info':
            chatReply(['ok' => true, 'info' => $services['messageService']->messageInfo(
                (int) ($request['messageID'] ?? 0),
                $personID
            )]);

        case 'pins':
            $chatID = (int) ($request['chatID'] ?? 0);
            chatReply(['ok' => true, 'pinned' => $services['messageService']->pinnedMessages($chatID, $personID)]);

        case 'pin':
            $services['messageService']->pin(
                (int) ($_POST['messageID'] ?? 0),
                $personID,
                !empty($_POST['pinned'])
            );
            chatReply([
                'ok' => true,
                'pinned' => $services['messageService']->pinnedMessages((int) ($_POST['chatID'] ?? 0), $personID),
            ]);

        case 'draft':
            $chatID = (int) ($request['chatID'] ?? 0);

            if ($method === 'GET') {
                chatReply(['ok' => true, 'draft' => $services['messageService']->draft($chatID, $personID)]);
            }

            // The composer posts on every pause in typing. An empty string means
            // "there is no draft any more" and clears the row rather than storing
            // a blank one, so the chat list can tell a finished draft from a typo.
            //
            // Tested with !empty() rather than `!== ''` because the client sends
            // the flag as a form field, so a "do not clear" arrives as the string
            // "0" — which is not the empty string, and read as "clear this draft".
            // That discarded every draft as fast as it could be typed.
            if (($request['content'] ?? '') === '' || !empty($_POST['clear'])) {
                $services['messageService']->clearDraft($chatID, $personID);
            } else {
                $services['messageService']->saveDraft(
                    $chatID,
                    $personID,
                    (string) $request['content'],
                    ($request['replyToMessageID'] ?? '') !== '' ? (int) $request['replyToMessageID'] : null
                );
            }

            chatReply(['ok' => true]);

        case 'forwardTargets':
            chatReply(['ok' => true, 'targets' => $services['chatService']->forwardTargets($personID)]);

        case 'forward':
            // One target is the common case and keeps the old single-chat
            // response shape; several is what the picker's multi-select sends.
            $targets = array_values(array_filter(array_map(
                'intval',
                (array) ($_POST['chatIDs'] ?? [])
            ), fn ($id) => $id > 0));

            if ($targets === [] && ($_POST['chatID'] ?? '') !== '') {
                $targets = [(int) $_POST['chatID']];
            }

            $note = ($_POST['note'] ?? '') !== '' ? (string) $_POST['note'] : null;

            if (count($targets) === 1) {
                $message = $services['messageService']->forward(
                    (int) ($_POST['messageID'] ?? 0),
                    $targets[0],
                    $personID,
                    $note
                );
                chatReply(['ok' => true, 'message' => $message, 'messages' => [$message], 'chatIDs' => $targets]);
            }

            $messages = $services['messageService']->forwardTo(
                (int) ($_POST['messageID'] ?? 0),
                $targets,
                $personID,
                $note
            );
            chatReply(['ok' => true, 'messages' => $messages, 'chatIDs' => $targets]);

        case 'react':
            $services['messageService']->react(
                (int) ($_POST['messageID'] ?? 0),
                $personID,
                (string) ($_POST['emoji'] ?? '')
            );
            chatReply(['ok' => true]);

        case 'star':
            $services['messageService']->star(
                (int) ($_POST['messageID'] ?? 0),
                $personID,
                !empty($_POST['starred'])
            );
            chatReply(['ok' => true]);

        // ---- conversations -------------------------------------------------

        case 'startChat':
            if (!isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/messenger_chat_new.php')) {
                chatFail(__('You do not have access to this action.'), 403);
            }

            $ids = (array) ($_POST['personIDs'] ?? []);
            $name = trim((string) ($_POST['name'] ?? ''));

            if ($name !== '') {
                $chatID = $services['chatService']->createGroup(
                    $personID,
                    $name,
                    $ids,
                    (int) ($_POST['disappearingMinutes'] ?? 0)
                );
            } else {
                $ids = array_values(array_filter(array_map('strval', $ids)));
                if (count($ids) !== 1) {
                    chatFail(__('Choose one person to start a conversation, or name the group.'), 400, 'personIDs');
                }
                $chatID = $services['chatService']->openDirectChat($personID, $ids[0]);
            }

            chatReply(['ok' => true, 'chatID' => $chatID]);

        case 'flag':
            $chatID = (int) ($_POST['chatID'] ?? 0);
            if (!$services['chats']->isParticipant($chatID, $personID)) {
                chatFail(__('You are not in this conversation.'), 403);
            }

            $flag = (string) ($_POST['flag'] ?? '');
            $value = (string) ($_POST['value'] ?? '');
            $mutedUntil = (string) ($_POST['mutedUntil'] ?? '');

            if ($flag === 'mutedUntil') {
                $services['chats']->setParticipantFlag($chatID, $personID, 'mutedUntil', $mutedUntil !== '' ? $mutedUntil : null);
            } elseif ($flag === 'notify' && in_array($value, ['all', 'mentions', 'none'], true)) {
                $services['chats']->setParticipantFlag($chatID, $personID, 'notify', $value);
            } elseif ($flag === 'archived') {
                $services['chats']->setParticipantFlag($chatID, $personID, 'archived', $value === 'Y' ? 'Y' : 'N');
            } elseif ($flag === 'pinned') {
                $services['chats']->setParticipantFlag($chatID, $personID, 'pinned', $value === 'Y' ? 'Y' : 'N');
            } else {
                chatFail(__('Unknown setting.'), 400);
            }

            chatReply(['ok' => true] + $services['chatService']->inbox($personID));

        // ---- group management (admin action) --------------------------------

        case 'manageGroup':
            if (!isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/messenger_chat_group_manage.php')) {
                chatFail(__('You do not have access to this action.'), 403);
            }

            $chatID = (int) ($_POST['chatID'] ?? 0);
            $operation = (string) ($_POST['operation'] ?? '');

            if ($operation === 'add') {
                $added = $services['chatService']->addMembers(
                    $chatID,
                    $personID,
                    (array) ($_POST['personIDs'] ?? [])
                );
            } elseif ($operation === 'remove') {
                $services['chatService']->removeMember($chatID, $personID, (string) ($_POST['targetID'] ?? ''));
                $added = [];
            } elseif ($operation === 'rename') {
                $services['chatService']->renameGroup(
                    $chatID,
                    $personID,
                    (string) ($_POST['name'] ?? ''),
                    (string) ($_POST['description'] ?? '')
                );
                $added = [];
            } elseif ($operation === 'role') {
                $services['chatService']->promote($chatID, $personID, (string) ($_POST['targetID'] ?? ''), (string) ($_POST['role'] ?? 'member'));
                $added = [];
            } elseif ($operation === 'disappearing') {
                $services['chatService']->setDisappearing($chatID, $personID, (int) ($_POST['minutes'] ?? 0));
                $added = [];
            } elseif ($operation === 'leave') {
                $services['chatService']->removeMember($chatID, $personID, $personID);

                // Answered here rather than falling through to the shared header
                // below. Leaving is the one operation whose whole point is that
                // the person is no longer a member, and the header it would
                // otherwise build refuses to describe a chat you have left — so
                // the request used to remove the person successfully and then
                // report "You are not in this conversation", leaving the client
                // showing a failure for something that had already worked.
                chatReply(['ok' => true, 'left' => true, 'chatID' => $chatID]);
            } else {
                chatFail(__('Unknown operation.'), 400);
            }

            chatReply([
                'ok' => true,
                'added' => $added,
            ] + $services['chatService']->header($chatID, $personID));

        // ---- attachment download -------------------------------------------

        case 'file':
            $attachmentID = (int) ($request['attachmentID'] ?? 0);
            $stmt = $connection2->prepare(
                'SELECT a.*, m.tawasulChatID
                   FROM `tawasulMessengerChatAttachment` a
                   JOIN `tawasulMessengerChatMessage` m ON (m.tawasulChatMessageID = a.tawasulChatMessageID)
                  WHERE a.tawasulChatAttachmentID = :id'
            );
            $stmt->execute(['id' => $attachmentID]);
            $attachment = $stmt->fetch();

            // Membership is checked against the message's chat, not the attachment
            // alone: knowing an attachment ID must not be enough to read it.
            if ($attachment === false
                || !$services['chats']->isParticipant((int) $attachment['tawasulChatID'], $personID)) {
                chatFail(__('That file is not available.'), 404);
            }

            $wantThumbnail = !empty($_GET['thumbnail']);
            $path = $wantThumbnail && !empty($attachment['thumbnailPath'])
                ? $attachment['thumbnailPath']
                : $attachment['filePath'];

            $absolute = $services['store']->resolveForRead($path);
            if ($absolute === null) {
                chatFail(__('That file is not available.'), 404);
            }

            $name = $wantThumbnail ? 'thumb' : $attachment['fileName'];

            if (isset($_GET['download'])) {
                header('Content-Disposition: attachment; filename="'.str_replace('"', '', $name).'"');
            } else {
                header('Content-Disposition: inline; filename="'.str_replace('"', '', $name).'"');
            }
            header('Content-Type: '.($attachment['fileMimeType'] ?: 'application/octet-stream'));
            header('Content-Length: '.filesize($absolute));
            header('Cache-Control: private, max-age=3600');
            header('X-Content-Type-Options: nosniff');
            readfile($absolute);
            exit;

        default:
            chatFail(__('Unknown action.'), 404);
    }
} catch (ChatException $e) {
    chatFail($e->getMessage(), 403, $e->field());
} catch (RuntimeException $e) {
    chatFail($e->getMessage(), 400);
} catch (PDOException $e) {
    // Never surface the SQL to the browser: it carries column names and, in a
    // failed insert, sometimes values.
    error_log('TawasulMessenger chat: '.$e->getMessage());
    chatFail(__('The message could not be saved. Please try again.'), 500);
}
