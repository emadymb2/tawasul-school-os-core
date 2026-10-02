<?php
/**
 * End-to-end test for the Messenger chat half, run against the live database.
 *
 * The services are exercised the way the endpoint exercises them rather than
 * through HTTP, because what is worth checking here is the behaviour that spans
 * tables — that a send writes receipts, that a read advances the cursor the chat
 * list counts from, that someone outside a conversation cannot read it. A test
 * that only asserted on the row it just wrote would miss all of that.
 *
 * Everything it creates is removed afterwards, and it refuses to run if chat is
 * not installed, so it is safe to point at a real installation.
 *
 *   php tools/chat/test.php
 */

// Registered before tawasul.php, not after.
//
// The platform's bootstrap ends with a shutdown handler that calls exit(), and
// PHP runs shutdown functions in registration order — so anything registered
// after it never runs at all. A cleanup routine placed the obvious way round
// therefore never fires, and the test quietly leaves its conversations in the
// database for the next run to trip over.
//
// The handler is filled in once the connection exists, further down.
$chatCleanup = null;

register_shutdown_function(function () use (&$chatCleanup) {
    if ($chatCleanup !== null) {
        $chatCleanup();
    }
});

require_once __DIR__.'/../../tawasul.php';

// tawasul.php installs an error handler that renders an HTML error page, which
// is right for a request and useless for a CLI test run. Re-point it at stderr
// once the platform has booted so a failure prints as text.
set_error_handler(function ($severity, $message, $file = '', $line = 0) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once __DIR__.'/../../modules/TawasulMessenger/chatFunctions.php';

use Tos\Module\TawasulChat\Domain\ActivityGateway;
use Tos\Module\TawasulChat\Domain\ChatGateway;
use Tos\Module\TawasulChat\Domain\MessageGateway;
use Tos\Module\TawasulChat\Http\ChatException;
use Tos\Module\TawasulChat\Service\ChatService;
use Tos\Module\TawasulChat\Service\Housekeeper;
use Tos\Module\TawasulChat\Service\MessageService;
use Tos\Module\TawasulChat\Service\Presence;
use Tos\Module\TawasulChat\Support\AttachmentStore;
use Tos\Module\TawasulChat\Support\Clock;
use Tos\Module\TawasulChat\Support\LongPollTransport;
use Tos\Module\TawasulChat\Support\Settings;

$passed = 0;
$failed = 0;
$failures = [];

function check(string $label, bool $condition, string $detail = ''): void
{
    global $passed, $failed, $failures;

    if ($condition) {
        $passed++;
        echo "  ok    $label\n";

        return;
    }

    $failed++;
    $failures[] = $label.($detail === '' ? '' : ' -- '.$detail);
    echo "  FAIL  $label".($detail === '' ? '' : "  ($detail)")."\n";
}

function expectThrows(string $label, callable $fn, string $expectedClass = ChatException::class): void
{
    global $passed, $failed, $failures;

    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $expectedClass) {
            $passed++;
            echo "  ok    $label\n";

            return;
        }
        $failed++;
        $failures[] = $label.' -- wrong exception: '.get_class($e);
        echo "  FAIL  $label  (wrong exception: ".get_class($e).")\n";

        return;
    }

    $failed++;
    $failures[] = $label.' -- nothing was thrown';
    echo "  FAIL  $label  (nothing was thrown)\n";
}

echo "TawasulMessenger chat end-to-end test\n\n";

$db = $connection2;
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// The platform opens connections as utf8mb3, which cannot hold a 4-byte emoji.
// The module's own factory widens the connection, so this does the same before
// building services by hand — without it a reaction reads back as question marks
// and the emoji assertion below is testing the connection, not the code.
try {
    $db->exec('SET NAMES utf8mb4');
} catch (PDOException $e) {
    fwrite(STDERR, "  could not switch to utf8mb4; emoji assertions will be skipped.\n");
}

$absolutePath = $session->get('absolutePath');
$clock = new Clock($db);
$settings = new Settings($db);
$store = new AttachmentStore($absolutePath, $settings);
$chats = new ChatGateway($db);
$messages = new MessageGateway($db);
$activity = new ActivityGateway($db);
$chatService = new ChatService($db, $chats, $activity, $settings);
$messageService = new MessageService($db, $chats, $messages, $activity, $settings, $store, $clock);
$presence = new Presence($activity, $chats, $settings);
$housekeeper = new Housekeeper($db, $settings, $store);
$transport = new LongPollTransport($db, $settings, $clock);

// ---------------------------------------------------------------- fixtures

$persons = [];
foreach ($db->query(
    "SELECT tawasulPersonID FROM tawasulPerson
      WHERE status = 'Full' AND tawasulRoleIDPrimary IS NOT NULL
      ORDER BY tawasulPersonID LIMIT 3"
) as $row) {
    $persons[] = (string) $row['tawasulPersonID'];
}

if (count($persons) < 3) {
    fwrite(STDERR, "  needs at least three active people to test with.\n");
    exit(1);
}

[$alice, $bob, $carol] = $persons;

