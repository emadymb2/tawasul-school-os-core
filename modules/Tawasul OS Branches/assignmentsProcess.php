<?php
/*
Tawasul OS Branches — apply branch assignments.
*/

use TawasulOS\Data\Validator;

require_once '../../tawasul.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$entityKey = tosBranchValidEntity($_POST['entity'] ?? '');
$URL = $session->get('absoluteURL').'/index.php?q=/modules/Tawasul OS Branches/assignments.php&entity='.urlencode((string) ($_POST['entity'] ?? 'staff'));

if (isActionAccessible($guid, $connection2, '/modules/Tawasul OS Branches/assignments.php') == false
    || !tosBranchCanManage($session, $container)) {
    header("Location: {$URL}&return=error0");
    exit;
}

if ($entityKey === null) {
    header("Location: {$URL}&return=error1");
    exit;
}

$targetBranchID = (int) ($_POST['targetBranch'] ?? 0);
if ($targetBranchID <= 0) {
    header("Location: {$URL}&return=error1");
    exit;
}

// The target branch must exist and be active: this is what stops a crafted
// POST from clearing the branch off a batch of records.
$validTarget = $pdo->select('SELECT tawasulBranchID FROM tawasulBranch WHERE tawasulBranchID=:b AND active=\'Y\'', ['b' => $targetBranchID])->fetchColumn();
if (empty($validTarget)) {
    header("Location: {$URL}&return=error1");
    exit;
}

$ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), function ($v) { return $v > 0; })));
if (empty($ids)) {
    header("Location: {$URL}&return=error2");
    exit;
}

$entity = tosBranchEntities()[$entityKey];

// Ids are bound as parameters rather than interpolated, and the table and
// column names come from the allow-list, never from the request. Named binds
// are used because a repeated ":id" would need one placeholder per element and
// the connection wrapper passes a flat map.
$params = ['target' => $targetBranchID];
$in = [];
foreach ($ids as $i => $id) {
    $in[] = ':id'.$i;
    $params['id'.$i] = $id;
}

$sql = "UPDATE `{$entity['table']}` SET `tawasulBranchID` = :target WHERE `{$entity['id']}` IN (".implode(', ', $in).')';

$moved = $pdo->select($sql, $params)->rowCount();

header("Location: {$URL}&return=".($moved > 0 ? 'success0' : 'error3'));