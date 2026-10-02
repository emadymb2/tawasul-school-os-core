<?php
/*
TawasulChat — module settings.

Each switch here changes a behaviour people notice, so each one says what it
does rather than just naming the setting. The limits are the interesting ones:
the attachment ceiling and the poll timeout both interact with what the server
can actually do, so the descriptions say what happens past the limit.
*/

use TawasulOS\Forms\Form;
use TawasulOS\Domain\System\SettingGateway;

require_once __DIR__.'/chatFunctions.php';

$page->breadcrumbs
    ->add(__('Chat'), 'chat.php')
    ->add(__('Settings'));

if (isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/messenger_chat_settings.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    echo '<p>'.__('You do not have access to this action.').'</p>';

    return;
}

$personID = chatPersonID($session);
$services = chatServices($connection2, $session->get('absolutePath'));
$settingGateway = $container->get(SettingGateway::class);

$current = static function (string $name) use ($services): string {
    return $services['settings']->get($name);
};

echo '<p class="description">'
    . __('These settings apply to everyone using Chat in this TawasulOS.')
    . '</p>';

$form = Form::create('tosChatSettings', $session->get('absoluteURL').'/modules/'.$session->get('module').'/chat_settingsProcess.php');
$form->addHiddenValue('address', $session->get('address'));

$row = $form->addRow();
$row->addLabel('attachmentMaxSizeMB', __('Maximum Attachment Size'))
    ->description(__('The largest file or voice note a person can send. Larger uploads are refused.'));
$row->addNumber('attachmentMaxSizeMB')->setValue($current('attachmentMaxSizeMB'))->min(1)->max(512);

$row = $form->addRow();
$row->addLabel('pollTimeoutSeconds', __('Long Poll Timeout'))
    ->description(__('How long a delivery request waits before returning empty. Shorter means more frequent requests; longer uses more server workers.'));
$row->addNumber('pollTimeoutSeconds')->setValue($current('pollTimeoutSeconds'))->min(5)->max(120);

$row = $form->addRow();
$row->addLabel('presenceTimeoutSeconds', __('Presence Timeout'))
    ->description(__('How long a user is still shown as online after their last activity.'));
$row->addNumber('presenceTimeoutSeconds')->setValue($current('presenceTimeoutSeconds'))->min(15)->max(3600);

$row = $form->addRow();
$row->addLabel('maxGroupSize', __('Maximum Group Size'))
    ->description(__('The largest number of people in one group chat.'));
$row->addNumber('maxGroupSize')->setValue($current('maxGroupSize'))->min(2)->max(1000);

$row = $form->addRow();
$row->addLabel('messageRetentionDays', __('Message Retention'))
    ->description(__('Messages older than this are removed as people use Chat. Leave at 0 to keep messages indefinitely.'));
$row->addNumber('messageRetentionDays')->setValue($current('messageRetentionDays'))->min(0)->max(3650);

$row = $form->addRow();
$row->addLabel('readReceiptsEnabled', __('Read Receipts'))
    ->description(__('Show the sender when a message has been read.'));
$col = $row->addColumn('readReceiptsEnabled');
$col->addYesNo($current('readReceiptsEnabled'));

$row = $form->addRow();
$row->addLabel('typingIndicatorEnabled', __('Typing Indicator'))
    ->description(__('Show others when you are typing, and show when they are.'));
$col = $row->addColumn('typingIndicatorEnabled');
$col->addYesNo($current('typingIndicatorEnabled'));

$row = $form->addRow();
$row->addLabel('voiceNotesEnabled', __('Voice Notes'))
    ->description(__('Allow recording and sending voice notes.'));
$col = $row->addColumn('voiceNotesEnabled');
$col->addYesNo($current('voiceNotesEnabled'));

$row = $form->addRow();
$row->addLabel('editingEnabled', __('Editing Messages'))
    ->description(__('Allow a sent message to be edited. Edited messages are marked as edited.'));
$col = $row->addColumn('editingEnabled');
$col->addYesNo($current('editingEnabled'));

$row = $form->addRow();
$row->addLabel('unreadBadgeEnabled', __('Unread Badge'))
    ->description(__('Show the unread message count in the Chat menu.'));
$col = $row->addColumn('unreadBadgeEnabled');
$col->addYesNo($current('unreadBadgeEnabled'));

$row = $form->addRow();
$row->addFooter();
$row->addSubmit(__('Save'));

echo $form->getOutput();