// Every chat that existed before the run. openDirectChat deliberately reuses an
// existing conversation, so the test does not know which chats it created until
// afterwards: anything not in this list is the test's to remove, and anything in
// it must be left alone.
$preExistingChats = array_column(
    $db->query('SELECT tawasulChatID FROM `tawasulChat`')->fetchAll(),
    'tawasulChatID'
);

// Everything below is cleaned up at the end; recorded as it is created.
$createdChats = [];

$chatCleanup = function () use ($db, &$createdChats, $preExistingChats, $persons) {
    $cleanup = array_values(array_diff(
        array_map('intval', $createdChats),
        array_map('intval', $preExistingChats)
    ));

    foreach ($cleanup as $chatID) {
        // Message ids are collected first because MySQL refuses a DELETE whose
        // subquery reads the same table it is deleting from, so the children
        // cannot be cleared with "WHERE messageID IN (SELECT ... FROM
        // tawasulChatMessage)".
        $messageIDs = array_column(
            $db->query('SELECT tawasulChatMessageID FROM `tawasulChatMessage` WHERE tawasulChatID = '.$chatID)->fetchAll(),
            'tawasulChatMessageID'
        );

        if ($messageIDs !== []) {
            $list = implode(',', array_map('intval', $messageIDs));
            foreach (['tawasulChatAttachment', 'tawasulChatReceipt', 'tawasulChatReaction',
                'tawasulChatStar', 'tawasulChatPin'] as $table) {
                $db->exec('DELETE FROM `'.$table.'` WHERE tawasulChatMessageID IN ('.$list.')');
            }
            $db->exec('UPDATE `tawasulChatMessage` SET replyToMessageID = NULL WHERE tawasulChatMessageID IN ('.$list.')');
            $db->exec('DELETE FROM `tawasulChatMessage` WHERE tawasulChatMessageID IN ('.$list.')');
        }

        $db->prepare('DELETE FROM `tawasulChatDraft` WHERE tawasulChatID = ?')->execute([$chatID]);
        $db->prepare('DELETE FROM `tawasulChatParticipant` WHERE tawasulChatID = ?')->execute([$chatID]);
        $db->prepare('DELETE FROM `tawasulChat` WHERE tawasulChatID = ?')->execute([$chatID]);
    }

    if (isset($persons[0])) {
        $ids = "'".implode("','", array_map(
            fn ($id) => str_pad((string) $id, 10, '0', STR_PAD_LEFT),
            array_slice($persons, 0, 3)
        ))."'";
        $db->exec('DELETE FROM `tawasulChatPresence` WHERE tawasulPersonID IN ('.$ids.')');
    }

    echo "\n  cleaned up ".count($cleanup)." test conversation(s)\n";
};

// ------------------------------------------------------------ direct chat

echo "1. Starting a conversation\n";

$chatID = $chatService->openDirectChat($alice, $bob);
$createdChats[] = $chatID;
check('a conversation is created', $chatID > 0);

$again = $chatService->openDirectChat($bob, $alice);
check('opening it again reuses the same conversation', $again === $chatID, "got $again, expected $chatID");

expectThrows('a conversation with yourself is refused', fn () => $chatService->openDirectChat($alice, $alice));
expectThrows('a person who has left cannot be messaged', fn () => $chatService->openDirectChat($alice, '0009999999'));

check('both people are members', $chats->participantCount($chatID) === 2);

// ----------------------------------------------------------------- sending

echo "\n2. Sending\n";

$message = $messageService->send($chatID, $alice, ['type' => 'text', 'content' => 'Hello from the test']);
$messageID = (int) $message['tawasulChatMessageID'];

// Scoped to this conversation. openDirectChat reuses whatever chat these two
// people had, and totalUnread spans all of them, so an absolute count measures
// the leftovers of earlier runs rather than the message just sent.
$unreadInChat = function (string $who) use ($db, $chatID): int {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM `tawasulChatMessage` m
           JOIN `tawasulChatParticipant` p
             ON (p.tawasulChatID = m.tawasulChatID AND p.tawasulPersonID = :personID)
          WHERE m.tawasulChatID = :chatID
            AND m.tawasulChatMessageID > COALESCE(p.lastReadMessageID, 0)
            AND m.tawasulPersonID <> p.tawasulPersonID'
    );
    $stmt->execute(['personID' => ChatGateway::pad($who), 'chatID' => $chatID]);

    return (int) $stmt->fetchColumn();
};
check('the message has an id', (int) $message['tawasulChatMessageID'] > 0);
check('it is marked as the sender\'s own', ($message['isMine'] ?? false) === true);

$messageID = (int) $message['tawasulChatMessageID'];

$receiptCount = (int) $db->query(
    'SELECT COUNT(*) FROM `tawasulChatReceipt` WHERE tawasulChatMessageID = '.$messageID
)->fetchColumn();
check('a receipt row exists for the recipient', $receiptCount === 1, "found $receiptCount");

