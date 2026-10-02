<?php
/*
TawasulOS REST API — Manage Settings (process)
Licensed under the GNU General Public License v3 or later.
*/

use Tos\Http\Url;
use Tos\Domain\System\SettingGateway;
use Tos\Module\TawasulCore\Domain\ApiLogGateway;

require_once __DIR__.'/../../tawasul.php';
require_once __DIR__.'/src/bootstrap.php';

$URL = Url::fromModuleRoute('TawasulCore', 'settings_manage');

if (isActionAccessible($guid, $connection2, '/modules/TawasulCore/settings_manage.php') == false) {
    header('Location: '.$URL->withReturn('error0'));
    exit;
}

$settingGateway = $container->get(SettingGateway::class);

$fields = [
    'apiEnabled', 'allowWrites', 'exposeModules', 'allowPasswordGrant',
    'tokenLifetime', 'refreshLifetime', 'enforceRolePermissions',
    'defaultPageSize', 'maxPageSize', 'corsOrigins',
    'logRequests', 'logRetentionDays',
];

$partialFail = false;

foreach ($fields as $field) {
    $value = trim((string) ($_POST[$field] ?? ''));
    $partialFail = !$settingGateway->updateSettingByScope('TawasulCore', $field, $value) || $partialFail;
}

// Saving settings is a natural moment to apply the retention policy.
$retention = (int) ($_POST['logRetentionDays'] ?? 30);
if ($retention > 0) {
    (new ApiLogGateway($pdo->getConnection()))->prune($retention);
}

header('Location: '.$URL->withReturn($partialFail ? 'warning1' : 'success0'));
exit;
