/*
TawasulChat — conversation client.

Vanilla JavaScript, no build step, because the platform has no bundler and a
chat screen is the one place where a framework would be slowest to arrive.

Three things carry the behaviour:

  * the poll loop, which holds a request open until the server has something to
    say, so new messages arrive without the client asking on a timer;
  * a cursor (`since`) that the server advances, which is what makes polling
    lossless — see the note in LongPollTransport;
  * optimistic send, which draws the bubble immediately and reconciles it with
    the server's copy, because waiting a round trip to show a message makes the
    whole thing feel slow even when it is fast.

Every value from a person is escaped before it reaches the DOM. Message bodies,
names, file names and location labels are all attacker-controlled in the sense
that matters here: one person in a group should not be able to run script in
everyone else's browser.
*/

(function () {
    'use strict';

    var config = JSON.parse(document.getElementById('tos-chat-config').textContent);
    var root = document.getElementById('tos-chat');
    var L = config.labels;

    var el = {
        list: document.getElementById('tos-chat-conversations'),
        empty: document.getElementById('tos-chat-empty'),
        emptyState: document.getElementById('tos-chat-empty-state'),
        thread: document.getElementById('tos-chat-thread'),
        header: document.getElementById('tos-chat-thread-header'),
        scroller: document.getElementById('tos-chat-scroller'),
        messages: document.getElementById('tos-chat-messages'),
        form: document.getElementById('tos-chat-form'),
        input: document.getElementById('tos-chat-input'),
        file: document.getElementById('tos-chat-file'),
        attach: document.getElementById('tos-chat-attach'),
        location: document.getElementById('tos-chat-location'),
        record: document.getElementById('tos-chat-record'),
        send: document.getElementById('tos-chat-send'),
        search: document.getElementById('tos-chat-search'),
        replying: document.getElementById('tos-chat-replying'),
        replyId: document.getElementById('tos-chat-reply-id'),
        replyName: document.querySelector('#tos-chat-replying .tos-chat__replying-name'),
        replyText: document.querySelector('#tos-chat-replying .tos-chat__replying-text'),
        replyCancel: document.getElementById('tos-chat-reply-cancel'),
        recordingHint: document.getElementById('tos-chat-recording-hint'),
        lightbox: document.getElementById('tos-chat-lightbox'),
        lightboxBody: document.getElementById('tos-chat-lightbox-body'),
        lightboxClose: document.getElementById('tos-chat-lightbox-close'),

        pinned: document.getElementById('tos-chat-pinned'),
        pinnedList: document.getElementById('tos-chat-pinned-list'),
        pinnedToggle: document.getElementById('tos-chat-pinned-toggle'),
        pinnedClose: document.getElementById('tos-chat-pinned-close'),

        info: document.getElementById('tos-chat-info'),
        infoSummary: document.getElementById('tos-chat-info-summary'),
        infoList: document.getElementById('tos-chat-info-list'),
        infoClose: document.getElementById('tos-chat-info-close'),

        forward: document.getElementById('tos-chat-forward'),
        forwardSearch: document.getElementById('tos-chat-forward-search'),
        forwardNote: document.getElementById('tos-chat-forward-note'),
        forwardList: document.getElementById('tos-chat-forward-list'),
        forwardCount: document.getElementById('tos-chat-forward-count'),
        forwardSend: document.getElementById('tos-chat-forward-send'),
        forwardClose: document.getElementById('tos-chat-forward-close'),

        starred: document.getElementById('tos-chat-starred'),
        starredList: document.getElementById('tos-chat-starred-list'),
        starredClose: document.getElementById('tos-chat-starred-close'),

        searchModal: document.getElementById('tos-chat-search-modal'),
        searchInput: document.getElementById('tos-chat-search-input'),
        searchResults: document.getElementById('tos-chat-search-results'),
        searchClose: document.getElementById('tos-chat-search-modal-close')
    };

    var state = {
        chats: config.initial.chats,
        chatID: parseInt(root.getAttribute('data-chat-id'), 10) || 0,
        messages: [],
        hasMore: false,
        since: config.initial.since,
        typingTimer: null,
        lastTypingSent: 0,
        polling: false,
        stopped: false,
        replyTo: null,
        tempCounter: 0,
        seen: {},
        // Receipt ids already applied. The poll cursor overlaps by a second, so
        // the same receipt can arrive twice; this is what keeps a bubble from
        // counting one reader twice.
        seenReceipts: {},
        searchTerm: '',
        chatType: 'individual',
        chatHeader: null,
        pinned: [],
        // Draft saving is debounced rather than posted per keystroke: a draft is
        // a safety net, not a live document, and writing it on every character
        // would put a database write behind each one.
        draftTimer: null,
        // What the forward picker is currently forwarding. null means closed.
        forwarding: null,

        // A starred or search result the reader asked to jump to, set before
        // switching conversation. The thread is fetched asynchronously, so the
        // jump can only run once the bubbles for it exist.
        pendingJump: null
    };

    // ---------------------------------------------------------------- helpers

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /** Only ever emit an attribute value that is a number or a known-safe id. */
    function safeId(value) {
        var n = parseInt(value, 10);
        return isNaN(n) ? '' : String(n);
    }

    /**
     * The id to put in data-message-id, for a real id or an optimistic one.
     *
     * safeId() is deliberately numeric — it is what stops a value from the
     * server breaking out of an attribute selector — but that made it reject
     * the temporary ids given to messages drawn before the server answers.
     * bubble() then wrote an empty attribute, so the querySelector looking for
     * 'tmp1' to replace the placeholder with the real message found nothing, the
     * placeholder stayed on screen, and every send left two bubbles: the
     * pending one and the confirmed one. An optimistic id is generated here and
     * never comes from the server, so it is matched as a prefix instead.
     */
    function domId(messageID) {
        var id = String(messageID === null || messageID === undefined ? '' : messageID);
        return /^tmp\d+$/.test(id) ? id : safeId(id);
    }

    /**
     * Messages oldest-first, which is the order a conversation is read in.
     *
     * The gateway already pages a conversation newest-first (ORDER BY id DESC)
     * and reverses it before returning, so a payload is oldest-first by the
     * time it reaches here. This used to reverse a second time, which put the
     * newest bubble at the top and made scrollToBottom() land on the oldest
     * message. It is a copy rather than the array in place so the payload the
     * caller still holds is not changed underneath it.
     *
     * The one thing that is genuinely newest-first and does need reversing is
     * the reply chain, which is built from the page already ordered.
     */
    function oldestFirst(messages) {
        return (messages || []).slice();
    }

    function post(action, fields, asFormData) {
        var body;
        if (asFormData) {
            body = fields;
        } else {
            body = new URLSearchParams();
            Object.keys(fields).forEach(function (key) {
                var value = fields[key];
                if (Array.isArray(value)) {
                    value.forEach(function (one) { body.append(key + '[]', one); });
                } else if (value !== null && value !== undefined) {
                    body.append(key, value);
                }
            });
        }
        body.append('chatAction', action);
        body.append('csrftoken', config.csrf);

        return fetch(config.ajaxURL, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: asFormData ? {} : { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error(L.failed);
            }).then(function (payload) {
                if (!response.ok || payload.ok === false) {
                    throw new Error(payload.error || L.failed);
                }
                return payload;
            });
        });
    }

    function get(action, params) {
        var query = new URLSearchParams(params || {});
        query.set('chatAction', action);
        return fetch(config.ajaxURL + '?' + query.toString(), {
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || payload.ok === false) {
                    throw new Error(payload.error || L.failed);
                }
                return payload;
            });
        });
    }

    function relativeTime(iso) {
        if (!iso) { return ''; }
        // The server sends 'Y-m-d H:i:s' in its own timezone, which is not
        // necessarily the reader's. Treating it as local is the same
        // simplification every server-rendered page in this platform makes, and
        // being one time out beats rendering a timestamp the user cannot read.
        var then = new Date(String(iso).replace(' ', 'T'));
        if (isNaN(then.getTime())) { return ''; }

        var seconds = Math.floor((Date.now() - then.getTime()) / 1000);
        if (seconds < 45) { return L.online; }
        if (seconds < 3600) { return Math.floor(seconds / 60) + 'm'; }
        if (seconds < 86400) { return Math.floor(seconds / 3600) + 'h'; }
        if (seconds < 604800) { return Math.floor(seconds / 86400) + 'd'; }

        return then.toLocaleDateString();
    }

    function clockTime(iso) {
        if (!iso) { return ''; }
        var then = new Date(String(iso).replace(' ', 'T'));
        if (isNaN(then.getTime())) { return ''; }
        return then.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function dayLabel(iso) {
        var then = new Date(String(iso).replace(' ', 'T'));
        if (isNaN(then.getTime())) { return ''; }

        var today = new Date();
        var yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);

        function sameDay(a, b) {
            return a.toDateString() === b.toDateString();
        }

        if (sameDay(then, today)) { return L.today; }
        if (sameDay(then, yesterday)) { return L.yesterday; }
        return then.toLocaleDateString();
    }

    function fileSizeLabel(bytes) {
        var kb = bytes / 1024;
        if (kb < 1024) { return Math.max(1, Math.round(kb)) + ' KB'; }
        return (kb / 1024).toFixed(1) + ' MB';
    }

    // ------------------------------------------------------------ chat list

    function renderList() {
        var visible = state.chats.filter(function (chat) {
            if (!state.searchTerm) { return true; }
            return String(chat.title || '').toLowerCase().indexOf(state.searchTerm) !== -1;
        });

        el.empty.hidden = state.chats.length > 0;

        el.list.innerHTML = visible.map(function (chat) {
            var id = safeId(chat.tawasulChatID);
            var tags = [];
            if (chat.pinned === 'Y') { tags.push(L.pinned); }
            if (chat.muted) { tags.push(L.muted); }
            if (parseInt(chat.disappearingMinutes, 10) > 0) { tags.push(L.disappearing); }

            var status = chat.type === 'individual' && chat.otherPerson
                ? presenceLabel(chat.otherPerson)
                : '';

            return '<li class="tos-chat__conversation' +
                    (chat.unreadCount > 0 ? ' is-unread' : '') +
                    (parseInt(id, 10) === state.chatID ? ' is-active' : '') +
                    '" data-chat-id="' + id + '">' +
                '<a href="#">' +
                    '<span class="tos-chat__avatar">' + avatarHtml(chat) + '</span>' +
                    '<span class="tos-chat__conversation-body">' +
                        '<span class="tos-chat__conversation-top">' +
                            '<span class="tos-chat__title">' + escapeHtml(chat.title || '') + '</span>' +
                            '<span class="tos-chat__time">' + escapeHtml(relativeTime(chat.timestampModified)) + '</span>' +
                        '</span>' +
                        '<span class="tos-chat__conversation-bottom">' +
                            '<span class="tos-chat__preview">' +
                                (status ? '<span class="tos-chat__tags">' + escapeHtml(status) + '</span> ' : '') +
                                escapeHtml(chat.lastMessagePreview || '') +
                            '</span>' +
                            (chat.unreadCount > 0
                                ? '<span class="tos-chat__badge">' + parseInt(chat.unreadCount, 10) + '</span>'
                                : '') +
                        '</span>' +
                    '</span>' +
                '</a></li>';
        }).join('');
    }

    function avatarHtml(chat) {
        if (chat.avatar) {
            return '<img src="' + escapeHtml(chat.avatar) + '" alt="" loading="lazy">';
        }
        var initials = String(chat.title || '?').trim().charAt(0).toUpperCase();
        return escapeHtml(initials || '?');
    }

    function presenceLabel(person) {
        if (!person) { return ''; }
        if (person.status === 'online') { return L.online; }
        if (person.status === 'away') { return L.away; }
        if (person.lastSeenAt) { return L.lastSeen.replace('%s', relativeTime(person.lastSeenAt)); }
        return L.offline;
    }

    // ---------------------------------------------------------- conversation

    function openConversation(chatID) {
        state.chatID = chatID;
        state.messages = [];
        state.replyTo = null;
        state.hasMore = false;
        hideReplyBar();

        root.classList.add('is-thread-open');
        el.thread.hidden = false;
        el.emptyState.hidden = true;
        el.messages.innerHTML = '<div class="tos-chat__system">' + escapeHtml(L.pickChat) + '</div>';

        renderList();
        markConversationSeen(chatID);

        get('messages', { chatID: chatID }).then(function (payload) {
            // A slower request for a conversation the user has already left must
            // not overwrite the one they are now looking at.
            if (state.chatID !== chatID) { return; }

            state.messages = oldestFirst(payload.messages);
            state.hasMore = payload.hasMore;
            state.chatHeader = payload.chat;
            state.chatType = payload.chat.type;
            state.pinned = payload.pinned || [];

            el.thread.hidden = false;
            el.messages.innerHTML = '';
            state.messages.forEach(function (message) { el.messages.appendChild(bubble(message)); });
            renderHeader(payload.chat, payload.typing || []);
            renderThreadExtras();
            renderPinned();
            restoreDraft(payload.draft);
            scrollToBottom(false);

            markRead(chatID);
            get('header', { chatID: chatID }).then(function (header) {
                if (state.chatID === chatID) {
                    state.chatHeader = header;
                    renderHeader(header, header.typing || []);
                }
            });

            // A jump requested from the starred list or search results before
            // this conversation was open. jumpToMessage pages backwards itself
            // if the message is not on this first page.
            if (state.pendingJump) {
                var wanted = state.pendingJump;
                state.pendingJump = null;
                jumpToMessage(wanted);
            }
        }).catch(function () {
            el.messages.innerHTML = '<div class="tos-chat__system">' + escapeHtml(L.failed) + '</div>';
        });
    }

    function renderHeader(header, typing) {
        var online = '';
        if (header.type === 'individual') {
            var other = (header.participants || []).filter(function (p) { return !p.isMe; })[0];
            if (other) {
                online = '<div class="tos-chat__thread-sub' + (other.status === 'online' ? ' is-online' : '') + '">' +
                    escapeHtml(presenceLabel(other)) + '</div>';
            }
        } else {
            var count = header.participantCount || 0;
            online = '<div class="tos-chat__thread-sub">' + count + '</div>';
        }

        el.header.innerHTML =
            '<span class="tos-chat__avatar">' + (header.avatar
                ? '<img src="' + escapeHtml(header.avatar) + '" alt="">'
                : escapeHtml(String(header.title || '?').trim().charAt(0).toUpperCase())) + '</span>' +
            '<span>' +
                '<div class="tos-chat__thread-title">' + escapeHtml(header.title || '') + '</div>' +
                online +
            '</span>' +
            '<span class="tos-chat__header-actions">' +
                '<button type="button" class="tos-chat__icon-button" data-header="starred" title="' + escapeHtml(L.starredMessages) + '" aria-label="' + escapeHtml(L.starredMessages) + '">&#9733;</button>' +
                '<button type="button" class="tos-chat__icon-button" data-header="search" title="' + escapeHtml(L.searchMessages) + '" aria-label="' + escapeHtml(L.searchMessages) + '">&#128269;</button>' +
                '<button type="button" class="tos-chat__icon-button" data-header="details" title="' + escapeHtml(L.details) + '" aria-label="' + escapeHtml(L.details) + '">&#8942;</button>' +
            '</span>';

        setTypingIndicator(typing || []);
    }

    /** Details panel: mute, pin, archive and disappearing messages. */
    function renderThreadExtras() {
        var chat = findChat(state.chatID);
        if (!chat) { return; }

        var box = document.getElementById('tos-chat-details');
        if (!box) {
            box = document.createElement('div');
            box.id = 'tos-chat-details';
            box.className = 'tos-chat__system';
            el.header.appendChild(box);
            // No early return: the panel is built by the markup immediately
            // below. Returning here meant the first tap on the details button
            // inserted an empty box and showed nothing at all, and only the
            // second tap populated it.
        }

        box.innerHTML =
            '<label><input type="checkbox" data-flag="pinned"' + (chat.pinned === 'Y' ? ' checked' : '') + '> ' + escapeHtml(L.pinned) + '</label> ' +
            '<label><input type="checkbox" data-flag="archived"' + (chat.archived === 'Y' ? ' checked' : '') + '> ' + escapeHtml(L.archived) + '</label> ' +
            '<label><input type="checkbox" data-flag="muted"' + (chat.muted ? ' checked' : '') + '> ' + escapeHtml(L.muted) + '</label> ' +
            '<select data-flag="notify">' +
                ['all', 'mentions', 'none'].map(function (value) {
                    return '<option value="' + value + '"' + (chat.notify === value ? ' selected' : '') + '>' +
                        escapeHtml(value) + '</option>';
                }).join('') +
            '</select>';
    }

    function findChat(chatID) {
        for (var i = 0; i < state.chats.length; i++) {
            if (parseInt(state.chats[i].tawasulChatID, 10) === chatID) { return state.chats[i]; }
        }
        return null;
    }

    // ------------------------------------------------------------- messages

    function bubble(message) {
        var node = document.createElement('div');
        node.className = 'tos-chat__bubble' + (message.isMine || message._temp ? ' is-mine' : '');
        if (message.deletedAt) { node.classList.add('is-deleted'); }
        if (message._failed) { node.classList.add('is-failed'); }

        node.dataset.messageId = domId(message.tawasulChatMessageID);

        var html = '';

        if (message._temp) {
            html += '<div class="tos-chat__text">' + escapeHtml(message.content || '') + '</div>';
        } else if (message.forwardedFromID) {
            html += '<div class="tos-chat__forwarded">' + escapeHtml(L.forwarded) + '</div>';
        }

        if (message.replyTo && !message._temp) {
            html += '<div class="tos-chat__quote">' +
                '<span class="tos-chat__quote-name">' +
                    escapeHtml((message.replyTo.senderPreferredName || '') + ' ' + (message.replyTo.senderSurname || '')) +
                '</span>' +
                '<span class="tos-chat__quote-text">' +
                    escapeHtml(message.replyTo.deletedAt ? L.deleted : (message.replyTo.content || quoteLabel(message.replyTo.type))) +
                '</span></div>';
        }

        // In a one-to-one the recipient is obvious from which side of the screen
        // the bubble is on, so the name would be noise. In a group everyone is
        // mixed together, so it is the only way to tell who said what.
        if (state.chatType === 'group' && !message.isMine) {
            html += '<div class="tos-chat__sender">' + escapeHtml(messageName(message)) + '</div>';
        }

        html += renderBody(message);
        html += renderReactions(message);
        if (message.pinned) {
            html += '<div class="tos-chat__pin-mark">' + escapeHtml(L.pinned) + '</div>';
        }
        // A starred message said so only in the starred list, which is a
        // different screen: the bubble itself looked identical starred or not,
        // and the hover button kept the same outline either. The filled star and
        // the is-starred class are what make the action legible where it was
        // taken.
        if (message.starredByMe) {
            html += '<div class="tos-chat__star-mark">&#9733;</div>';
        }
        html += '<div class="tos-chat__meta">' +
            (message.editedAt ? '<span class="tos-chat__edited">' + escapeHtml(L.editing) + '</span>' : '') +
            '<span>' + escapeHtml(clockTime(message.timestampCreated)) + '</span>' +
            (message.isMine ? ticksHtml(message) : '') +
            '</div>';

        if (!message._temp && !message._failed) {
            html += '<div class="tos-chat__message-actions">' +
                '<button type="button" data-act="reply" title="' + escapeHtml(L.reply) + '">&#8617;</button>' +
                (message.isMine
                    ? '<button type="button" data-act="edit" title="' + escapeHtml(L.edit) + '">&#9998;</button>'
                    : '') +
                '<button type="button" data-act="forward" title="' + escapeHtml(L.forward) + '">&#10145;</button>' +
                '<button type="button" data-act="star"' +
                    (message.starredByMe ? ' class="is-starred" title="' + escapeHtml(L.unstar) + '">&#9733;</button>'
                                         : ' title="' + escapeHtml(L.star) + '">&#9734;</button>') +
                '<button type="button" data-act="react" title="' + escapeHtml(L.copy) + '">&#9786;</button>' +
                '<button type="button" data-act="pin" title="' +
                    escapeHtml(message.pinned ? L.unpinMessage : L.pinMessage) + '">&#128204;</button>' +
                (message.isMine
                    ? '<button type="button" data-act="info" title="' + escapeHtml(L.messageInfo) + '">&#8505;</button>'
                    : '') +
                (message.isMine
                    ? '<button type="button" data-act="delete" title="' + escapeHtml(L.delete) + '">&#128465;</button>'
                    : '') +
                '</div>';
        }

        node.innerHTML = html;
        return node;
    }

    function messageName(message) {
        return ((message.senderPreferredName || '') + ' ' + (message.senderSurname || '')).trim();
    }

    function quoteLabel(type) {
        return ({
            image: L.photo, video: L.video, audio: L.voiceNote,
            document: L.document, location: L.location
        })[type] || '';
    }

    function renderBody(message) {
        if (message.deletedAt) {
            return '<div class="tos-chat__text">' + escapeHtml(L.deleted) + '</div>';
        }

        var html = '';
        var attachments = message.attachments || [];

        if (message.type === 'location' && message.locationLat !== null && message.locationLat !== undefined) {
            html += '<a class="tos-chat__location" target="_blank" rel="noopener noreferrer" href="' +
                escapeHtml('https://www.openstreetmap.org/?mlat=' + message.locationLat + '&mlon=' + message.locationLng + '#map=15/' + message.locationLat + '/' + message.locationLng) +
                '">' + escapeHtml(L.location) + '</a>' +
                (message.locationName ? '<span class="tos-chat__location-name">' + escapeHtml(message.locationName) + '</span>' : '');
            return html;
        }

        attachments.forEach(function (file) {
            var url = config.ajaxURL + '?chatAction=file&attachmentID=' + safeId(file.tawasulChatAttachmentID);
            var thumb = file.thumbnailPath
                ? url + '&thumbnail=1'
                : (file.kind === 'image' ? url : null);

            if (file.kind === 'image') {
                html += '<a class="tos-chat__attachment" data-lightbox="' + escapeHtml(url) + '">' +
                    '<img src="' + escapeHtml(thumb || url) + '" alt="' + escapeHtml(file.fileName) + '" loading="lazy"></a>';
            } else if (file.kind === 'video') {
                html += '<video class="tos-chat__attachment" controls preload="metadata" src="' + escapeHtml(url) + '"></video>';
            } else if (file.kind === 'audio') {
                html += '<div class="tos-chat__voice">' +
                    '<button type="button" data-act="play-audio" aria-label="Play">&#9654;</button>' +
                    '<audio preload="none" src="' + escapeHtml(url) + '"></audio>' +
                    '<span class="tos-chat__voice-duration">' + escapeHtml(durationLabel(file.durationSeconds, message.durationSeconds)) + '</span>' +
                    '</div>';
            } else {
                html += '<a class="tos-chat__file" href="' + escapeHtml(url + '&download=1') + '">' +
                    '&#128206; ' + escapeHtml(file.fileName) + ' (' + escapeHtml(fileSizeLabel(file.fileSize)) + ')</a>';
            }
        });

        if (message.content) {
            html += '<div class="tos-chat__text">' + escapeHtml(message.content) + '</div>';
        }

        return html;
    }

    function durationLabel(a, b) {
        var seconds = parseInt(a !== null && a !== undefined ? a : b, 10);
        if (isNaN(seconds) || seconds <= 0) { return '0:00'; }
        return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
    }

    function renderReactions(message) {
        var reactions = message.reactions || [];
        if (!reactions.length) { return ''; }

        var counts = {};
        reactions.forEach(function (reaction) {
            counts[reaction.emoji] = (counts[reaction.emoji] || 0) + 1;
        });

        return '<div class="tos-chat__reactions">' + Object.keys(counts).map(function (emoji) {
            var mine = reactions.some(function (r) {
                return r.emoji === emoji && String(r.tawasulPersonID) === config.personID;
            });
            return '<button type="button" class="tos-chat__reaction' + (mine ? ' is-mine' : '') +
                '" data-act="react-set" data-emoji="' + escapeHtml(emoji) + '">' +
                escapeHtml(emoji) + ' ' + counts[emoji] + '</button>';
        }).join('') + '</div>';
    }

    /**
     * The ticks, which are the only honest signal a sender gets that a message
     * arrived: one tick once stored, two grey once a recipient's client has
     * polled it, two blue once it has been read. A conversation on its own has
     * nobody to deliver to, so it stays at a single tick.
     */
    function ticksHtml(message) {
        if (message.status === 'pending') { return '<span class="tos-chat__ticks">&#8987;</span>'; }

        var recipients = (parseInt(message.participantCount, 10) || 0) - 1;
        var read = parseInt(message.readByCount, 10) || 0;
        var delivered = parseInt(message.deliveredByCount, 10) || 0;

        if (!config.features.readReceipts || recipients < 1) {
            return '<span class="tos-chat__ticks">&#10003;</span>';
        }
        if (read >= recipients) {
            return '<span class="tos-chat__ticks is-read">&#10003;&#10003;</span>';
        }
        if (delivered >= recipients) {
            return '<span class="tos-chat__ticks">&#10003;&#10003;</span>';
        }

        return '<span class="tos-chat__ticks">&#10003;</span>';
    }

    // -------------------------------------------------------------- actions

    function sendText() {
        var text = el.input.value.replace(/\s+$/, '');
        if (!text) { return; }

        el.input.value = '';
        autosize();

        var replyTo = el.replyId.value;
        // Cleared before the request, not after: the draft of a message that is
        // on its way must go, and waiting for the response would restore the
        // sent text into the composer if the round trip were slow.
        clearDraftNow();

        sendPayload({
            type: 'text',
            content: text,
            replyToMessageID: replyTo || null
        });
    }

    /** Drop the stored draft immediately, without waiting for the debounce. */
    function clearDraftNow() {
        if (state.draftTimer) { window.clearTimeout(state.draftTimer); state.draftTimer = null; }
        hideReplyBarQuietly();

        post('draft', { chatID: state.chatID, content: '', clear: 1 }).catch(function () { });
    }

    function sendPayload(payload) {
        var tempID = 'tmp' + (++state.tempCounter);

        // Drawn straight away. If the request fails the bubble turns red and can
        // be retried, which is better than the composer appearing to swallow the
        // message.
        var temp = {
            tawasulChatMessageID: tempID,
            tawasulChatID: state.chatID,
            content: payload.content || '',
            type: payload.type || 'text',
            timestampCreated: new Date().toISOString().slice(0, 19).replace('T', ' '),
            isMine: true,
            _temp: true,
            status: 'pending'
        };
        el.messages.appendChild(bubble(temp));
        scrollToBottom(true);

        var fields = { chatID: state.chatID, content: payload.content || '', type: payload.type || 'text' };
        if (payload.replyToMessageID) { fields.replyToMessageID = payload.replyToMessageID; }
        if (payload.locationLat !== undefined && payload.locationLat !== null) {
            fields.locationLat = payload.locationLat;
            fields.locationLng = payload.locationLng;
            fields.locationName = payload.locationName || '';
        }

        return post('send', fields).then(function (response) {
            var node = el.messages.querySelector('[data-message-id="' + domId(tempID) + '"]');
            if (node) { node.replaceWith(bubble(response.message)); }
            state.messages.push(response.message);
            scrollToBottom(true);
        }).catch(function (error) {
            var node = el.messages.querySelector('[data-message-id="' + domId(tempID) + '"]');
            if (node) {
                node.classList.add('is-failed');
                node.insertAdjacentHTML('beforeend',
                    '<div class="tos-chat__meta">' + escapeHtml(L.failed) + '</div>');
            }
            el.input.value = payload.content || '';
            autosize();
            el.input.focus();
        });
    }

    function sendFile(file) {
        if (file.size > config.maxUploadMB * 1024 * 1024) {
            window.alert(file.name + ' — ' + fileSizeLabel(file.size) + '. The limit is ' + config.maxUploadMB + ' MB.');
            return;
        }

        var tempID = 'tmp' + (++state.tempCounter);
        var temp = {
            tawasulChatMessageID: tempID,
            tawasulChatID: state.chatID,
            type: 'file',
            timestampCreated: new Date().toISOString().slice(0, 19).replace('T', ' '),
            isMine: true,
            _temp: true,
            status: 'pending'
        };
        el.messages.appendChild(bubble(temp));
        scrollToBottom(true);

        var body = new FormData();
        body.append('attachment', file);
        body.append('chatID', state.chatID);

        post('send', body, true).then(function (response) {
            var node = el.messages.querySelector('[data-message-id="' + domId(tempID) + '"]');
            if (node) { node.replaceWith(bubble(response.message)); }
            state.messages.push(response.message);
            scrollToBottom(true);
        }).catch(function (error) {
            var node = el.messages.querySelector('[data-message-id="' + domId(tempID) + '"]');
            if (node) { node.classList.add('is-failed'); }
            window.alert(error.message);
        });
    }

    /** Record a voice note with the browser's own encoder and post the blob. */
    var recorder = null;
    var recorderChunks = [];
    var recorderStartedAt = 0;

    function toggleRecording() {
        if (recorder && recorder.state === 'recording') {
            recorder.stop();
            return;
        }

        if (!navigator.mediaDevices || !window.MediaRecorder) {
            window.alert(L.voiceNote + ' — not supported by this browser.');
            return;
        }

        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            // webm/opus in Chrome and Firefox, mp4 in Safari. Whichever the
            // browser produces is what gets sent, so a Safari user is not told
            // their own recording is an unsupported format.
            var options = { mimeType: pickAudioMime() };
            recorder = options.mimeType ? new MediaRecorder(stream, options) : new MediaRecorder(stream);

            recorderChunks = [];
            recorder.ondataavailable = function (event) {
                if (event.data && event.data.size > 0) { recorderChunks.push(event.data); }
            };

            recorder.onstop = function () {
                stream.getTracks().forEach(function (track) { track.stop(); });
                el.record.classList.remove('is-recording');
                el.recordingHint.hidden = true;

                var seconds = Math.max(1, Math.round((Date.now() - recorderStartedAt) / 1000));
                var type = (recorder.mimeType || 'audio/webm').split(';')[0];
                // Safari records mp4/aac where the others record webm/opus. Send
                // the extension that matches what this browser actually produced.
                var extension = /mp4|aac|m4a/.test(type) ? 'm4a' : 'webm';

                var body = new FormData();
                body.append('chatID', state.chatID);
                body.append('durationSeconds', seconds);
                body.append('extension', extension);
                // The recording has to actually travel. It used to be collected
                // into recorderChunks and then never attached to the request, so
                // every voice note arrived with an empty body and failed.
                body.append('audio', new Blob(recorderChunks, { type: recorder.mimeType || 'audio/webm' }),
                    'voice-note.' + extension);
                body.append('csrftoken', config.csrf);
                body.append('chatAction', 'voice');

                fetch(config.ajaxURL, {
                    method: 'POST',
                    body: body,
                    headers: { 'X-Chat-Extension': extension },
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (payload) {
                    if (payload.ok === false) { throw new Error(payload.error || L.failed); }
                    el.messages.appendChild(bubble(payload.message));
                    state.messages.push(payload.message);
                    scrollToBottom(true);
                }).catch(function (error) { window.alert(error.message); });

                recorder = null;
            };

            recorderStartedAt = Date.now();
            recorder.start();
            el.record.classList.add('is-recording');
            el.recordingHint.hidden = false;
        }).catch(function () {
            window.alert('Microphone access was refused.');
        });
    }

    function pickAudioMime() {
        var candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
        for (var i = 0; i < candidates.length; i++) {
            if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(candidates[i])) {
                return candidates[i];
            }
        }
        return '';
    }

    function shareLocation() {
        if (!navigator.geolocation) { return; }

        el.location.textContent = '…';
        navigator.geolocation.getCurrentPosition(function (position) {
            el.location.textContent = '?';
            sendPayload({
                type: 'location',
                content: '',
                locationLat: position.coords.latitude,
                locationLng: position.coords.longitude,
                locationName: ''
            });
        }, function () {
            el.location.textContent = '?';
            window.alert('Your location could not be read.');
        });
    }

    function markRead(chatID) {
        var chat = findChat(chatID);
        var lastID = chat && chat.lastMessageID ? chat.lastMessageID : 0;
        if (!lastID) { return; }

        post('read', { chatID: chatID, messageID: lastID }).then(function () {
            var row = findChat(chatID);
            if (row) {
                row.unreadCount = 0;
                row.lastReadMessageID = parseInt(lastID, 10);
                renderList();
            }
        }).catch(function () { /* a missed read receipt is corrected on the next poll */ });
    }

    function markConversationSeen(chatID) {
        if (!state.seen[chatID]) {
            state.seen[chatID] = true;
            try {
                history.replaceState(null, '', '#chat=' + chatID);
            } catch (error) { /* older browsers keep the URL as it is */ }
        }
    }

    // Nothing in this conversation is still being typed at, so the other people in
    // it can stop being told about it.
    function stoppedTyping() {
        if (!config.features.typing) { return; }
        post('typing', { chatID: state.chatID, typing: 0 }).catch(function () { });
    }

    // --------------------------------------------------------------- polling

    /**
     * The poll loop.
     *
     * The request is held open server-side until something changes, so this
     * waits for the answer rather than asking on a timer. On a timeout the
     * server still returns, and `since` has advanced, so the loop is
     * self-correcting rather than drifting.
     */
    function poll() {
        if (state.polling || state.stopped) { return; }
        state.polling = true;

        var body = new URLSearchParams();
        body.append('chatAction', 'poll');
        body.append('csrftoken', config.csrf);
        body.append('since', state.since);
        body.append('active', document.hasFocus() ? '1' : '0');

        fetch(config.ajaxURL, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            state.polling = false;
            if (payload.ok === false) { throw new Error(payload.error); }
            if (state.stopped) { return; }

            state.since = payload.serverTime || state.since;

            if (payload.chats) {
                state.chats = payload.chats.chats || payload.chats;
                renderList();
            }

            applyPresence(payload.presence || []);
            applyReceipts(payload.receipts || []);

            if (payload.typing && payload.typing.length) {
                applyTyping(payload.typing);
            }

            if (payload.messages && payload.messages.length) {
                payload.messages.forEach(function (message) {
                    if (String(message.tawasulChatID) === String(state.chatID)) {
                        appendMessage(message);
                    }
                });
            }

            poll();
        }).catch(function () {
            state.polling = false;
            if (state.stopped) { return; }
            // Back off rather than hammering a server that is failing.
            window.setTimeout(poll, 5000);
        });
    }

    function appendMessage(message) {
        var existing = el.messages.querySelector('[data-message-id="' + safeId(message.tawasulChatMessageID) + '"]');
        if (existing) {
            existing.replaceWith(bubble(message));
        } else {
            var atBottom = isScrolledToBottom();
            el.messages.appendChild(bubble(message));
            if (atBottom) { scrollToBottom(true); }
            if (String(message.tawasulPersonID) !== config.personID) {
                markRead(state.chatID);
            }
        }
    }

    /**
     * Apply tick updates.
     *
     * Receipts are tracked by their own id rather than counted, because the poll
     * cursor deliberately overlaps by a second and so can deliver the same
     * receipt twice. Counting them would make a message show as read by more
     * people than it was sent to.
     */
    function applyReceipts(receipts) {
        receipts.forEach(function (receipt) {
            var receiptID = String(receipt.tawasulChatReceiptID);
            if (state.seenReceipts[receiptID]) { return; }
            state.seenReceipts[receiptID] = true;

            var node = el.messages.querySelector('[data-message-id="' + safeId(receipt.tawasulChatMessageID) + '"]');
            if (!node) { return; }

            var ticks = node.querySelector('.tos-chat__ticks');
            if (ticks && receipt.readAt) { ticks.classList.add('is-read'); }

            var message = state.messages.filter(function (m) {
                return String(m.tawasulChatMessageID) === String(receipt.tawasulChatMessageID);
            })[0];
            if (message) {
                message.readByCount = (message.readByCount || 0) + (receipt.readAt ? 1 : 0);
            }
        });
    }

    function applyPresence(rows) {
        rows.forEach(function (row) {
            state.chats.forEach(function (chat) {
                if (chat.otherPerson && String(chat.otherPerson.tawasulPersonID) === String(row.tawasulPersonID)) {
                    chat.otherPerson.status = row.status;
                    chat.otherPerson.lastSeenAt = row.lastSeenAt;
                }
            });
        });
        renderList();
    }

    var typingNode = null;
    var typingPeople = [];

    function applyTyping(rows) {
        typingPeople = rows.filter(function (row) {
            return parseInt(row.typingChatID, 10) === parseInt(state.chatID, 10);
        });
        setTypingIndicator(typingPeople);
    }

    function setTypingIndicator(rows) {
        if (typingNode && typingNode.parentNode) { typingNode.remove(); }
        typingNode = null;

        if (!rows.length) { return; }

        typingNode = document.createElement('div');
        typingNode.className = 'tos-chat__typing';
        typingNode.innerHTML = '<span></span><span></span><span></span>';
        el.messages.appendChild(typingNode);
        scrollToBottom(true);
    }

    function notifyTyping() {
        if (!config.features.typing || !state.chatID) { return; }

        var now = Date.now();
        if (now - state.lastTypingSent < 2000) { return; }
        state.lastTypingSent = now;

        post('typing', { chatID: state.chatID, typing: 1 }).catch(function () { });
    }

    // -------------------------------------------------------------- replying

    function startReply(message) {
        state.replyTo = message;
        el.replyId.value = safeId(message.tawasulChatMessageID);
        el.replyName.textContent = messageName(message) || L.you;
        el.replyText.textContent = message.deletedAt ? L.deleted : (message.content || quoteLabel(message.type));
        el.replying.hidden = false;
        el.input.focus();
    }

    function hideReplyBar() {
        state.replyTo = null;
        el.replyId.value = '';
        el.replying.hidden = true;
        scheduleDraftSave();
    }

    // --------------------------------------------------------------- drafts

    /**
     * Save the composer to the server, a moment after typing stops.
     *
     * Debounced because a draft is a net, not a ledger: saving on every
     * keystroke would put a request behind each character and gain nothing. The
     * draft also carries the reply it was written against, so reopening a
     * conversation restores the quote strip too.
     */
    function scheduleDraftSave() {
        if (state.draftTimer) { window.clearTimeout(state.draftTimer); }
        if (!state.chatID) { return; }

        state.draftTimer = window.setTimeout(function () {
            state.draftTimer = null;

            var content = el.input.value;
            var replyTo = el.replyId.value || '';
            var clearing = content.trim() === '' && replyTo === '';

            post('draft', {
                chatID: state.chatID,
                content: content,
                replyToMessageID: replyTo,
                clear: clearing ? 1 : 0
            }).catch(function () {
                // A draft is a convenience. Failing to store one is not worth
                // interrupting the conversation with an error the user cannot
                // act on, and the next keystroke will try again.
            });
        }, 700);
    }

    function restoreDraft(draft) {
        if (!draft || !draft.content) {
            el.input.value = '';
            hideReplyBarQuietly();
            autosize();
            return;
        }

        el.input.value = draft.content;
        autosize();

        if (draft.replyToMessageID) {
            var quoted = state.messages.filter(function (message) {
                return String(message.tawasulChatMessageID) === String(draft.replyToMessageID);
            })[0];
            if (quoted) {
                startReply(quoted);
                // startReply would schedule another save; the draft is already
                // stored and re-saving it from a load is how a draft ends up
                // looking edited when nobody touched it.
                if (state.draftTimer) { window.clearTimeout(state.draftTimer); state.draftTimer = null; }
                return;
            }
        }

        hideReplyBarQuietly();
    }

    /** Clear the reply strip without touching the draft timer. */
    function hideReplyBarQuietly() {
        state.replyTo = null;
        el.replyId.value = '';
        el.replying.hidden = true;
    }

    /** Jump to a pinned message, loading earlier pages until it is on screen. */
    function jumpToMessage(messageID) {
        var target = el.messages.querySelector('[data-message-id="' + safeId(messageID) + '"]');
        if (target) {
            target.scrollIntoView({ block: 'center', behavior: 'smooth' });
            flashNode(target);
            return;
        }

        // Not on the page yet: pull pages until it appears, bounded so a stale
        // pin can never spin the client through the whole conversation.
        var attempts = 0;
        (function loadUntilFound() {
            if (!state.hasMore || attempts >= 10) { return; }
            attempts++;

            var oldest = state.messages[0];
            if (!oldest) { return; }

            var scrollerHeight = el.scroller.scrollHeight;
            get('messages', { chatID: state.chatID, before: safeId(oldest.tawasulChatMessageID) })
                .then(function (payload) {
                    var page = oldestFirst(payload.messages);
                    var fragment = document.createDocumentFragment();
                    page.forEach(function (message) {
                        fragment.appendChild(bubble(message));
                    });
                    el.messages.insertBefore(fragment, el.messages.firstChild);
                    state.messages = page.concat(state.messages);
                    state.hasMore = payload.hasMore;
                    el.scroller.scrollTop = el.scroller.scrollHeight - scrollerHeight;

                    var node = el.messages.querySelector('[data-message-id="' + safeId(messageID) + '"]');
                    if (node) {
                        node.scrollIntoView({ block: 'center', behavior: 'smooth' });
                        flashNode(node);
                        return;
                    }

                    loadUntilFound();
                });
        })();
    }

    function flashNode(node) {
        node.classList.add('is-highlighted');
        window.setTimeout(function () { node.classList.remove('is-highlighted'); }, 1600);
    }

    // ------------------------------------------------------------- reactions

    function openReactionPicker(bubbleNode) {
        var existing = el.messages.querySelector('.tos-chat__picker');
        if (existing) { existing.remove(); }

        var picker = document.createElement('div');
        picker.className = 'tos-chat__reactions tos-chat__picker';
        picker.innerHTML = config.quickReactions.map(function (emoji) {
            return '<button type="button" class="tos-chat__reaction" data-act="react-set" data-emoji="' +
                escapeHtml(emoji) + '">' + escapeHtml(emoji) + '</button>';
        }).join('');

        // Appended to the bubble element, not to the message record: the record
        // is a plain object from the payload, so calling closest() on it threw
        // "message.closest is not a function" and no reaction could ever be
        // picked.
        bubbleNode.appendChild(picker);
    }

    function setReaction(messageID, emoji, current) {
        // Tapping the reaction you already set removes it, as on a phone.
        post('react', {
            messageID: messageID,
            emoji: emoji === current ? '' : emoji
        }).then(function () {
            reloadThread();
        }).catch(function (error) { window.alert(error.message); });
    }

    function reloadThread() {
        if (!state.chatID) { return; }
        get('messages', { chatID: state.chatID }).then(function (payload) {
            if (state.chatID !== payload.chat.tawasulChatID) { return; }
            state.messages = oldestFirst(payload.messages);
            el.messages.innerHTML = '';
            state.messages.forEach(function (message) { el.messages.appendChild(bubble(message)); });
            renderHeader(payload.chat, payload.typing || []);

            // The conversation payload describes the chat but not who is in it,
            // so the header drawn from it has no title and no presence line. The
            // full header is fetched here for the same reason the first open does
            // it, otherwise every reload — after a star, a pin, an edit or a
            // reaction — quietly replaced a real title and avatar with "?" and a
            // participant count.
            var openID = state.chatID;
            get('header', { chatID: openID }).then(function (header) {
                if (state.chatID !== openID) { return; }
                state.chatHeader = header;
                renderHeader(header, payload.typing || []);
            }).catch(function () { /* the header already drawn stands */ });

            renderThreadExtras();
            // Re-read from the same payload rather than mutating client state:
            // reloadThread runs after a pin or a star, and the server's answer
            // already carries both the pinned strip and any cleared draft.
            state.pinned = payload.pinned || [];
            renderPinned();
            if (el.input.value === '') { restoreDraft(payload.draft); }
            scrollToBottom(false);
        });
    }

    // ------------------------------------------------------------- pinning

    function renderPinned() {
        var pinned = state.pinned || [];

        el.pinned.hidden = pinned.length === 0;
        if (!pinned.length) { return; }

        // Only the first pin is shown while collapsed. A strip that grows to
        // three rows would push the conversation down every time somebody pins
        // something, which is the opposite of what pinning is for.
        var expanded = el.pinned.classList.contains('is-expanded');
        var shown = expanded ? pinned : pinned.slice(0, 1);

        el.pinnedList.innerHTML = shown.map(function (pin) {
            return '<li><button type="button" data-jump="' + safeId(pin.tawasulChatMessageID) + '">' +
                '<span class="tos-chat__pinned-sender">' + escapeHtml(pin.senderTitle) + '</span>' +
                '<span class="tos-chat__pinned-text">' + escapeHtml(pin.preview || quoteLabel(pin.type)) + '</span>' +
                '</button></li>';
        }).join('');

        el.pinnedToggle.setAttribute(
            'aria-expanded',
            expanded ? 'true' : 'false'
        );
    }

    function togglePin(messageID, pinned) {
        post('pin', {
            messageID: messageID,
            chatID: state.chatID,
            pinned: pinned ? 0 : 1
        }).then(function (payload) {
            state.pinned = payload.pinned || [];
            renderPinned();
            // The bubble's own pin marker comes back with the same redraw, but
            // the row may also be further up the page, so both are refreshed
            // from one source rather than guessed at here.
            markPinOnBubble(messageID, !pinned);
        }).catch(function (error) {
            window.alert(error.message);
        });
    }

    function markPinOnBubble(messageID, pinned) {
        state.messages.forEach(function (message) {
            if (String(message.tawasulChatMessageID) === String(messageID)) {
                message.pinned = pinned;
            }
        });

        var node = el.messages.querySelector('[data-message-id="' + safeId(messageID) + '"]');
        if (!node) { return; }

        var fresh = bubble(state.messages.filter(function (message) {
            return String(message.tawasulChatMessageID) === String(messageID);
        })[0]);
        node.replaceWith(fresh);
    }

    // ------------------------------------------------- starred and search

    /**
     * One row in the starred list or the search results.
     *
     * Both lists carry the same shape from the server — a message id, the
     * conversation it is in, its text and who sent it — so they share a row
     * builder rather than each inventing its own markup.
     */
    function resultRow(item) {
        var sender = ((item.senderPreferredName || '') + ' ' + (item.senderSurname || '')).trim();

        return '<li class="tos-chat__result-row">' +
            '<button type="button" data-goto-chat="' + safeId(item.tawasulChatID) + '"' +
                ' data-goto-message="' + safeId(item.tawasulChatMessageID) + '">' +
                '<span class="tos-chat__result-sender">' + escapeHtml(sender) + '</span>' +
                '<span class="tos-chat__result-text">' +
                    escapeHtml(item.content || quoteLabel(item.type)) +
                '</span>' +
                '<span class="tos-chat__result-time">' +
                    escapeHtml(relativeTime(item.timestampCreated)) +
                '</span>' +
            '</button></li>';
    }

    function openStarred() {
        get('starred', {}).then(function (payload) {
            var rows = payload.starred || [];

            el.starredList.innerHTML = rows.length
                ? rows.map(resultRow).join('')
                : '<li class="tos-chat__system">' + escapeHtml(L.noStarred) + '</li>';

            el.starred.hidden = false;
        }).catch(function (error) {
            window.alert(error.message);
        });
    }

    function closeStarred() {
        el.starred.hidden = true;
        el.starredList.innerHTML = '';
    }

    /**
     * In-conversation message search.
     *
     * The query is sent as the person types, debounced, because the endpoint
     * caps at fifty rows and there is no benefit to asking on every keystroke.
     * The server ignores anything shorter than two characters, so the same
     * minimum is applied here rather than showing a round trip's worth of
     * nothing.
     */
    var searchTimer = null;
    var searchToken = 0;

    function runSearch() {
        var term = el.searchInput.value.trim();

        if (term.length < 2) {
            el.searchResults.innerHTML = term
                ? '<li class="tos-chat__system">' + escapeHtml(L.searchHint) + '</li>'
                : '';
            return;
        }

        // Every keystroke bumps the token, so a slow response for an abandoned
        // term is recognised as stale and dropped rather than overwriting the
        // results for what is now in the box.
        var token = ++searchToken;

        get('search', { q: term }).then(function (payload) {
            if (token !== searchToken) { return; }

            var rows = payload.results || [];
            el.searchResults.innerHTML = rows.length
                ? rows.map(resultRow).join('')
                : '<li class="tos-chat__system">' + escapeHtml(L.noMatches) + '</li>';
        }).catch(function (error) {
            if (token !== searchToken) { return; }
            el.searchResults.innerHTML = '<li class="tos-chat__system">' +
                escapeHtml(error.message) + '</li>';
        });
    }

    function openSearch() {
        el.searchResults.innerHTML = '';
        el.searchModal.hidden = false;
        el.searchInput.focus();
    }

    function closeSearch() {
        el.searchModal.hidden = true;
        // Clearing the box stops a term surviving into the next open, which
        // would otherwise re-run a search the reader did not ask for again.
        el.searchInput.value = '';
        el.searchResults.innerHTML = '';
        searchToken++;
    }

    /**
     * Open the conversation a result lives in and scroll to it.
     *
     * A starred message can be in a conversation that is not open, so the
     * conversation is opened first and the message id handed to it as
     * state.pendingJump: openConversation fetches asynchronously, and scrolling
     * before its bubbles exist would find nothing and give up.
     */
    function goToResult(event) {
        var button = event.target.closest('[data-goto-message]');
        if (!button) { return; }

        var chatID = parseInt(button.getAttribute('data-goto-chat'), 10);
        var messageID = button.getAttribute('data-goto-message');

        closeSearch();
        closeStarred();

        if (chatID === state.chatID) {
            jumpToMessage(messageID);
            return;
        }

        state.pendingJump = messageID;
        openConversation(chatID);
    }

    // --------------------------------------------------------- message info

    function openMessageInfo(messageID) {
        get('info', { messageID: messageID }).then(function (payload) {
            var info = payload.info || {};
            var recipients = info.recipients || [];

            // The sender's own row is dropped: they are not a recipient of their
            // own message, and listing them as one is what makes a "read by 2"
            // count wrong.
            var others = recipients.filter(function (person) { return !person.isMe; });

            el.infoSummary.textContent = info.recipientCount + ' · '
                + (info.readCount || 0) + ' ' + L.infoRead + ' · '
                + (info.deliveredCount || 0) + ' ' + L.infoDelivered;

            el.infoList.innerHTML = others.length
                ? others.map(function (person) {
                    var state_ = person.readAt ? 'read' : (person.deliveredAt ? 'delivered' : 'pending');
                    var when = person.readAt || person.deliveredAt || null;
                    return '<li class="tos-chat__info-row is-' + state_ + '">' +
                        '<span class="tos-chat__avatar">' +
                            (person.image_240
                                ? '<img src="' + escapeHtml(person.image_240) + '" alt="" loading="lazy">'
                                : escapeHtml(String(person.title || '?').trim().charAt(0).toUpperCase())) +
                        '</span>' +
                        '<span class="tos-chat__info-name">' + escapeHtml(person.title) + '</span>' +
                        '<span class="tos-chat__info-state">' +
                            escapeHtml(state_ === 'read' ? L.infoRead : (state_ === 'delivered' ? L.infoDelivered : L.infoPending)) +
                            (when ? ', ' + escapeHtml(clockTime(when)) : '') +
                        '</span></li>';
                }).join('')
                : '<li class="tos-chat__system">' + escapeHtml(L.noTargets) + '</li>';

            el.info.hidden = false;
        }).catch(function (error) {
            window.alert(error.message);
        });
    }

    // ------------------------------------------------------- forward picker

    /**
     * The forward picker.
     *
     * Replaces a prompt() that asked for a raw chat id, which made forwarding a
     * message to somebody you had never messaged impossible without leaving the
     * screen. Selection is multi-select by design: forwarding the same message
     * to a group and an individual is an ordinary thing to want to do.
     */
    function openForwardPicker(messageID) {
        state.forwarding = { messageID: messageID, targets: [], selected: [] };

        get('forwardTargets').then(function (payload) {
            state.forwarding.targets = payload.targets || [];
            el.forwardSearch.value = '';
            el.forwardNote.value = '';
            renderForwardList();
            el.forward.hidden = false;
            el.forwardSearch.focus();
        }).catch(function (error) {
            state.forwarding = null;
            window.alert(error.message);
        });
    }

    function renderForwardList() {
        if (!state.forwarding) { return; }

        var needle = el.forwardSearch.value.trim().toLowerCase();
        var targets = state.forwarding.targets.filter(function (target) {
            if (!needle) { return true; }
            return String(target.title || '').toLowerCase().indexOf(needle) !== -1;
        });

        el.forwardList.innerHTML = targets.length
            ? targets.map(function (target) {
                var id = safeId(target.chatID);
                var on = state.forwarding.selected.indexOf(parseInt(id, 10)) !== -1;
                return '<li><button type="button" class="tos-chat__forward-row' + (on ? ' is-selected' : '') +
                    '" data-target="' + id + '">' +
                    '<span class="tos-chat__avatar">' +
                        (target.avatar
                            ? '<img src="' + escapeHtml(target.avatar) + '" alt="" loading="lazy">'
                            : escapeHtml(String(target.title || '?').trim().charAt(0).toUpperCase())) +
                    '</span>' +
                    '<span class="tos-chat__forward-title">' + escapeHtml(target.title) + '</span>' +
                    '<span class="tos-chat__forward-tick">' + (on ? '&#10003;' : '') + '</span>' +
                    '</button></li>';
            }).join('')
            : '<li class="tos-chat__system">' + escapeHtml(L.noTargets) + '</li>';

        updateForwardButton();
    }

    function updateForwardButton() {
        if (!state.forwarding) { return; }

        var count = state.forwarding.selected.length;
        el.forwardSend.disabled = count === 0;
        el.forwardCount.textContent = count
            ? L.selectedCount.replace('%d', String(count))
            : '';
    }

    function closeForwardPicker() {
        el.forward.hidden = true;
        state.forwarding = null;
    }

    function sendForward() {
        if (!state.forwarding || !state.forwarding.selected.length) { return; }

        var messageID = state.forwarding.messageID;
        var targets = state.forwarding.selected.slice();
        var note = el.forwardNote.value.trim();

        el.forwardSend.disabled = true;

        post('forward', {
            messageID: messageID,
            chatIDs: targets,
            note: note
        }).then(function () {
            closeForwardPicker();
            // Land in the first destination, which is what the person was
            // heading for when they opened the picker.
            openConversation(targets[0]);
        }).catch(function (error) {
            el.forwardSend.disabled = false;
            window.alert(error.message);
        });
    }

    // --------------------------------------------------------------- helpers

    function scrollToBottom(smooth) {
        el.scroller.scrollTo ? el.scroller.scrollTo({ top: el.scroller.scrollHeight, behavior: smooth ? 'smooth' : 'auto' })
            : (el.scroller.scrollTop = el.scroller.scrollHeight);
    }

    function isScrolledToBottom() {
        return el.scroller.scrollHeight - el.scroller.scrollTop - el.scroller.clientHeight < 120;
    }

    function autosize() {
        el.input.style.height = 'auto';
        el.input.style.height = Math.min(el.input.scrollHeight, 140) + 'px';
    }

    function openLightbox(url) {
        el.lightboxBody.innerHTML = '<img src="' + escapeHtml(url) + '" alt="">';
        el.lightbox.hidden = false;
    }

    function loadEarlier() {
        var oldest = state.messages[0];
        if (!oldest) { return; }

        var scrollerHeight = el.scroller.scrollHeight;

        get('messages', { chatID: state.chatID, before: safeId(oldest.tawasulChatMessageID) })
            .then(function (payload) {
                var page = oldestFirst(payload.messages);
                var fragment = document.createDocumentFragment();
                page.forEach(function (message) {
                    fragment.appendChild(bubble(message));
                });
                el.messages.insertBefore(fragment, el.messages.firstChild);
                state.messages = page.concat(state.messages);
                state.hasMore = payload.hasMore;

                // Keep the reader where they were rather than jumping to the top.
                el.scroller.scrollTop = el.scroller.scrollHeight - scrollerHeight;
            });
    }

    // ----------------------------------------------------------------- wiring

    el.form.addEventListener('submit', function (event) {
        event.preventDefault();
        sendText();
    });

    el.input.addEventListener('input', function () {
        autosize();
        notifyTyping();
        scheduleDraftSave();
    });

    el.input.addEventListener('keydown', function (event) {
        // Enter sends, Shift+Enter breaks the line. Matches every other
        // messenger, and is the behaviour people expect to carry over.
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            sendText();
        }
    });

    el.attach.addEventListener('click', function () { el.file.click(); });
    el.file.addEventListener('change', function () {
        if (el.file.files && el.file.files[0]) { sendFile(el.file.files[0]); }
        el.file.value = '';
    });
    el.location.addEventListener('click', shareLocation);
    if (el.record) { el.record.addEventListener('click', toggleRecording); }
    el.replyCancel.addEventListener('click', function () {
        hideReplyBar();
        scheduleDraftSave();
    });

    // Leaving the conversation: the composer is flushed before the page unloads,
    // because the debounce would otherwise never fire and the last sentence
    // typed would be lost with the tab.
    window.addEventListener('beforeunload', function () {
        if (state.draftTimer && state.chatID) {
            var content = el.input.value;
            if (content.trim() !== '' || el.replyId.value) {
                var body = new URLSearchParams();
                body.append('chatAction', 'draft');
                body.append('csrftoken', config.csrf);
                body.append('chatID', state.chatID);
                body.append('content', content);
                body.append('replyToMessageID', el.replyId.value || '');
                if (content.trim() === '' && !el.replyId.value) { body.append('clear', '1'); }

                // sendBeacon survives the page going away; a fetch here is
                // cancelled by the browser before it is sent.
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(config.ajaxURL, body);
                }
            }
        }
        state.stopped = true;
    });

    // Drag and drop a file onto the conversation.
    el.thread.addEventListener('dragover', function (event) {
        event.preventDefault();
        el.thread.classList.add('is-dragging');
    });
    el.thread.addEventListener('dragleave', function () { el.thread.classList.remove('is-dragging'); });
    el.thread.addEventListener('drop', function (event) {
        event.preventDefault();
        el.thread.classList.remove('is-dragging');
        if (event.dataTransfer.files && event.dataTransfer.files[0]) { sendFile(event.dataTransfer.files[0]); }
    });

    el.list.addEventListener('click', function (event) {
        var item = event.target.closest('.tos-chat__conversation');
        if (!item) { return; }
        event.preventDefault();
        openConversation(parseInt(item.dataset.chatId, 10));
    });

    el.messages.addEventListener('click', function (event) {
        var lightboxTrigger = event.target.closest('[data-lightbox]');
        if (lightboxTrigger) {
            openLightbox(lightboxTrigger.getAttribute('data-lightbox'));
            return;
        }

        var bubbleNode = event.target.closest('.tos-chat__bubble');
        if (!bubbleNode) { return; }
        var messageID = safeId(bubbleNode.dataset.messageId);
        if (!messageID || messageID.indexOf('tmp') === 0) { return; }

        var message = state.messages.filter(function (m) {
            return String(m.tawasulChatMessageID) === messageID;
        })[0] || {};

        var action = event.target.closest('[data-act]');
        if (!action) { return; }

        switch (action.getAttribute('data-act')) {
            case 'reply': startReply(message); break;
            case 'react': openReactionPicker(bubbleNode); break;
            case 'react-set': setReaction(messageID, action.getAttribute('data-emoji'), message.reactedByMe ? reactionOf(message) : ''); break;
            case 'star': toggleStar(messageID, !!message.starredByMe); break;
            case 'edit': editMessage(messageID, message.content || ''); break;
            case 'delete': deleteMessage(messageID); break;
            case 'forward': openForwardPicker(messageID); break;
            case 'pin': togglePin(messageID, !!message.pinned); break;
            case 'info': openMessageInfo(messageID); break;
            case 'play-audio': {
                var audio = action.parentNode.querySelector('audio');
                if (audio) { audio.play(); }
                break;
            }
        }
    });

    function reactionOf(message) {
        var mine = (message.reactions || []).filter(function (r) {
            return String(r.tawasulPersonID) === config.personID;
        });
        return mine.length ? mine[0].emoji : '';
    }

    function toggleStar(messageID, starred) {
        post('star', { messageID: messageID, starred: starred ? 0 : 1 })
            .then(reloadThread)
            .catch(function (error) { window.alert(error.message); });
    }

    function editMessage(messageID, current) {
        var replacement = window.prompt(L.edit, current);
        if (replacement === null || replacement === current) { return; }

        post('edit', { messageID: messageID, content: replacement })
            .then(reloadThread)
            .catch(function (error) { window.alert(error.message); });
    }

    function deleteMessage(messageID) {
        var everyone = window.confirm(L.delete + ' — ' + L.deleted + '?');
        post('delete', { messageID: messageID, scope: everyone ? 'everyone' : 'me' })
            .then(reloadThread)
            .catch(function (error) { window.alert(error.message); });
    }

    // Forwarding opens the picker, which is wired at the bottom of this file.

    el.pinnedToggle.addEventListener('click', function () {
        el.pinned.classList.toggle('is-expanded');
        renderPinned();
    });

    el.pinnedClose.addEventListener('click', function () {
        el.pinned.classList.remove('is-expanded');
        renderPinned();
    });

    el.pinnedList.addEventListener('click', function (event) {
        var jump = event.target.closest('[data-jump]');
        if (!jump) { return; }
        jumpToMessage(jump.getAttribute('data-jump'));
    });

    el.infoClose.addEventListener('click', function () { el.info.hidden = true; });
    el.info.addEventListener('click', function (event) {
        if (event.target === el.info) { el.info.hidden = true; }
    });

    el.starredClose.addEventListener('click', closeStarred);
    el.starred.addEventListener('click', function (event) {
        if (event.target === el.starred) { closeStarred(); }
    });
    el.starredList.addEventListener('click', goToResult);

    el.searchClose.addEventListener('click', closeSearch);
    el.searchModal.addEventListener('click', function (event) {
        if (event.target === el.searchModal) { closeSearch(); }
    });
    el.searchInput.addEventListener('input', function () {
        if (searchTimer) { clearTimeout(searchTimer); }
        searchTimer = setTimeout(runSearch, 250);
    });
    el.searchResults.addEventListener('click', goToResult);

    el.forwardClose.addEventListener('click', closeForwardPicker);
    el.forward.addEventListener('click', function (event) {
        if (event.target === el.forward) { closeForwardPicker(); }
    });

    el.forwardSearch.addEventListener('input', renderForwardList);

    el.forwardList.addEventListener('click', function (event) {
        var row = event.target.closest('[data-target]');
        if (!row || !state.forwarding) { return; }

        var chatID = parseInt(row.getAttribute('data-target'), 10);
        var at = state.forwarding.selected.indexOf(chatID);
        if (at === -1) {
            state.forwarding.selected.push(chatID);
        } else {
            state.forwarding.selected.splice(at, 1);
        }

        renderForwardList();
    });

    el.forwardSend.addEventListener('click', sendForward);

    el.header.addEventListener('click', function (event) {
        var button = event.target.closest('[data-header]');
        if (!button) { return; }

        // Each header button drives its own panel. Sharing one handler that only
        // re-rendered the details panel left the starred and search buttons
        // looking live and doing nothing at all.
        var which = button.getAttribute('data-header');

        if (which === 'starred') {
            closeSearch();
            openStarred();
            return;
        }

        if (which === 'search') {
            closeStarred();
            openSearch();
            return;
        }

        renderThreadExtras();
    });

    el.header.addEventListener('change', function (event) {
        var field = event.target.closest('[data-flag]');
        if (!field) { return; }

        var flag = field.getAttribute('data-flag');
        var value = field.type === 'checkbox' ? (field.checked ? 'Y' : 'N') : field.value;
        var mutedUntil = '';

        if (flag === 'muted') {
            // Muted for a day, the way a phone does it, with no separate setting.
            mutedUntil = field.checked
                ? new Date(Date.now() + 86400000).toISOString().slice(0, 19).replace('T', ' ')
                : '';
            value = '';
        }

        post('flag', { chatID: state.chatID, flag: flag, value: value, mutedUntil: mutedUntil })
            .then(function (payload) {
                if (payload.chats) {
                    state.chats = payload.chats.chats || payload.chats;
                    renderList();
                    renderThreadExtras();
                }
            })
            .catch(function (error) { window.alert(error.message); });
    });

    el.search.addEventListener('input', function () {
        state.searchTerm = el.search.value.trim().toLowerCase();
        renderList();
    });

    el.lightboxClose.addEventListener('click', function () { el.lightbox.hidden = true; });
    el.lightbox.addEventListener('click', function (event) {
        if (event.target === el.lightbox) { el.lightbox.hidden = true; }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (!el.forward.hidden) { closeForwardPicker(); return; }
            if (!el.searchModal.hidden) { closeSearch(); return; }
            if (!el.starred.hidden) { closeStarred(); return; }
            if (!el.info.hidden) { el.info.hidden = true; return; }
            if (!el.lightbox.hidden) { el.lightbox.hidden = true; return; }
            if (!el.replying.hidden) { hideReplyBar(); scheduleDraftSave(); }
        }
    });

    window.addEventListener('pagehide', function () {
        stoppedTyping();
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && state.chatID) {
            markRead(state.chatID);
        } else if (document.hidden && state.chatID) {
            // Backgrounded while typing: tell the others, or their "typing…"
            // indicator hangs there until the housekeeper clears it.
            stoppedTyping();
        }
    });

    // ----------------------------------------------------------------- start

    renderList();
    autosize();
    poll();

    if (state.chatID) {
        openConversation(state.chatID);
    } else {
        el.emptyState.hidden = state.chats.length > 0;
        el.thread.hidden = true;
    }
})();
