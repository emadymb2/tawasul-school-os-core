<?php
use TawasulOS\FileUploader;
use TawasulOS\Data\Validator;
use TawasulOS\Domain\System\SettingGateway;

require_once '../../tawasul.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);
$URL = $session->get('absoluteURL').'/index.php?q=/modules/Tawasul OS Tools/branding.php';

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Tools/branding.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$name = trim($_POST['organisationName'] ?? '');
if ($name === '') {
    header("Location: {$URL}&return=error1");
    exit;
}

$settingGateway = $container->get(SettingGateway::class);
$ok = true;

// School name and logo are the platform's own System settings (used by the login page).
$ok = $settingGateway->updateSettingByScope('System', 'organisationName', $name) && $ok;

if (!empty($_FILES['organisationLogoFile']['tmp_name'])) {
    $fileUploader = new FileUploader($pdo, $session);
    $fileUploader->getFileExtensions('Graphics/Design');
    $logo = $fileUploader->uploadFromPost($_FILES['organisationLogoFile'], 'logo');
    if (!empty($logo)) {
        $ok = $settingGateway->updateSettingByScope('System', 'organisationLogo', $logo) && $ok;
    } else {
        $ok = false;
    }
}

$useCustom = ($_POST['brandUseCustom'] ?? 'N') === 'Y' ? 'Y' : 'N';
$primary   = tosValidHex($_POST['brandPrimary'] ?? '', '#1f4a33');
$accent    = tosValidHex($_POST['brandAccent'] ?? '', '#e8664f');
$highlight = tosValidHex($_POST['brandHighlight'] ?? '', '#e9b949');

foreach (['brandUseCustom' => $useCustom, 'brandPrimary' => $primary, 'brandAccent' => $accent, 'brandHighlight' => $highlight] as $key => $value) {
    $ok = $settingGateway->updateSettingByScope('Tawasul OS Tools', $key, $value) && $ok;
}

$ok = tosWriteBrandCss($session->get('absolutePath'), $useCustom, $primary, $accent, $highlight) && $ok;

getSystemSettings($guid, $connection2);
$session->set('pageLoads', null);

header("Location: {$URL}&return=".($ok ? 'success0' : 'warning1'));
