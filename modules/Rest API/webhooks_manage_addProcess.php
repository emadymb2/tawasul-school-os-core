<?php
/*
Gibbon REST API — Add Webhook (process)
Licensed under the GNU General Public License v3 or later.
*/

use TawasulOS\Http\Url;
use Gibbon\Module\RestAPI\Domain\WebhookGateway;

require_once __DIR__.'/../../tawasul.php';
require_once __DIR__.'/src/bootstrap.php';

$URL = Url::fromModuleRoute('Rest API', 'webhooks_manage');

if (isActionAccessible($guid, $connection2, '/modules/Rest API/webhooks_manage.php') == false) {
    header('Location: '.$URL->withReturn('error0'));
    exit;
}

$name = trim($_POST['name'] ?? '');
$url = trim($_POST['url'] ?? '');
$events = (array) ($_POST['events'] ?? []);

if ($name === '' || $url === '' || empty($events) || !filter_var($url, FILTER_VALIDATE_URL)) {
    header('Location: '.$URL->withReturn('error1'));
    exit;
}

$parsed = parse_url($url);
if (($parsed['scheme'] ?? '') !== 'http' && ($parsed['scheme'] ?? '') !== 'https') {
    header('Location: '.$URL->withReturn('error1'));
    exit;
}

$gateway = new WebhookGateway($pdo->getConnection());

$inserted = $gateway->insert([
    'name' => $name,
    'url' => $url,
    'events' => implode(',', $events),
    'secret' => WebhookGateway::generateSecret(),
    'headers' => trim($_POST['headers'] ?? ''),
    'active' => ($_POST['active'] ?? 'Y') === 'Y' ? 'Y' : 'N',
    'tawasulPersonIDCreator' => $session->get('tawasulPersonID'),
]);

if (empty($inserted)) {
    header('Location: '.$URL->withReturn('error2'));
    exit;
}

header('Location: '.$URL->withReturn('success0'));
exit;
