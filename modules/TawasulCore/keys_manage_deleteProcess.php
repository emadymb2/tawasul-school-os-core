<?php
/*
TawasulOS REST API — Delete API Key (process)
Licensed under the GNU General Public License v3 or later.
*/

use Tos\Http\Url;
use Tos\Module\TawasulCore\Domain\ApiKeyGateway;

require_once __DIR__.'/../../tawasul.php';
require_once __DIR__.'/src/bootstrap.php';

$URL = Url::fromModuleRoute('TawasulCore', 'keys_manage');

if (isActionAccessible($guid, $connection2, '/modules/TawasulCore/keys_manage.php') == false) {
    header('Location: '.$URL->withReturn('error0'));
    exit;
}

$tos_api_keyID = (string) ($_POST['tos_api_keyID'] ?? $_GET['tos_api_keyID'] ?? '');

if ($tos_api_keyID === '') {
    header('Location: '.$URL->withReturn('error1'));
    exit;
}

$deleted = (new ApiKeyGateway($pdo->getConnection()))->delete($tos_api_keyID);

header('Location: '.$URL->withReturn($deleted ? 'success0' : 'error2'));
exit;
