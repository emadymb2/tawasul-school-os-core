<?php
/*
TawasulChat — create a conversation from the new-chat form.

A form processor rather than a call to chat_ajax.php, so the screen works with
JavaScript disabled and so the browser's own navigation ends the request. The
validation and the permission checks are the services', not this file's: this
only turns a submitted form into a service call and a redirect.
*/

use TawasulOS\Domain\System\SettingGateway;
use Tos\Module\TawasulChat\Http\ChatException;

require_once __DIR__.'/../../tawasul.php';
require_once __DIR__.'/chatFunctions.php';

$address = $_POST['address'] ?? '';
$module = getModuleName($address);
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.($module ?: 'TawasulMessenger').'/messenger_chat_new.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/messenger_chat_newProcess.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$personID = chatPersonID($session);
$services = chatServices($connection2, $session->get('absolutePath'));
$settingGateway = $container->get(SettingGateway::class);

$personIDs = (array) ($_POST['personIDs'] ?? []);
$name = trim((string) ($_POST['name'] ?? ''));
$disappearing = (int) ($_POST['disappearingMinutes'] ?? 0);

try {
    if ($name !== '') {
        $chatID = $services['chatService']->createGroup($personID, $name, $personIDs, $disappearing);
    } else {
        $personIDs = array_values(array_filter(array_map('strval', $personIDs)));
        if (count($personIDs) !== 1) {
            throw new ChatException(
                __('Choose one person to start a conversation, or name the group.'),
                'personIDs'
            );
        }
        $chatID = $services['chatService']->openDirectChat($personID, $personIDs[0]);
    }

    $URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulMessenger/messenger_chat.php&chat='.$chatID;
    header("Location: {$URL}");
} catch (ChatException $e) {
    // The reason is carried in the session rather than encoded in the URL, and
    // chat_new.php registers a matching return code to show it. Putting the
    // message in the query string would leak it into history and referrers.
    $session->set('chatFlashError', $e->getMessage());
    $pageURL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulMessenger/messenger_chat_new.php';
    header("Location: {$pageURL}&return=chatError");
} catch (Throwable $e) {
    error_log('TawasulMessenger chat new chat: '.$e->getMessage());
    $pageURL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulMessenger/messenger_chat_new.php';
    header("Location: {$pageURL}&return=error2");
}