// Scoped to this message, not to the conversation: openDirectChat reuses an
// existing chat, so an earlier run's messages are still in there and a
// conversation-wide count would measure those instead.
$receiptCount = (int) $db->query(
    'SELECT COUNT(*) FROM `tawasulChatReceipt` WHERE tawasulChatMessageID = '.$messageID
      ." AND tawasulPersonID = '".$alice."'"
)->fetchColumn();
check('no receipt row exists for the sender', $receiptCount === 0, "found $receiptCount");

$chat = $chats->find($chatID);
check('the chat list entry points at the new message', (int) $chat['lastMessageID'] === (int) $message['tawasulChatMessageID']);
check('the chat list preview is set', str_contains((string) $chat['lastMessagePreview'], 'Hello'));

$chat = $chats->find($chatID);
check('the chat floated to the top by timestamp', strcmp((string) $chat['timestampModified'], (string) $chat['timestampCreated']) >= 0);

expectThrows('an empty message is refused', fn () => $messageService->send($chatID, $alice, ['type' => 'text', 'content' => '   ']));
expectThrows('someone outside the conversation cannot send', fn () => $messageService->send($chatID, $carol, ['type' => 'text', 'content' => 'eavesdrop']));
expectThrows('an absurdly long message is refused', fn () => $messageService->send($chatID, $alice, ['type' => 'text', 'content' => str_repeat('x', 5000)]));

// ------------------------------------------------------------- read state

echo "\n3. Delivery and read receipts\n";

// The message just sent has to move the conversation's unread count, whatever
// was already sitting unread in it from an earlier run.
$unreadAfterSend = $unreadInChat($bob);
$messageService->markRead($chatID, $bob);
check('reading it clears the conversation\'s unread count', $unreadInChat($bob) === 0, 'found '.$unreadInChat($bob));
check('the send had raised it first', $unreadAfterSend > 0, 'found '.$unreadAfterSend);

$readAt = $db->query(
    'SELECT readAt FROM `tawasulChatReceipt` WHERE tawasulChatMessageID = '.(int) $message['tawasulChatMessageID']
)->fetchColumn();
check('a read timestamp was written', !empty($readAt));

$cursor = $db->query(
    'SELECT lastReadMessageID FROM `tawasulChatParticipant`
      WHERE tawasulChatID = '.(int) $chatID." AND tawasulPersonID = '".$bob."'"
)->fetchColumn();
check('the read cursor advanced', (int) $cursor === (int) $message['tawasulChatMessageID']);

expectThrows('someone outside cannot mark it read', fn () => $messageService->markRead($chatID, $carol));

// --------------------------------------------------------------- reactions

echo "\n4. Reactions, stars and replies\n";

$messageService->react($messageID, $bob, '👍');
$emoji = $db->query(
    'SELECT emoji FROM `tawasulChatReaction` WHERE tawasulChatMessageID = '.$messageID
)->fetchColumn();
check('a reaction is stored', $emoji === '👍', 'got '.$emoji);

$messageService->react($messageID, $bob, '❤️');
$count = (int) $db->query(
    'SELECT COUNT(*) FROM `tawasulChatReaction` WHERE tawasulChatMessageID = '.$messageID
)->fetchColumn();
check('reacting again replaces rather than duplicates', $count === 1, "found $count");

$messageService->react($messageID, $bob, '');
$count = (int) $db->query(
    'SELECT COUNT(*) FROM `tawasulChatReaction` WHERE tawasulChatMessageID = '.$messageID
)->fetchColumn();
check('an empty reaction clears it', $count === 0, "found $count");

$messageService->star($messageID, $alice, true);
$starred = array_filter(
    $messages->starredFor($alice),
    fn ($row) => (int) $row['tawasulChatMessageID'] === $messageID
);
check('a starred message is listed', count($starred) === 1, 'found '.count($starred));
$starredForBob = array_filter(
    $messages->starredFor($bob),
    fn ($row) => (int) $row['tawasulChatMessageID'] === $messageID
);
check('a star is private to the person who set it', count($starredForBob) === 0);

// A unique needle per run. A literal one would match the same words in
// conversations left behind by previous runs, in chats carol is a member of,
// and the scoping assertion below would then be testing nothing.
$searchNeedle = 'A reply '.uniqid();
$reply = $messageService->send($chatID, $bob, [
    'type' => 'text',
    'content' => $searchNeedle,
    'replyToMessageID' => $messageID,
]);
$replyMessageID = (int) $reply['tawasulChatMessageID'];
$stored = $messages->find($replyMessageID);
check('the reply points at the original', (int) $stored['replyToMessageID'] === $messageID);

// A quote into another conversation would read a message the person cannot see.
$strangerChat = $chatService->openDirectChat($alice, $carol);
$createdChats[] = $strangerChat;
expectThrows(
    'a reply cannot quote a message from another conversation',
    fn () => $messageService->send($strangerChat, $alice, [
        'type' => 'text',
        'content' => 'quoting',
        'replyToMessageID' => (int) $message['tawasulChatMessageID'],
    ])
);

// ------------------------------------------------------------ edit/delete

echo "\n5. Editing and deleting\n";

