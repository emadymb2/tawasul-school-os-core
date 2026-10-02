<?php
/*
TawasulChat — manage a group's membership.

Reached from the conversation header by someone who holds the Manage Chat Group
action. Membership changes go through the endpoint rather than a form post,
because removing somebody and adding somebody are the same operation with
different arguments and the group screen is already a live view.
*/

use Tos\Module\TawasulChat\Http\ChatException;

require_once __DIR__.'/chatFunctions.php';

$page->breadcrumbs
    ->add(__('Chat'), 'chat.php')
    ->add(__('Manage Group'));

$page->return->addReturns([
    'chatError' => $session->get('chatFlashError') ?: __('The group could not be changed.'),
]);
$session->forget('chatFlashError');

if (isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/messenger_chat_group_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    echo '<p>'.__('You do not have access to this action.').'</p>';

    return;
}

$chatID = (int) ($_GET['chat'] ?? 0);
if ($chatID <= 0) {
    $page->addError(__('No conversation was selected.'));
    echo '<p>'.__('No conversation was selected.').'</p>';

    return;
}

$personID = chatPersonID($session);
$services = chatServices($connection2, $session->get('absolutePath'));

try {
    $header = $services['chatService']->header($chatID, $personID);
} catch (ChatException $e) {
    $page->addError($e->getMessage());
    echo '<p>'.htmlspecialchars($e->getMessage()).'</p>';

    return;
} catch (Throwable $e) {
    $page->addError(__('That conversation is not available.'));
    echo '<p>'.__('That conversation is not available.').'</p>';

    return;
}

if ($header['type'] !== 'group') {
    $page->addError(__('That conversation is not a group.'));
    echo '<p>'.__('That conversation is not a group.').'</p>';

    return;
}

$myRole = $header['myRole'];
$canAdmin = in_array($myRole, ['owner', 'admin'], true);
$isOwner = $myRole === 'owner';
?>

<p>
    <strong><?php echo htmlspecialchars($header['name']); ?></strong> —
    <?php echo sprintf(__('%d people'), (int) $header['participantCount']); ?>
</p>

<?php if (!$canAdmin): ?>
    <p class="description"><?php echo __('Only a group admin can change the membership or settings of this group.'); ?></p>
<?php endif; ?>

