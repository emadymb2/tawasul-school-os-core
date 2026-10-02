<?php
/*
Tawasul OS Branches — switch the active branch.
*/

use TawasulOS\Data\Validator;

require_once '../../tawasul.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Tawasul OS Branches/branches.php';

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Branches/branches.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$branchID = (int) ($_POST['tawasulBranchActive'] ?? 0);

// Only an active branch may be selected. Checking here rather than trusting
// the form is what stops a tampered POST from parking a user on a branch that
// has been switched off.
$ok = false;
if ($branchID > 0) {
    $ok = (bool) $pdo->select('SELECT tawasulBranchID FROM tawasulBranch WHERE tawasulBranchID=:b AND active=\'Y\'', ['b' => $branchID])->fetchColumn();
}

if (!$ok) {
    header("Location: {$URL}&return=error1");
    exit;
}

$session->set('tawasulBranchActive', $branchID);
tosBranchResetCache();

header("Location: {$URL}&return=success0");