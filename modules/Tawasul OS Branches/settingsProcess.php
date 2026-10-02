<?php
/*
Tawasul OS Branches — save module settings.
*/

use TawasulOS\Data\Validator;
use TawasulOS\Domain\System\SettingGateway;

require_once '../../tawasul.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);
$URL = $session->get('absoluteURL').'/index.php?q=/modules/Tawasul OS Branches/settings.php';

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Branches/settings.php') == false
    || !tosBranchCanManage($session, $container)) {
    header("Location: {$URL}&return=error0");
    exit;
}

$settingGateway = $container->get(SettingGateway::class);

// Default branch: must be an existing active branch.
$defaultID = (int) ($_POST['branchDefaultID'] ?? 0);
if ($defaultID > 0) {
    $valid = $pdo->select('SELECT tawasulBranchID FROM tawasulBranch WHERE tawasulBranchID=:b AND active=\'Y\'', ['b' => $defaultID])->fetchColumn();
    if (empty($valid)) {
        header("Location: {$URL}&return=error1");
        exit;
    }
}

// Manager roles: intersect with roles that actually exist, so a stale id in the
// setting cannot grant access to something that was deleted. Ids are padded to
// match the ZEROFILL column before comparing.
$posted = array_values(array_unique(array_filter(array_map(function ($v) { return tosBranchNormaliseRoleID($v); }, (array) ($_POST['branchManagerRoles'] ?? [])), function ($v) { return $v !== ''; })));
$allowed = [];
if (!empty($posted)) {
    $placeholders = implode(',', array_fill(0, count($posted), '?'));
    $existing = array_map('tosBranchNormaliseRoleID', $pdo->select("SELECT tawasulRoleID FROM tawasulRole WHERE tawasulRoleID IN ({$placeholders})", $posted)->fetchAll(\PDO::FETCH_COLUMN));
    $allowed = array_values(array_intersect($posted, $existing));
}

$filterEnabled = (($_POST['branchFilterEnabled'] ?? 'N') === 'Y') ? 'Y' : 'N';

$ok = $settingGateway->updateSettingByScope(TOS_BRANCH_SCOPE, 'branchFilterEnabled', $filterEnabled);
$ok = $settingGateway->updateSettingByScope(TOS_BRANCH_SCOPE, 'branchDefaultID', (string) $defaultID) && $ok;
$ok = $settingGateway->updateSettingByScope(TOS_BRANCH_SCOPE, 'branchManagerRoles', implode(',', $allowed)) && $ok;

tosBranchResetCache();
getSystemSettings($guid, $connection2);

header("Location: {$URL}&return=".($ok ? 'success0' : 'error2'));