<?php
/*
Gibbon REST API — Edit API Key (process)
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

$restApiKeyID = $_POST['restApiKeyID'] ?? '';
$name = trim($_POST['name'] ?? '');
$scopes = $_POST['scopes'] ?? [];

if ($restApiKeyID === '' || $name === '' || empty($scopes)) {
    header('Location: '.$URL->withReturn('error1'));
    exit;
}

$gateway = new ApiKeyGateway($pdo->getConnection());

if (empty($gateway->selectByID($restApiKeyID))) {
    header('Location: '.$URL->withReturn('error2'));
    exit;
}

$updated = $gateway->update($restApiKeyID, [
    'name' => $name,
    'description' => trim($_POST['description'] ?? ''),
    'scopes' => implode(',', (array) $scopes),
    'tawasulPersonID' => !empty($_POST['tawasulPersonID']) ? $_POST['tawasulPersonID'] : null,
    'tawasulSchoolYearID' => !empty($_POST['tawasulSchoolYearID']) ? $_POST['tawasulSchoolYearID'] : null,
    'ipAddresses' => trim($_POST['ipAddresses'] ?? ''),
    'rateLimit' => ($_POST['rateLimit'] ?? '') !== '' ? (int) $_POST['rateLimit'] : 600,
    'dateExpiry' => !empty($_POST['dateExpiry']) ? dateConvert($guid, $_POST['dateExpiry']) : null,
    'active' => ($_POST['active'] ?? 'Y') === 'Y' ? 'Y' : 'N',
]);

header('Location: '.$URL->withReturn($updated ? 'success0' : 'error2'));
exit;