$messageService->edit((int) $message['tawasulChatMessageID'], $alice, 'Edited text');
$stored = $messages->find((int) $message['tawasulChatMessageID']);
check('the content is replaced', $stored['content'] === 'Edited text');
check('it is marked as edited', !empty($stored['editedAt']));

expectThrows(
    'only the author may edit',
    fn () => $messageService->edit((int) $message['tawasulChatMessageID'], $bob, 'not mine')
);
expectThrows(
    'an empty edit is refused',
    fn () => $messageService->edit((int) $message['tawasulChatMessageID'], $alice, '  ')
);

$messageService->deleteForEveryone((int) $message['tawasulChatMessageID'], $alice, 60);
$stored = $messages->find((int) $message['tawasulChatMessageID']);
check('delete for everyone keeps the row', $stored !== null);
check('delete for everyone clears the content', $stored['content'] === null);
check('delete for everyone records when', !empty($stored['deletedAt']));

$replyStill = $messages->find((int) $reply['tawasulChatMessageID']);
check('a reply to it is not broken', $replyStill !== null);

// ------------------------------------------------------------- visibility

echo "\n6. Who can see what\n";

expectThrows('someone outside cannot read the conversation', fn () => $messageService->conversation($chatID, $carol));
expectThrows('someone outside cannot read the header', fn () => $chatService->header($chatID, $carol));
expectThrows('someone outside cannot react', fn () => $messageService->react((int) $reply['tawasulChatMessageID'], $carol, '👍'));
expectThrows('someone outside cannot star', fn () => $messageService->star((int) $reply['tawasulChatMessageID'], $carol, true));

$bobView = $messageService->conversation($chatID, $bob);
$bobIDs = array_column($bobView['messages'], 'tawasulChatMessageID');
check('the recipient sees the message', in_array((int) $reply['tawasulChatMessageID'], array_map('intval', $bobIDs), true));

$carolResults = $messages->search($carol, $searchNeedle);
check('search is scoped to the searcher\'s conversations', count($carolResults) === 0, 'found '.count($carolResults));

$aliceResults = $messages->search($alice, $searchNeedle);
check('the sender does find it in their own conversation', count($aliceResults) >= 1);

// ---------------------------------------------------------------- groups

echo "\n7. Groups\n";

$groupID = $chatService->createGroup($alice, 'Test Group', [$bob, $carol]);
$createdChats[] = $groupID;

check('the group has three members', $chats->participantCount($groupID) === 3);
check('the creator owns it', $chats->roleOf($groupID, $alice) === 'owner');
check('the others are members', $chats->roleOf($groupID, $bob) === 'member');

expectThrows('a group needs a name', fn () => $chatService->createGroup($alice, '   ', [$bob]));
// Distinct ids, because the service collapses duplicates and a list of the same
// person repeated is not an oversized group.
$tooMany = [];
for ($i = 1; $i <= $settings->getInt('maxGroupSize', 2, 1000) + 5; $i++) {
    $tooMany[] = ChatGateway::pad((string) $i);
}
expectThrows('the size limit is enforced', fn () => $chatService->createGroup($alice, 'Too Big', $tooMany));

expectThrows('a member cannot rename a group', fn () => $chatService->renameGroup($groupID, $bob, 'Renamed'));
$chatService->renameGroup($groupID, $alice, 'Renamed Group', 'A description');
check('an owner can rename it', $chats->find($groupID)['name'] === 'Renamed Group');

$chatService->promote($groupID, $alice, $bob, 'admin');
check('the owner can promote somebody', $chats->roleOf($groupID, $bob) === 'admin');

expectThrows('an admin cannot promote', fn () => $chatService->promote($groupID, $bob, $carol, 'admin'));
expectThrows(
    'an unlisted disappearing window is refused rather than silently turned off',
    fn () => $chatService->setDisappearing($groupID, $alice, 12345)
);
$chatService->setDisappearing($groupID, $alice, 86400);
check('a listed window is accepted', (int) $chats->find($groupID)['disappearingMinutes'] === 86400);

$chatService->removeMember($groupID, $alice, $carol);
check('an admin can remove somebody', !$chats->isParticipant($groupID, $carol));
check('a removed person is marked as having left, not deleted', (int) $db->query(
    'SELECT COUNT(*) FROM `tawasulChatParticipant` WHERE tawasulChatID = '.(int) $groupID." AND tawasulPersonID = '".$carol."'"
)->fetchColumn() === 1);

$chatService->removeMember($groupID, $carol, $carol);
check('a removed person cannot leave a conversation they are not in', !$chats->isParticipant($groupID, $carol));

// The owner cannot be removed, because the owner is the only role that can
// promote an admin or rename the chat: a group whose owner has been removed can
// never be brought under control again by anybody.
$ownedGroup = $chatService->createGroup($alice, 'Owned', [$bob]);
$createdChats[] = $ownedGroup;
$chatService->promote($ownedGroup, $alice, $bob, 'admin');
expectThrows(
    'an admin cannot remove the owner',
    fn () => $chatService->removeMember($ownedGroup, $bob, $alice)
);
check('a refused owner removal leaves the owner in place', $chats->roleOf($ownedGroup, $alice) === 'owner');

