<?php
/*
TawasulOS REST API — Add API Key (process)
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

$name = trim($_POST['name'] ?? '');
$scopes = $_POST['scopes'] ?? [];

if ($name === '' || empty($scopes)) {
    header('Location: '.$URL->withReturn('error1'));
    exit;
}

$gateway = new ApiKeyGateway($pdo->getConnection());

// The secret exists in plain text only in this response; the database keeps a hash.
$generated = ApiKeyGateway::generate();

$inserted = $gateway->insert([
    'name' => $name,
    'description' => trim($_POST['description'] ?? ''),
    'keyPrefix' => $generated['prefix'],
    'keyHash' => $generated['hash'],
    'scopes' => implode(',', (array) $scopes),
    'tawasulPersonID' => !empty($_POST['tawasulPersonID']) ? $_POST['tawasulPersonID'] : null,
    'tawasulSchoolYearID' => !empty($_POST['tawasulSchoolYearID']) ? $_POST['tawasulSchoolYearID'] : null,
    'ipAllowList' => trim($_POST['ipAddresses'] ?? $_POST['ipAllowList'] ?? ''),
    'rateLimit' => !empty($_POST['rateLimit']) ? (int) $_POST['rateLimit'] : 600,
    'dateExpiry' => !empty($_POST['dateExpiry']) ? Format::dateConvert($_POST['dateExpiry']) : null,
    'active' => ($_POST['active'] ?? 'Y') === 'Y' ? 'Y' : 'N',
    'tawasulPersonIDCreator' => $session->get('tawasulPersonID'),
]);

if (empty($inserted)) {
    header('Location: '.$URL->withReturn('error2'));
    exit;
}

$session->set('restApiNewKeySecret', $generated['secret']);
header('Location: '.$URL->withReturn('success0'));
exit;
