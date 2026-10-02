<?php
/*
Gibbon REST API — Delete API Key (process)
Licensed under the GNU General Public License v3 or later.
*/

use TawasulOS\Http\Url;
use Gibbon\Module\RestAPI\Domain\ApiKeyGateway;

require_once __DIR__.'/../../tawasul.php';
require_once __DIR__.'/src/bootstrap.php';

$URL = Url::fromModuleRoute('Rest API', 'keys_manage');

if (isActionAccessible($guid, $connection2, '/modules/Rest API/keys_manage.php') == false) {
    header('Location: '.$URL->withReturn('error0'));
    exit;
}

$restApiKeyID = (string) ($_POST['restApiKeyID'] ?? $_GET['restApiKeyID'] ?? '');

if ($restApiKeyID === '') {
    header('Location: '.$URL->withReturn('error1'));
    exit;
}

$deleted = (new ApiKeyGateway($pdo->getConnection()))->delete($restApiKeyID);

header('Location: '.$URL->withReturn($deleted ? 'success0' : 'error2'));
exit;