$chatService->removeMember($ownedGroup, $alice, $bob);
check('the owner can still remove an admin', !$chats->isParticipant($ownedGroup, $bob));

// Leaving is always allowed, including as the last person in the group:
// someone who wants out must never be trapped in it.
$soloGroup = $chatService->createGroup($alice, 'Solo', [$bob]);
$createdChats[] = $soloGroup;
$chatService->removeMember($soloGroup, $alice, $bob);
check('removing the other person leaves one', $chats->participantCount($soloGroup) === 1);
$chatService->removeMember($soloGroup, $alice, $alice);
check('a person can still leave even as the last member', $chats->participantCount($soloGroup) === 0);

// Removing somebody who is not in the group asks for nothing, so it must not be
// answered with the last-member refusal: that message would describe a request
// the caller never made.
$strangerGroup = $chatService->createGroup($alice, 'Strangers', [$bob]);
$createdChats[] = $strangerGroup;
$chatService->removeMember($strangerGroup, $alice, $carol);
check('removing a non-member is a no-op rather than an error', true);
check('a non-member was not removed from anywhere', !$chats->isParticipant($strangerGroup, $carol));

// ------------------------------------------------------- disappearing msgs

echo "\n8. Disappearing messages\n";

$vanishing = $chatService->createGroup($alice, 'Vanishing', [$bob]);
$createdChats[] = $vanishing;
$chatService->setDisappearing($vanishing, $alice, 60);

$old = $messageService->send($vanishing, $alice, ['type' => 'text', 'content' => 'Should vanish']);
$db->prepare('UPDATE `tawasulChatMessage` SET timestampCreated = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE tawasulChatMessageID = ?')
    ->execute([(int) $old['tawasulChatMessageID']]);

$housekeeper = new Housekeeper($db, $settings, $store);
$housekeeper->sweep((int) $vanishing);
check('an expired message is swept', $messages->find((int) $old['tawasulChatMessageID']) === null);

$fresh = $messageService->send($vanishing, $alice, ['type' => 'text', 'content' => 'Should stay']);
$housekeeper = new Housekeeper($db, $settings, $store);
$housekeeper->sweep((int) $vanishing);
check('a message inside the window stays', $messages->find((int) $fresh['tawasulChatMessageID']) !== null);

// ------------------------------------------------------------- long poll

echo "\n9. Delivery\n";

$since = $clock->ago(5);
$polled = $messageService->send($chatID, $alice, ['type' => 'text', 'content' => 'For the poll '.uniqid()]);

$change = $transport->changesSince($bob, $since);
$pollIDs = array_map('intval', array_column($change['messages'], 'tawasulChatMessageID'));
check('the poll reports the new message to the recipient', in_array((int) $polled['tawasulChatMessageID'], $pollIDs, true));

// Carried by a chat only bob is in, so the sender's own copy is the only thing
// that can tell an echo apart from a genuine new arrival. Bob's own message
// coming back to bob is what proves the poll delivers what it should: a sender
// whose client never sees their own message has no bubble to reconcile against.
$bobOnly = $chatService->openDirectChat($alice, $bob);
$createdChats[] = $bobOnly;
$ownEcho = $messageService->send($chatID, $bob, ['type' => 'text', 'content' => 'Bob speaking '.uniqid()]);

$change = $transport->changesSince($bob, $change['serverTime']);
$pollIDs = array_map('intval', array_column($change['messages'], 'tawasulChatMessageID'));
check(
    'the poll reports a message back to the person who sent it',
    in_array((int) $ownEcho['tawasulChatMessageID'], $pollIDs, true)
);

// Someone who was removed from every one of their conversations gets nothing at
// all: the poll is scoped by membership, not by message id.
$excluded = $transport->changesSince('0009999999', $since);
check('the poll reports nothing to a non-member', $excluded['messages'] === [], 'found '.count($excluded['messages']));

$change = $transport->changesSince($carol, $since);
check('the poll reports nothing to someone with no part in it', $change['messages'] === [], 'found '.count($change['messages']));

// The cursor deliberately overlaps by a second so a message written in the same
// second as the cursor is not skipped. The consequence is that a poll can repeat
// the boundary second: what must never happen is a genuinely new message id
// appearing twice, so that is what is asserted.
$repeat = $transport->changesSince($bob, $change['serverTime']);
$repeatIDs = array_map('intval', array_column($repeat['messages'], 'tawasulChatMessageID'));
check(
    'a poll after the cursor repeats only the overlap second',
    array_diff($repeatIDs, $pollIDs) === [],
    'new ids: '.implode(',', array_diff($repeatIDs, $pollIDs))
);