<table class="w-full">
    <thead>
        <tr>
            <th><?php echo __('Person'); ?></th>
            <th><?php echo __('Role'); ?></th>
            <th><?php echo __('Status'); ?></th>
            <th class="w-24"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($header['participants'] as $person): ?>
            <tr>
                <td>
                    <?php echo htmlspecialchars(trim($person['preferredName'].' '.$person['surname'])); ?>
                    <?php if ($person['isMe']): ?>
                        <span class="description"><?php echo __('(you)'); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($isOwner && !$person['isMe']): ?>
                        <form method="post" action="<?php echo $session->get('absoluteURL'); ?>/modules/TawasulMessenger/messenger_chat_group_manageProcess.php">
                            <input type="hidden" name="address" value="<?php echo htmlspecialchars($session->get('address')); ?>">
                            <input type="hidden" name="chatID" value="<?php echo (int) $chatID; ?>">
                            <input type="hidden" name="targetID" value="<?php echo htmlspecialchars($person['tawasulPersonID']); ?>">
                            <input type="hidden" name="operation" value="role">
                            <select name="role" onchange="this.form.submit()">
                                <option value="member"<?php echo $person['role'] === 'member' ? ' selected' : ''; ?>><?php echo __('Member'); ?></option>
                                <option value="admin"<?php echo $person['role'] === 'admin' ? ' selected' : ''; ?>><?php echo __('Admin'); ?></option>
                            </select>
                        </form>
                    <?php else: ?>
                        <?php echo htmlspecialchars(ucfirst($person['role'])); ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $statusLabel = $person['status'] === 'online' ? __('online')
                        : ($person['status'] === 'away' ? __('away') : __('offline'));
                    echo htmlspecialchars($statusLabel);
                    ?>
                </td>
                <td>
                    <?php if ($canAdmin && !$person['isMe']): ?>
                        <form method="post" action="<?php echo $session->get('absoluteURL'); ?>/modules/TawasulMessenger/messenger_chat_group_manageProcess.php" class="text-right">
                            <input type="hidden" name="address" value="<?php echo htmlspecialchars($session->get('address')); ?>">
                            <input type="hidden" name="chatID" value="<?php echo (int) $chatID; ?>">
                            <input type="hidden" name="targetID" value="<?php echo htmlspecialchars($person['tawasulPersonID']); ?>">
                            <input type="hidden" name="operation" value="remove">
                            <button type="submit" class="btn btn-link"><?php echo __('Remove'); ?></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($canAdmin): ?>
    <hr>

    <h2><?php echo __('Add People'); ?></h2>
    <p class="description"><?php echo __('Search for people to add to this group.'); ?></p>
    <form id="tos-chat-add-people" method="post"
          action="<?php echo $session->get('absoluteURL'); ?>/modules/TawasulMessenger/messenger_chat_group_manageProcess.php">
        <input type="hidden" name="address" value="<?php echo htmlspecialchars($session->get('address')); ?>">
        <input type="hidden" name="chatID" value="<?php echo (int) $chatID; ?>">
        <input type="hidden" name="operation" value="add">
        <label class="w-full" for="tos-chat-add-search"><?php echo __('Find someone'); ?></label>
        <select name="personIDs[]" id="tos-chat-add-people-select" size="10" multiple>
            <?php foreach ($services['chatService']->contacts($personID, '', 60) as $contact): ?>
                <option value="<?php echo htmlspecialchars($contact['tawasulPersonID']); ?>">
                    <?php echo htmlspecialchars($contact['title'].' — '.($contact['roleName'] ?: '')); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary mt-2"><?php echo __('Add to Group'); ?></button>
    </form>

    <hr>

    <h2><?php echo __('Group Settings'); ?></h2>
    <form method="post" action="<?php echo $session->get('absoluteURL'); ?>/modules/TawasulMessenger/messenger_chat_group_manageProcess.php" class="w-full">
        <input type="hidden" name="address" value="<?php echo htmlspecialchars($session->get('address')); ?>">
        <input type="hidden" name="chatID" value="<?php echo (int) $chatID; ?>">
        <input type="hidden" name="operation" value="rename">

        <label class="w-full" for="group-name"><?php echo __('Group Name'); ?></label>
        <input type="text" id="group-name" name="name" maxlength="100"
               value="<?php echo htmlspecialchars($header['name']); ?>">

        <label class="w-full mt-2" for="group-description"><?php echo __('Description'); ?></label>
        <input type="text" id="group-description" name="description" maxlength="255"
               value="<?php echo htmlspecialchars($header['description']); ?>">

        <label class="w-full mt-2" for="group-disappearing"><?php echo __('Disappearing Messages'); ?></label>
        <select id="group-disappearing" name="minutes">
            <?php
            $options = [
                0 => __('Off'),
                3600 => __('After 1 hour'),
                86400 => __('After 1 day'),
                604800 => __('After 1 week'),
            ];
            foreach ($options as $value => $label):
                ?>
                <option value="<?php echo (int) $value; ?>"<?php echo (int) $header['disappearingMinutes'] === $value ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primary mt-2"><?php echo __('Save'); ?></button>
    </form>
<?php endif; ?>

<hr>

<form method="post" action="<?php echo $session->get('absoluteURL'); ?>/modules/TawasulMessenger/messenger_chat_group_manageProcess.php">
    <input type="hidden" name="address" value="<?php echo htmlspecialchars($session->get('address')); ?>">
    <input type="hidden" name="chatID" value="<?php echo (int) $chatID; ?>">
    <input type="hidden" name="operation" value="leave">
    <button type="submit" class="btn btn-link"><?php echo __('Leave this group'); ?></button>
</form>
