<?php
/*
TawasulChat — the chat screen.

The page renders the conversation shell and the first copy of the chat list on
the server, so the screen is usable before any JavaScript request completes and
so it still works as a plain page if the polling endpoint is unreachable. From
then on the client takes over: it re-renders the list, holds a poll open for new
messages, and swaps the conversation pane in place rather than reloading.

The endpoint, the CSRF token and the person's own ID are handed to the client as
one JSON block rather than as hidden form fields, because none of them are form
values and reading them out of the DOM would mean querying for elements whose
only job is to carry data.
*/

use Tos\Module\TawasulChat\Service\MessageService;

require_once __DIR__.'/chatFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/messenger_chat.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    echo '<p>'.__('You do not have access to this action.').'</p>';

    return;
}

$page->breadcrumbs->add(__('Chat'));
$page->stylesheets->add('tos-chat', 'modules/TawasulMessenger/css/chat.css');
$page->scripts->add('tos-chat', 'modules/TawasulMessenger/js/messenger_chat.js');

$personID = chatPersonID($session);
$absolutePath = $session->get('absolutePath');
$services = chatServices($connection2, $absolutePath);

$inbox = $services['chatService']->inbox($personID);

// Remember that the user has been here, so the badge on the way in is right.
$services['presence']->beat($personID, false);
$services['housekeeper']->clearStaleTyping();

/** Everything the client needs, in one place. */
$chatConfig = [
    'ajaxURL' => $session->get('absoluteURL').'/modules/TawasulMessenger/messenger_chat_ajax.php',
    'csrf' => $session->get('csrftoken'),
    'personID' => $personID,
    'pollTimeout' => $services['settings']->pollTimeout(),
    'maxUploadMB' => $services['settings']->getInt('attachmentMaxSizeMB', 1, 512),
    'features' => [
        'voiceNotes' => $services['settings']->isOn('voiceNotesEnabled'),
        'typing' => $services['settings']->isOn('typingIndicatorEnabled'),
        'readReceipts' => $services['settings']->isOn('readReceiptsEnabled'),
        'editing' => $services['settings']->isOn('editingEnabled'),
    ],
'quickReactions' => MessageService::QUICK_REACTIONS,
        'maxPins' => MessageService::MAX_PINS,
        'labels' => [
        'online' => __('online'),
        'away' => __('away'),
        'offline' => __('offline'),
        'lastSeen' => __('last seen %s'),
        'you' => __('You'),
        'today' => __('Today'),
        'yesterday' => __('Yesterday'),
        'delivered' => __('Delivered'),
        'read' => __('Read'),
        'typing' => __('typing…'),
        'editing' => __('edited'),
        'deleted' => __('This message was deleted'),
        'forwarded' => __('Forwarded'),
        'photo' => __('Photo'),
        'video' => __('Video'),
        'voiceNote' => __('Voice note'),
        'document' => __('Document'),
        'location' => __('Location'),
        'reply' => __('Reply'),
        'forward' => __('Forward'),
        'edit' => __('Edit'),
        'delete' => __('Delete'),
        'star' => __('Star'),
        'unstar' => __('Remove star'),
        'copy' => __('Copy'),
        'loadEarlier' => __('Load earlier messages'),
        'noChats' => __('No conversations yet. Start one to begin.'),
        'pickChat' => __('Choose a conversation'),
        'sending' => __('Sending…'),
        'failed' => __('Not sent'),
        'recording' => __('Recording'),
        'searchPlaceholder' => __('Search messages'),
        'newChat' => __('New chat'),
        'muted' => __('Muted'),
        'pinned' => __('Pinned'),
        'archived' => __('Archived'),
        'disappearing' => __('Disappearing messages'),

        // Message info, pinned messages, drafts and the forward picker. The
        // rest of the forward picker's own wording lives in its markup, which is
        // server-rendered, so it is translated with __() there rather than
        // shipped through this block.
        'messageInfo' => __('Message info'),
        'infoDelivered' => __('Delivered'),
        'infoRead' => __('Read'),
        'infoPending' => __('Pending'),
        'pinMessage' => __('Pin message'),
        'unpinMessage' => __('Unpin'),
        'selectedCount' => __('%d selected'),
        'noTargets' => __('No conversations to forward to yet.'),

        // The starred list and in-conversation search. Both read actions the
        // header offers; the wording is here because the results are rendered
        // by the client, and an empty result needs a sentence to stand in for
        // the list that did not come.
        'details' => __('Details'),
        'starredMessages' => __('Starred messages'),
        'searchMessages' => __('Search messages'),
        'searchHint' => __('Type at least two characters.'),
        'noStarred' => __('Nothing starred yet.'),
        'noMatches' => __('No messages matched.'),
    ],
    'initial' => [
        'chats' => $inbox['chats'],
        'totalUnread' => $inbox['totalUnread'],
        'since' => $services['clock']->now(),
    ],
];
?>

