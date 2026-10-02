<?php
/*
TawasulChat — group membership changes.

A form processor so the group screen works without JavaScript: the same screen
serves an admin removing someone and someone leaving, and neither should depend
on a script having loaded. The checks are the service's, not this file's.
*/

use Tos\Module\TawasulChat\Http\ChatException;

require_once __DIR__.'/../../tawasul.php';
require_once __DIR__.'/chatFunctions.php';

$address = $_POST['address'] ?? '';
$module = getModuleName($address);
$chatID = (int) ($_POST['chatID'] ?? 0);
$operation = (string) ($_POST['operation'] ?? '');

$listURL = $session->get('absoluteURL').'/index.php?q=/modules/'.($module ?: 'TawasulMessenger').'/messenger_chat_group_manage.php&chat='.$chatID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/messenger_chat_group_manageProcess.php') == false) {
    header("Location: {$listURL}&return=error0");
    exit;
}

$personID = chatPersonID($session);
$services = chatServices($connection2, $session->get('absolutePath'));

try {
    switch ($operation) {
        case 'add':
            $services['chatService']->addMembers($chatID, $personID, (array) ($_POST['personIDs'] ?? []));
            break;

        case 'remove':
            $services['chatService']->removeMember($chatID, $personID, (string) ($_POST['targetID'] ?? ''));
            break;

        case 'role':
            $services['chatService']->promote(
                $chatID,
                $personID,
                (string) ($_POST['targetID'] ?? ''),
                (string) ($_POST['role'] ?? 'member')
            );
            break;

        case 'rename':
            $services['chatService']->renameGroup(
                $chatID,
                $personID,
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['description'] ?? '')
            );
            // The disappearing window is set by the same submission, so apply it
            // here rather than making an admin submit the form twice.
            $services['chatService']->setDisappearing($chatID, $personID, (int) ($_POST['minutes'] ?? 0));
            break;

        case 'disappearing':
            $services['chatService']->setDisappearing($chatID, $personID, (int) ($_POST['minutes'] ?? 0));
            break;

        case 'leave':
            $services['chatService']->removeMember($chatID, $personID, $personID);
            // Leaving ends the conversation for this person, so send them to the
            // chat list rather than back to a group they are no longer in.
            header("Location: ".$session->get('absoluteURL')."/index.php?q=/modules/TawasulMessenger/messenger_chat.php&return=success0");
            exit;

        default:
            header("Location: {$listURL}&return=error3");
            exit;
    }

    header("Location: {$listURL}&return=success0");
} catch (ChatException $e) {
    $session->set('chatFlashError', $e->getMessage());
    header("Location: {$listURL}&return=chatError");
} catch (Throwable $e) {
    error_log('TawasulMessenger chat manage group: '.$e->getMessage());
    header("Location: {$listURL}&return=error2");
}
