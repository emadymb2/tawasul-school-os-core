<?php
/*
TawasulOS REST API — Delete Webhook (process)
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

$tos_api_webhookID = (string) ($_POST['tos_api_webhookID'] ?? $_GET['tos_api_webhookID'] ?? '');
$gateway = new WebhookGateway($pdo->getConnection());

if ($tos_api_webhookID === '' || empty($gateway->selectByID($tos_api_webhookID))) {
    header('Location: '.$URL->withReturn('error1'));
    exit;
}

$gateway->delete($tos_api_webhookID);

header('Location: '.$URL->withReturn('success0'));
exit;