<script type="application/json" id="tos-chat-config"><?php
    echo json_encode($chatConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?></script>

<div class="tos-chat" id="tos-chat" data-chat-id="<?php echo isset($_GET['chat']) ? (int) $_GET['chat'] : 0; ?>">

    <!-- Conversation list. Rendered on the server, then maintained by the client. -->
    <aside class="tos-chat__list" id="tos-chat-list">
        <header class="tos-chat__list-header">
            <h1 class="tos-chat__brand"><?php echo __('Chat'); ?></h1>
            <div class="tos-chat__list-actions">
                <label class="tos-chat__search">
                    <span class="tos-chat__visually-hidden"><?php echo __('Search messages'); ?></span>
                    <input type="search" id="tos-chat-search" autocomplete="off"
                           placeholder="<?php echo __('Search messages'); ?>">
                </label>
                <?php if (isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/messenger_chat_new.php')): ?>
                    <a class="tos-chat__icon-button" id="tos-chat-new"
                       href="<?php echo $session->get('absoluteURL'); ?>/index.php?q=/modules/TawasulMessenger/messenger_chat_new.php"
                       title="<?php echo __('New chat'); ?>" aria-label="<?php echo __('New chat'); ?>">+</a>
                <?php endif; ?>
            </div>
        </header>

        <?php // Always emitted, not just for an empty inbox: chat.js binds this
              // element unconditionally and toggles its visibility on every
              // re-render, so omitting it once the person has a conversation made
              // that render throw and took the whole client down with it. ?>
        <p class="tos-chat__empty<?php echo $inbox['chats'] === [] ? '' : ' hidden'; ?>"
           id="tos-chat-empty"<?php echo $inbox['chats'] === [] ? '' : ' hidden'; ?>>
            <?php echo __('No conversations yet. Start one to begin.'); ?>
        </p>

        <ul class="tos-chat__conversations" id="tos-chat-conversations">
            <?php foreach ($inbox['chats'] as $row): ?>
                <li class="tos-chat__conversation<?php echo $row['unreadCount'] > 0 ? ' is-unread' : ''; ?>"
                    data-chat-id="<?php echo (int) $row['tawasulChatID']; ?>">
                    <a href="<?php echo $session->get('absoluteURL'); ?>/index.php?q=/modules/TawasulMessenger/messenger_chat.php&amp;chat=<?php echo (int) $row['tawasulChatID']; ?>">
                        <span class="tos-chat__avatar" aria-hidden="true">
                            <?php if (!empty($row['avatar'])): ?>
                                <img src="<?php echo chatAvatar($row['avatar']); ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <?php echo chatInitials($row['title'] ?? '', ''); ?>
                            <?php endif; ?>
                        </span>
                        <span class="tos-chat__conversation-body">
                            <span class="tos-chat__conversation-top">
                                <span class="tos-chat__title"><?php echo htmlspecialchars($row['title'] ?? ''); ?></span>
                                <span class="tos-chat__time"></span>
                            </span>
                            <span class="tos-chat__conversation-bottom">
                                <span class="tos-chat__preview"><?php echo htmlspecialchars($row['lastMessagePreview']); ?></span>
                                <?php if ($row['unreadCount'] > 0): ?>
                                    <span class="tos-chat__badge"><?php echo (int) $row['unreadCount']; ?></span>
                                <?php endif; ?>
                            </span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>

    <!-- Conversation pane. Populated by the client. -->
    <section class="tos-chat__thread" id="tos-chat-thread" hidden>
        <header class="tos-chat__thread-header" id="tos-chat-thread-header"></header>

        <!-- Pinned messages. Collapsed to a single line until tapped, the way a
             phone does it, so three pins never cost more than one row. -->
        <div class="tos-chat__pinned" id="tos-chat-pinned" hidden>
            <button type="button" class="tos-chat__icon-button tos-chat__pinned-toggle"
                    id="tos-chat-pinned-toggle" aria-expanded="false">
                <span class="tos-chat__pinned-label"><?php echo __('Pinned'); ?></span>
            </button>
            <ul class="tos-chat__pinned-list" id="tos-chat-pinned-list"></ul>
            <button type="button" class="tos-chat__icon-button" id="tos-chat-pinned-close"
                    aria-label="<?php echo __('Close'); ?>">&times;</button>
        </div>

        <div class="tos-chat__scroller" id="tos-chat-scroller">
            <div class="tos-chat__messages" id="tos-chat-messages"></div>
        </div>

        <div class="tos-chat__replying" id="tos-chat-replying" hidden>
            <div class="tos-chat__replying-body">
                <span class="tos-chat__replying-name"></span>
                <span class="tos-chat__replying-text"></span>
            </div>
            <button type="button" class="tos-chat__icon-button" id="tos-chat-reply-cancel"
                    aria-label="<?php echo __('Cancel'); ?>">&times;</button>
        </div>

        <footer class="tos-chat__composer">
            <form id="tos-chat-form" autocomplete="off">
                <input type="hidden" id="tos-chat-reply-id" value="">
                <button type="button" class="tos-chat__icon-button" id="tos-chat-attach"
                        title="<?php echo __('Attach a file'); ?>" aria-label="<?php echo __('Attach a file'); ?>">+</button>
                <input type="file" id="tos-chat-file" hidden
                       accept="image/*,video/*,audio/*,.pdf,.txt,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.zip">

                <textarea id="tos-chat-input" rows="1"
                          placeholder="<?php echo __('Write a message'); ?>"
                          aria-label="<?php echo __('Write a message'); ?>"></textarea>

                <button type="button" class="tos-chat__icon-button" id="tos-chat-location"
                        title="<?php echo __('Share a location'); ?>" aria-label="<?php echo __('Share a location'); ?>"
                        hidden>?</button>

                <?php if ($services['settings']->isOn('voiceNotesEnabled')): ?>
                    <button type="button" class="tos-chat__icon-button" id="tos-chat-record"
                            title="<?php echo __('Record a voice note'); ?>"
                            aria-label="<?php echo __('Record a voice note'); ?>">&#9679;</button>
                <?php endif; ?>

                <button type="submit" class="tos-chat__send" id="tos-chat-send"
                        aria-label="<?php echo __('Send'); ?>">&#10148;</button>
            </form>
            <p class="tos-chat__composer-hint" id="tos-chat-recording-hint" hidden>
                <?php echo __('Recording — press again to stop and send.'); ?>
            </p>
        </footer>
    </section>

    <section class="tos-chat__empty-state" id="tos-chat-empty-state">
        <p><?php echo __('Choose a conversation'); ?></p>
    </section>

    <!-- Media viewer, shown when a photo or video is opened. -->
    <div class="tos-chat__lightbox" id="tos-chat-lightbox" hidden>
        <button type="button" class="tos-chat__lightbox-close" id="tos-chat-lightbox-close"
                aria-label="<?php echo __('Close'); ?>">&times;</button>
        <div class="tos-chat__lightbox-body" id="tos-chat-lightbox-body"></div>
    </div>

    <!-- Message info: who a message reached, and who has read it. -->
    <div class="tos-chat__modal" id="tos-chat-info" hidden role="dialog"
         aria-modal="true" aria-labelledby="tos-chat-info-title">
        <div class="tos-chat__modal-panel">
            <header class="tos-chat__modal-header">
                <h2 id="tos-chat-info-title"><?php echo __('Message info'); ?></h2>
                <button type="button" class="tos-chat__icon-button" id="tos-chat-info-close"
                        aria-label="<?php echo __('Close'); ?>">&times;</button>
            </header>
            <p class="tos-chat__info-summary" id="tos-chat-info-summary"></p>
            <ul class="tos-chat__info-list" id="tos-chat-info-list"></ul>
        </div>
    </div>

    <!-- Forward picker. Tapping a row selects it, and several rows can be
         selected at once so one message can go to several conversations. -->
    <div class="tos-chat__modal" id="tos-chat-forward" hidden role="dialog"
         aria-modal="true" aria-labelledby="tos-chat-forward-title">
        <div class="tos-chat__modal-panel">
            <header class="tos-chat__modal-header">
                <h2 id="tos-chat-forward-title"><?php echo __('Forward to'); ?></h2>
                <button type="button" class="tos-chat__icon-button" id="tos-chat-forward-close"
                        aria-label="<?php echo __('Close'); ?>">&times;</button>
            </header>
            <label class="tos-chat__visually-hidden" for="tos-chat-forward-search">
                <?php echo __('Search name or group'); ?>
            </label>
            <input type="search" id="tos-chat-forward-search" autocomplete="off"
                   placeholder="<?php echo __('Search name or group'); ?>">
            <label class="tos-chat__visually-hidden" for="tos-chat-forward-note">
                <?php echo __('Add a comment (optional)'); ?>
            </label>
            <input type="text" id="tos-chat-forward-note" autocomplete="off"
                   placeholder="<?php echo __('Add a comment (optional)'); ?>">
            <ul class="tos-chat__forward-list" id="tos-chat-forward-list"></ul>
            <footer class="tos-chat__modal-footer">
                <span id="tos-chat-forward-count"></span>
                <button type="button" class="tos-chat__send" id="tos-chat-forward-send" disabled>
                    <?php echo __('Forward'); ?>
                </button>
            </footer>
        </div>
    </div>

    <!-- Starred messages. Opened from the header, listing every message this
person starred in any conversation. Selecting one jumps to it, switching
         conversation first if it lives somewhere else. -->
    <div class="tos-chat__modal" id="tos-chat-starred" hidden role="dialog"
         aria-modal="true" aria-labelledby="tos-chat-starred-title">
        <div class="tos-chat__modal-panel">
            <header class="tos-chat__modal-header">
                <h2 id="tos-chat-starred-title"><?php echo __('Starred messages'); ?></h2>
                <button type="button" class="tos-chat__icon-button" id="tos-chat-starred-close"
                        aria-label="<?php echo __('Close'); ?>">&times;</button>
            </header>
            <ul class="tos-chat__result-list" id="tos-chat-starred-list"></ul>
        </div>
    </div>

    <!-- Search within the messages this person can see. Server-rendered input
         label and placeholder; results arrive from the search endpoint. -->
    <div class="tos-chat__modal" id="tos-chat-search-modal" hidden role="dialog"
         aria-modal="true" aria-labelledby="tos-chat-search-modal-title">
        <div class="tos-chat__modal-panel">
            <header class="tos-chat__modal-header">
                <h2 id="tos-chat-search-modal-title"><?php echo __('Search messages'); ?></h2>
                <button type="button" class="tos-chat__icon-button" id="tos-chat-search-modal-close"
                        aria-label="<?php echo __('Close'); ?>">&times;</button>
            </header>
            <label class="tos-chat__visually-hidden" for="tos-chat-search-input">
                <?php echo __('Search messages'); ?>
            </label>
            <input type="search" id="tos-chat-search-input" autocomplete="off"
                   placeholder="<?php echo __('Search messages'); ?>">
            <ul class="tos-chat__result-list" id="tos-chat-search-results"></ul>
        </div>
    </div>
</div>

