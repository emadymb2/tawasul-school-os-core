<?php
use TawasulOS\Data\Validator;
use TawasulOS\Domain\System\SettingGateway;

require_once '../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);
$URL = $session->get('absoluteURL').'/index.php?q=/modules/Tawasul OS Tools/settings.php';

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Tools/settings.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$serviceURL = trim($_POST['aiServiceURL'] ?? '');
if (!filter_var($serviceURL, FILTER_VALIDATE_URL) || stripos($serviceURL, 'https://') !== 0) {
    header("Location: {$URL}&return=error1");
    exit;
}

$settingGateway = $container->get(SettingGateway::class);
$ok = $settingGateway->updateSettingByScope('Tawasul OS Tools', 'aiServiceURL', $serviceURL);
$token = trim($_POST['aiServiceToken'] ?? '');
if ($token !== '') {
    $ok = $settingGateway->updateSettingByScope('Tawasul OS Tools', 'aiServiceToken', $token) && $ok;
}

header("Location: {$URL}&return=".($ok ? 'success0' : 'warning1'));