// The overlap is anchored to the clock, not to the last row delivered, so while
// the database clock has not moved on the same boundary second comes back each
// time. That is stable rather than growing, and the client replaces by id, which
// is why it costs a re-render rather than duplicates. What must not happen is the
// set of ids creeping upwards.
$settled = $transport->changesSince($bob, $repeat['serverTime']);
$settledIDs = array_map('intval', array_column($settled['messages'], 'tawasulChatMessageID'));
check(
    'a stationary clock repeats the same rows rather than accumulating',
    array_diff($settledIDs, $repeatIDs) === [],
    'new ids: '.implode(',', array_diff($settledIDs, $repeatIDs))
);
check(
    'each id appears once, not twice',
    count($settledIDs) === count(array_unique($settledIDs)),
    'found '.count($settledIDs).' rows, '.count(array_unique($settledIDs)).' distinct'
);

// Presence and typing.
$presence->beat($bob, true);
$presence->typing((int) $groupID, $bob);
$typing = $presence->whoIsTyping((int) $groupID, $alice);
check('the recipient sees who is typing', count($typing) === 1, 'found '.count($typing));

$typing = $presence->whoIsTyping((int) $groupID, $bob);
check('the typer does not see themselves typing', count($typing) === 0);

$presence->stoppedTyping($bob);
check('stopping clears the indicator', count($presence->whoIsTyping((int) $groupID, $alice)) === 0);

// Presence is per person and shared between everyone who shares a chat with
// them, so bob's own ping is what should come back. Asking about alice would test
// whether alice is online, which the test never made her.
$found = $presence->forPeople([$bob]);
$bobRow = null;
foreach ($found as $row) {
    if ((string) $row['tawasulPersonID'] === $bob) {
        $bobRow = $row;
    }
}
check('presence reports the recent ping as online', $bobRow !== null && $bobRow['status'] === 'online');

// ----------------------------------------------------------- file safety

echo "\n10. Files\n";

check('an image extension is treated as an image', AttachmentStore::kindOf('png') === 'image');
check('a video extension is treated as a video', AttachmentStore::kindOf('mp4') === 'video');
check('a voice note extension is treated as audio', AttachmentStore::kindOf('m4a') === 'audio');
check('a pdf is treated as a file', AttachmentStore::kindOf('pdf') === 'file');

check('a traversal path is refused', $store->resolveForRead('uploads/../../tawasul.php') === null);
check('an absolute path is refused', $store->resolveForRead('/etc/passwd') === null);
check('an executable extension is refused', $store->resolveForRead('uploads/2026/01/chat_1_x.php') === null);
check('an empty path is refused', $store->resolveForRead('') === null);

$existing = $store->resolveForRead('uploads/');
check('a directory is refused', $existing === null);

expectThrows('a forbidden upload type is refused', function () use ($store, $alice) {
    $store->storeBlob('<?php echo 1;', 'php', 'payload.php', $alice);
}, RuntimeException::class);

expectThrows('an empty upload is refused', function () use ($store, $alice) {
    $store->storeBlob('', 'png', 'empty.png', $alice);
}, RuntimeException::class);

$voice = $store->storeBlob('ID3 fake audio bytes for the test', 'm4a', 'note.m4a', $alice);
check('a voice note is stored under uploads', str_starts_with($voice['filePath'], 'uploads/'));
check('it resolves back for reading', $store->resolveForRead($voice['filePath']) !== null);

$voiceMessage = $messageService->send($chatID, $alice, [
    'type' => 'audio',
    'content' => '',
    'durationSeconds' => 7,
    'attachments' => [$voice],
]);
check('a voice note can be sent as a message', count($voiceMessage['attachments']) === 1);
check('its duration is kept', (int) $voiceMessage['durationSeconds'] === 7);

$store->remove($voice['filePath']);
check('removing it takes the file off disk', $store->resolveForRead($voice['filePath']) === null);

// -------------------------------------------------------------- forward

echo "\n11. Forwarding\n";

$forwarded = $messageService->forward((int) $reply['tawasulChatMessageID'], $strangerChat, $alice, 'Look at this');
check('a message can be forwarded', (int) $forwarded['tawasulChatMessageID'] > 0);
$stored = $messages->find((int) $forwarded['tawasulChatMessageID']);
check('the copy records where it came from', (int) $stored['forwardedFromID'] === (int) $reply['tawasulChatMessageID']);

expectThrows(
    'someone outside cannot forward a message they cannot see',
    fn () => $messageService->forward($replyMessageID, $groupID, $carol)
);

// The picker sends one message to several conversations in a single request,
// which is the case a per-call source check handles badly.
$manyTargets = $messageService->forwardTo($replyMessageID, [$strangerChat, $groupID], $alice);
check('a message can be forwarded to several conversations at once', count($manyTargets) === 2, 'found '.count($manyTargets));
foreach ($manyTargets as $sent) {
    $storedCopy = $messages->find((int) $sent['tawasulChatMessageID']);
    check(
        'each copy records where it came from',
        (int) $storedCopy['forwardedFromID'] === $replyMessageID
    );
}
check(
    'the same target twice is sent to once',
    count($messageService->forwardTo($replyMessageID, [$strangerChat, $strangerChat], $alice)) === 1
);

