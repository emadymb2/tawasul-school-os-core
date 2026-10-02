<?php
/*
TawasulChat — starred messages.

Starred messages are per person, so this shows what *you* starred rather than
what anyone did. The query is scoped to conversations the reader is still in: a
message starred before being removed from a group is not their business any
more, and showing it would disclose the contents of a conversation they left.
*/

use TawasulOS\Services\Format;

require_once __DIR__.'/chatFunctions.php';

$page->breadcrumbs
    ->add(__('Chat'), 'chat.php')
    ->add(__('Starred Messages'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/messenger_chat_starred.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    echo '<p>'.__('You do not have access to this action.').'</p>';

    return;
}

$personID = chatPersonID($session);
$services = chatServices($connection2, $session->get('absolutePath'));

$starred = $services['messages']->starredFor($personID, 100);

echo '<p class="description">'
    . __('Messages you have starred, newest first. Starred messages are private to you.')
    . '</p>';

if ($starred === []) {
    echo '<p>'.__('You have not starred any messages yet. Long-press or hover a message and choose the star.').'</p>';

    return;
}

echo '<table class="w-full">';
echo '<thead><tr>'
    . '<th>'.__('Conversation').'</th>'
    . '<th>'.__('Message').'</th>'
    . '<th class="w-32">'.__('Date').'</th>'
    . '</tr></thead><tbody>';

foreach ($starred as $row) {
    $sender = trim($row['senderPreferredName'].' '.$row['senderSurname']);
    $preview = $row['content'] !== null && $row['content'] !== ''
        ? $row['content']
        : __('(attachment)');

    echo '<tr>';
    echo '<td>'.htmlspecialchars($sender).'</td>';
    echo '<td>'.nl2br(htmlspecialchars(Format::truncate($preview, 200))).'</td>';
    echo '<td><a href="'.$session->get('absoluteURL').'/index.php?q=/modules/TawasulMessenger/messenger_chat.php&amp;chat='
        .(int) $row['tawasulChatID'].'">'.htmlspecialchars(Format::dateReadable($row['timestampCreated'])).'</a></td>';
    echo '</tr>';
}

echo '</tbody></table>';
