<?php
/*
TawasulChat — start a conversation.

A single screen for both cases rather than two, because choosing one person and
naming a group is the same decision made once. The form posts to
chat_newProcess.php and works without JavaScript; the search and the resulting
conversation list are progressive enhancements layered on top.
*/

use TawasulOS\Forms\Form;

require_once __DIR__.'/chatFunctions.php';

$page->breadcrumbs
    ->add(__('Chat'), 'chat.php')
    ->add(__('New Chat'));

// The processor puts the specific reason a conversation could not be started in
// the session, under a code only this module knows. Registering it here is what
// turns that into a sentence on the page.
$page->return->addReturns([
    'chatError' => $session->get('chatFlashError') ?: __('Your request failed.'),
]);
$session->forget('chatFlashError');

if (isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/messenger_chat_new.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    echo '<p>'.__('You do not have access to this action.').'</p>';

    return;
}

$personID = chatPersonID($session);
$services = chatServices($connection2, $session->get('absolutePath'));
$settings = $services['settings'];
$maxGroupSize = $settings->getInt('maxGroupSize', 2, 1000);

// The picker is populated by the endpoint on first paint and by the search box
// afterwards. Rendering a few hundred people server-side into a multi-select
// would produce a form nobody can use, so the initial set is the first page of
// contacts and the search narrows it.
$contacts = $services['chatService']->contacts($personID, '', 40);

echo '<p>'.__('Choose one person to start a conversation, or pick several and give the conversation a name to make a group.').'</p>';

$form = Form::create('tosChatNew', $session->get('absoluteURL').'/modules/'.$session->get('module').'/chat_newProcess.php');
$form->addHiddenValue('address', $session->get('address'));

$row = $form->addRow();
$row->addLabel('search', __('Find someone'));
$col = $row->addColumn('search');
$col->addTextField('search')->setAttribute('id', 'tos-chat-person-search')
    ->setAttribute('placeholder', __('Type a name'))->setAttribute('autocomplete', 'off');

$row = $form->addRow();
$row->addLabel('personIDs', __('People'))->description(sprintf(__('Up to %d people in a group.'), $maxGroupSize));
$col = $row->addColumn('personIDs');
$col->addMultipleSelect('personIDs')
    ->setSize(12)
    ->setData([$contacts]);

$row = $form->addRow();
$row->addLabel('name', __('Group Name'))->description(__('Leave this empty for a one-to-one conversation.'));
$row->addTextField('name')->maxLength(100);

$row = $form->addRow();
$row->addLabel('disappearingMinutes', __('Disappearing Messages'));
$col = $row->addColumn('disappearingMinutes');
$col->addSelect('disappearingMinutes')
    ->setData([
        ['0' => __('Off')],
        ['3600' => __('After 1 hour')],
        ['86400' => __('After 1 day')],
        ['604800' => __('After 1 week')],
    ]);

$row = $form->addRow();
$row->addFooter();
$row->addSubmit(__('Start Conversation'));

echo $form->getOutput();

echo '<p class="description">'
    . __('A one-to-one conversation with a person who already has one with you reuses that conversation, so nobody ends up with two parallel threads.')
    . '</p>';

$page->scripts->add('tos-chat-contact-search', 'modules/TawasulMessenger/js/messenger_chat_contactSearch.js');
