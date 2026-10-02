<?php
/*
TawasulChat — save module settings.

Each value is validated against the same bounds the form advertises, so a
request that bypasses the form still cannot store a poll timeout of zero or an
attachment limit that would be refused by every upload.
*/

use TawasulOS\Domain\System\SettingGateway;
use Tos\Module\TawasulChat\Support\Settings;

require_once __DIR__.'/../../tawasul.php';
require_once __DIR__.'/chatFunctions.php';

$address = $_POST['address'] ?? '';
$module = getModuleName($address);
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.($module ?: 'TawasulMessenger').'/messenger_chat_settings.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulMessenger/messenger_chat_settingsProcess.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// name => [type, min, max]
$fields = [
    'attachmentMaxSizeMB' => ['int', 1, 512],
    'pollTimeoutSeconds' => ['int', 5, 120],
    'presenceTimeoutSeconds' => ['int', 15, 3600],
    'maxGroupSize' => ['int', 2, 1000],
    'messageRetentionDays' => ['int', 0, 3650],
    'readReceiptsEnabled' => ['bool', null, null],
    'typingIndicatorEnabled' => ['bool', null, null],
    'voiceNotesEnabled' => ['bool', null, null],
    'editingEnabled' => ['bool', null, null],
    'unreadBadgeEnabled' => ['bool', null, null],
];

$settingGateway = $container->get(SettingGateway::class);
$ok = true;
$problems = [];

foreach ($fields as $name => [$type, $min, $max]) {
    if (!array_key_exists($name, $_POST)) {
        $problems[] = $name;
        continue;
    }

    if ($type === 'bool') {
        $value = ($_POST[$name] ?? 'N') === 'Y' ? 'Y' : 'N';
    } else {
        $raw = $_POST[$name];
        if (!is_numeric($raw)) {
            $problems[] = $name;
            continue;
        }
        $value = max($min, min($max, (int) $raw));
    }

    if (!$settingGateway->updateSettingByScope('TawasulMessenger', $name, (string) $value)) {
        $ok = false;
    }
}

// The settings object caches per request; drop it so anything later in this
// same request reads what was just written.
Settings::flush();

$URL .= $ok ? '&return=success0' : '&return=error2';
header("Location: {$URL}");
