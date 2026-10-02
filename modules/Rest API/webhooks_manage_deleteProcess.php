<?php
/*
Gibbon REST API — Delete Webhook (process)
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

$restApiWebhookID = (string) ($_POST['restApiWebhookID'] ?? $_GET['restApiWebhookID'] ?? '');
$gateway = new WebhookGateway($pdo->getConnection());

if ($restApiWebhookID === '' || empty($gateway->selectByID($restApiWebhookID))) {
    header('Location: '.$URL->withReturn('error1'));
    exit;
}

$gateway->delete($restApiWebhookID);

header('Location: '.$URL->withReturn('success0'));
exit;
