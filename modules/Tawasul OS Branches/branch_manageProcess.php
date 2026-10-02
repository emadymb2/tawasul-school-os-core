<?php
/*
Tawasul OS Branches — save a branch (add or edit).
*/

use TawasulOS\Data\Validator;

require_once '../../tawasul.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['noteAr' => 'RAW', 'noteEn' => 'RAW']);
$URL = $session->get('absoluteURL').'/index.php?q=/modules/Tawasul OS Branches/branch_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Branches/branch_manage.php') == false
    || !tosBranchCanManage($session, $container)) {
    header("Location: {$URL}&return=error0");
    exit;
}

$branchID = (int) ($_POST['tawasulBranchID'] ?? 0);
$returnURL = $branchID > 0 ? "{$URL}&tawasulBranchID={$branchID}" : $URL;

$clean = function ($v, $max) { return mb_substr(trim((string) $v), 0, $max); };

$code = tosBranchNormaliseCode($_POST['code'] ?? '');
if ($code === null) {
    header("Location: {$returnURL}&return=error1");
    exit;
}

$nameEn = $clean($_POST['nameEn'] ?? '', 150);
if ($nameEn === '') {
    header("Location: {$returnURL}&return=error1");
    exit;
}

// Arabic name is optional; fall back to the English one so the register never
// shows an empty cell, and so Format-driven labels stay readable.
$nameAr = $clean($_POST['nameAr'] ?? '', 150);
if ($nameAr === '') {
    $nameAr = $nameEn;
}

$email = $clean($_POST['email'] ?? '', 150);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: {$returnURL}&return=error2");
    exit;
}

$headPersonID = (int) ($_POST['headPersonID'] ?? 0);
if ($headPersonID > 0) {
    $isStaff = $pdo->select('SELECT tawasulStaffID FROM tawasulStaff WHERE tawasulPersonID=:p', ['p' => $headPersonID])->fetchColumn();
    if (empty($isStaff)) {
        $headPersonID = 0;
    }
}

$data = [
    'code' => $code,
    'nameAr' => $nameAr,
    'nameEn' => $nameEn,
    'addressAr' => $clean($_POST['addressAr'] ?? '', 255),
    'addressEn' => $clean($_POST['addressEn'] ?? '', 255),
    'phone' => $clean($_POST['phone'] ?? '', 40),
    'email' => $email,
    'headPersonID' => $headPersonID > 0 ? $headPersonID : null,
    'noteAr' => $clean($_POST['noteAr'] ?? '', 2000),
    'noteEn' => $clean($_POST['noteEn'] ?? '', 2000),
];

if ($branchID > 0) {
    $exists = $pdo->select('SELECT tawasulBranchID FROM tawasulBranch WHERE tawasulBranchID=:b', ['b' => $branchID])->fetchColumn();
    if (empty($exists)) {
        header("Location: {$URL}&return=error0");
        exit;
    }

    $data['active'] = (($_POST['active'] ?? 'Y') === 'N') ? 'N' : 'Y';
    $sets = [];
    $params = [];
    foreach ($data as $col => $value) {
        $sets[] = "`{$col}` = :{$col}";
        $params[$col] = $value;
    }
    $params['b'] = $branchID;
    // update(), not insert(): insert() returns the last insert id, which for an
    // UPDATE is whatever id was generated previously and would always look true.
    $ok = $pdo->update("UPDATE tawasulBranch SET ".implode(', ', $sets)." WHERE tawasulBranchID = :b", $params);
} else {
    // The code is the only natural key, so it has to be checked explicitly.
    $taken = $pdo->select('SELECT tawasulBranchID FROM tawasulBranch WHERE code=:c', ['c' => $code])->fetchColumn();
    if (!empty($taken)) {
        header("Location: {$returnURL}&return=error3");
        exit;
    }

    $ok = $pdo->insert(
        'INSERT INTO tawasulBranch (code, nameAr, nameEn, addressAr, addressEn, phone, email, headPersonID, noteAr, noteEn)
         VALUES (:code, :nameAr, :nameEn, :addressAr, :addressEn, :phone, :email, :headPersonID, :noteAr, :noteEn)',
        $data
    );

    if (!empty($ok)) {
        $branchID = (int) $ok;
        // A brand new branch becomes the one the administrator is looking at,
        // otherwise they would keep editing the previous campus.
        $session->set('tawasulBranchActive', $branchID);
    }
}

tosBranchResetCache();
getSystemSettings($guid, $connection2);

$base = $session->get('absoluteURL').'/index.php?q=/modules/Tawasul OS Branches/branches.php';
header('Location: '.$base.'&return='.($ok ? 'success0' : 'error4'));