expectThrows(
    'forwarding to nobody is refused',
    fn () => $messageService->forwardTo($replyMessageID, [], $alice)
);
expectThrows(
    'a batch naming a conversation the person is not in sends nothing at all',
    function () use ($messageService, $replyMessageID, $strangerChat, $carol) {
        // $strangerChat is alice and carol's; carol may not forward into it.
        $messageService->forwardTo($replyMessageID, [$strangerChat, $strangerChat], $carol);
    }
);

// ---------------------------------------------------- message info & pins

echo "\n12. Message info, pinned messages and drafts\n";

$infoChat = $chatService->createGroup($alice, 'Info Group', [$bob, $carol]);
$createdChats[] = $infoChat;

$infoMessage = $messageService->send($infoChat, $alice, ['type' => 'text', 'content' => 'Who has read this?']);
$infoMessageID = (int) $infoMessage['tawasulChatMessageID'];

$messageService->markRead($infoChat, $bob);
$messageService->markRead($infoChat, $carol);

$info = $messageService->messageInfo($infoMessageID, $alice);
check('the info screen counts only the recipients', (int) $info['recipientCount'] === 2, 'found '.$info['recipientCount']);
check('both have read it', (int) $info['readCount'] === 2, 'found '.$info['readCount']);

$named = [];
foreach ($info['recipients'] as $recipient) {
    $named[(string) $recipient['tawasulPersonID']] = $recipient;
}
check('the sender is not listed as their own recipient', !isset($named[$alice]));
check('a reader is marked as read', isset($named[$bob]) && !empty($named[$bob]['readAt']));
check('each recipient carries a display name', isset($named[$carol]['title']) && $named[$carol]['title'] !== '');

// Somebody who joined after the message was sent did not receive it, and the
// screen has to say pending rather than quietly omit them.
$latecomer = $chatService->createGroup($alice, 'Late', [$bob]);
$createdChats[] = $latecomer;
$lateMessage = $messageService->send($latecomer, $alice, ['type' => 'text', 'content' => 'Sent first']);
$chatService->addMembers($latecomer, $alice, [$carol]);
$lateInfo = $messageService->messageInfo((int) $lateMessage['tawasulChatMessageID'], $alice);
$pending = array_filter($lateInfo['recipients'], fn ($r) => empty($r['readAt']));
check('someone who joined later shows as pending', count($pending) === 2, 'found '.count($pending));
check('a pending recipient is counted in nobody\'s read total', (int) $lateInfo['readCount'] === 0);

expectThrows(
    'only the sender can see who read a message',
    fn () => $messageService->messageInfo($infoMessageID, $bob)
);
expectThrows(
    'someone outside cannot ask for message info',
    fn () => $messageService->messageInfo((int) $reply['tawasulChatMessageID'], '0009999999')
);

// Pinning. A pin belongs to the conversation, so a member sees one set too.
$messageService->pin($infoMessageID, $alice, true);
$pinned = $messageService->pinnedMessages($infoChat, $bob);
check('a member sees the pin the sender set', count($pinned) === 1, 'found '.count($pinned));
check('the pin names its sender', ($pinned[0]['senderTitle'] ?? '') !== '');

$pinningAgain = $messageService->pin($infoMessageID, $alice, true);
check('pinning twice does not duplicate', count($messageService->pinnedMessages($infoChat, $bob)) === 1);

$aliceView = $messageService->conversation($infoChat, $alice);
$flagged = array_filter($aliceView['messages'], fn ($m) => (int) $m['tawasulChatMessageID'] === $infoMessageID);
check('the bubble is flagged as pinned', !empty($flagged[0]['pinned']));

// The cap. A message is already pinned above, so it is unpinned first to give
// the loop a full set of slots rather than one fewer than it looks.
$messageService->pin($infoMessageID, $alice, false);

$pinCandidates = [];
for ($i = 0; $i < MessageService::MAX_PINS; $i++) {
    $sent = $messageService->send($infoChat, $alice, ['type' => 'text', 'content' => 'Pinnable '.$i]);
    $pinCandidates[] = (int) $sent['tawasulChatMessageID'];
    $messageService->pin((int) $sent['tawasulChatMessageID'], $alice, true);
}
check('the pin list stops at the cap', count($messageService->pinnedMessages($infoChat, $alice)) === MessageService::MAX_PINS);

$overflow = $messageService->send($infoChat, $alice, ['type' => 'text', 'content' => 'One too many']);
expectThrows(
    'pinning past the cap is refused',
    fn () => $messageService->pin((int) $overflow['tawasulChatMessageID'], $alice, true)
);

// Re-pinning a message that is already in the strip must not be treated as a new
// pin: another member having pinned it is the likely reason a client is asking.
$messageService->pin($pinCandidates[0], $alice, true);
check('re-pinning a pinned message is allowed', count($messageService->pinnedMessages($infoChat, $alice)) === MessageService::MAX_PINS);

$messageService->pin($pinCandidates[0], $alice, false);
check('unpinning frees a slot', count($messageService->pinnedMessages($infoChat, $alice)) === MessageService::MAX_PINS - 1);

