<?php
/*
TawasulOS REST API — Edit API Key (process)
Licensed under the GNU General Public License v3 or later.
*/

use Tos\Http\Url;
use Tos\Module\TawasulCore\Domain\ApiKeyGateway;
use TawasulOS\Services\Format;

require_once __DIR__.'/../../tawasul.php';
require_once __DIR__.'/src/bootstrap.php';

$URL = Url::fromModuleRoute('TawasulCore', 'keys_manage');

if (isActionAccessible($guid, $connection2, '/modules/TawasulCore/keys_manage.php') == false) {
    header('Location: '.$URL->withReturn('error0'));
    exit;
}

$tos_api_keyID = $_POST['tos_api_keyID'] ?? '';
$name = trim($_POST['name'] ?? '');
$scopes = $_POST['scopes'] ?? [];

if ($tos_api_keyID === '' || $name === '' || empty($scopes)) {
    header('Location: '.$URL->withReturn('error1'));
    exit;
}

$gateway = new ApiKeyGateway($pdo->getConnection());

if (empty($gateway->selectByID($tos_api_keyID))) {
    header('Location: '.$URL->withReturn('error2'));
    exit;
}

$updated = $gateway->update($tos_api_keyID, [
    'name' => $name,
    'description' => trim($_POST['description'] ?? ''),
    'scopes' => implode(',', (array) $scopes),
    'tawasulPersonID' => !empty($_POST['tawasulPersonID']) ? $_POST['tawasulPersonID'] : null,
    'tawasulSchoolYearID' => !empty($_POST['tawasulSchoolYearID']) ? $_POST['tawasulSchoolYearID'] : null,
    'ipAllowList' => trim($_POST['ipAddresses'] ?? ''),
    'rateLimit' => ($_POST['rateLimit'] ?? '') !== '' ? (int) $_POST['rateLimit'] : 600,
    'dateExpiry' => !empty($_POST['dateExpiry']) ? Format::dateConvert($_POST['dateExpiry']) : null,
    'active' => ($_POST['active'] ?? 'Y') === 'Y' ? 'Y' : 'N',
]);

header('Location: '.$URL->withReturn($updated ? 'success0' : 'error2'));
exit;
