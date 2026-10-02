<?php
/*
TawasulOS REST API — Edit Webhook (process)
Licensed under the GNU General Public License v3 or later.
*/

use Tos\Http\Url;
use Tos\Module\TawasulCore\Domain\WebhookGateway;

require_once __DIR__.'/../../tawasul.php';
require_once __DIR__.'/src/bootstrap.php';

$URL = Url::fromModuleRoute('TawasulCore', 'webhooks_manage');

if (isActionAccessible($guid, $connection2, '/modules/TawasulCore/webhooks_manage.php') == false) {
    header('Location: '.$URL->withReturn('error0'));
    exit;
}

$tos_api_webhookID = (string) ($_POST['tos_api_webhookID'] ?? '');
$name = trim($_POST['name'] ?? '');
$url = trim($_POST['url'] ?? '');
$events = (array) ($_POST['events'] ?? []);

if ($tos_api_webhookID === '' || $name === '' || $url === '' || empty($events) || !filter_var($url, FILTER_VALIDATE_URL)) {
    header('Location: '.$URL->withReturn('error1'));
    exit;
}

$parsed = parse_url($url);
if (($parsed['scheme'] ?? '') !== 'http' && ($parsed['scheme'] ?? '') !== 'https') {
    header('Location: '.$URL->withReturn('error1'));
    exit;
}

$gateway = new WebhookGateway($pdo->getConnection());

if (empty($gateway->selectByID($tos_api_webhookID))) {
    header('Location: '.$URL->withReturn('error2'));
    exit;
}

$updated = $gateway->update($tos_api_webhookID, [
    'name' => $name,
    'url' => $url,
    'events' => implode(',', $events),
    'headers' => trim($_POST['headers'] ?? ''),
    'active' => ($_POST['active'] ?? 'Y') === 'Y' ? 'Y' : 'N',
]);

header('Location: '.$URL->withReturn($updated ? 'success0' : 'error2'));
exit;