$messageService->pin($infoMessageID, $alice, true);
check('a freed slot can be used', count($messageService->pinnedMessages($infoChat, $alice)) === MessageService::MAX_PINS);
$messageService->pin($infoMessageID, $alice, false);
check('unpinning removes it from the strip', count($messageService->pinnedMessages($infoChat, $alice)) === MessageService::MAX_PINS - 1);

expectThrows(
    'someone outside cannot pin',
    fn () => $messageService->pin($infoMessageID, '0009999999', true)
);
expectThrows(
    'a deleted message cannot be pinned',
    function () use ($messageService, $chatID, $alice) {
        $doomed = $messageService->send($chatID, $alice, ['type' => 'text', 'content' => 'Doomed']);
        $messageService->deleteForEveryone((int) $doomed['tawasulChatMessageID'], $alice, 60);
        $messageService->pin((int) $doomed['tawasulChatMessageID'], $alice, true);
    }
);

// Drafts. Stored per person, so one person's draft never reaches another's.
$draftChat = $chatService->createGroup($alice, 'Drafts', [$bob]);
$createdChats[] = $draftChat;

$messageService->saveDraft($draftChat, $alice, 'Half a thought');
$aliceDraft = $messageService->draft($draftChat, $alice);
check('a draft is stored', $aliceDraft !== null && $aliceDraft['content'] === 'Half a thought');
check('another member has no draft', $messageService->draft($draftChat, $bob) === null);

$messageService->saveDraft($draftChat, $alice, 'A finished thought');
check('saving again replaces rather than duplicates', $messageService->draft($draftChat, $alice)['content'] === 'A finished thought');

$draftMessage = $messageService->send($draftChat, $alice, ['type' => 'text', 'content' => 'Sent at last']);
check('sending clears the draft', $messageService->draft($draftChat, $alice) === null);

$messageService->saveDraft($draftChat, $alice, '   ');
check('a blank draft is not stored', $messageService->draft($draftChat, $alice) === null);

// A draft quoting a message from another conversation would reopen with a quote
// strip showing a message the person may not read.
$messageService->saveDraft($draftChat, $alice, 'Quoting something', $replyMessageID);
check('a draft cannot quote another conversation', $messageService->draft($draftChat, $alice)['replyToMessageID'] === null);

// Forward targets for the picker. Archived conversations are included, because
// forwarding to somebody you had archived still has to work.
$chats->setParticipantFlag($draftChat, $alice, 'archived', 'Y');
$targets = $chatService->forwardTargets($alice);
$targetIDs = array_column($targets, 'chatID');
check('the picker lists conversations', in_array($draftChat, $targetIDs, true), 'missing '.$draftChat);
check('an archived conversation is still offered', in_array($draftChat, $targetIDs, true));

$namedTargets = [];
foreach ($targets as $target) {
    $namedTargets[$target['chatID']] = $target;
}
check(
    'a one-to-one target is named after the other person',
    trim((string) $namedTargets[$strangerChat]['title']) !== '',
    'got '.($namedTargets[$strangerChat]['title'] ?? 'nothing')
);
check(
    'a group target is named after the group',
    ($namedTargets[$draftChat]['title'] ?? '') === 'Drafts'
);

// A pin left behind by a swept message would render as a blank strip entry, and a
// draft quoting one would reopen as an empty quote.
$pinVanishing = $chatService->createGroup($alice, 'Pins Vanish', [$bob]);
$createdChats[] = $pinVanishing;
$chatService->setDisappearing($pinVanishing, $alice, 60);
$toPin = $messageService->send($pinVanishing, $alice, ['type' => 'text', 'content' => 'Briefly here']);
$messageService->pin((int) $toPin['tawasulChatMessageID'], $alice, true);
$messageService->saveDraft($pinVanishing, $bob, 'About to vanish', (int) $toPin['tawasulChatMessageID']);

$db->prepare('UPDATE `tawasulChatMessage` SET timestampCreated = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE tawasulChatMessageID = ?')
    ->execute([(int) $toPin['tawasulChatMessageID']]);

$housekeeper = new Housekeeper($db, $settings, $store);
$housekeeper->sweep($pinVanishing);

check('sweeping removes the pin', count($messageService->pinnedMessages($pinVanishing, $alice)) === 0);
// array_key_exists rather than ??: the point of the assertion is that the column
// is present and null, and ?? cannot tell those two cases apart from absent.
$dbgDraft = $messageService->draft($pinVanishing, $bob);
check(
    'sweeping clears a draft quote pointing at the swept message',
    $dbgDraft !== null && array_key_exists('replyToMessageID', $dbgDraft) && $dbgDraft['replyToMessageID'] === null
);
check('the draft text itself survives the sweep', ($dbgDraft['content'] ?? '') === 'About to vanish');

// ----------------------------------------------------------------- report

echo "\n";
echo "----------------------------------------\n";
echo "  $passed passed, $failed failed\n";

if ($failures !== []) {
    echo "\n  failures:\n";
    foreach ($failures as $failure) {
        echo "    - $failure\n";
    }
}

exit($failed === 0 ? 0 : 1);
