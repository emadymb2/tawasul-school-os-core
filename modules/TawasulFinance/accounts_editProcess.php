<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/accounts_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/accounts_edit.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['code' => ($_POST['code'] ?? '') === '' ? null : $_POST['code'], 'name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'type' => ($_POST['type'] ?? '') === '' ? null : $_POST['type'], 'parentAccountID' => ($_POST['parentAccountID'] ?? '') === '' ? null : $_POST['parentAccountID'], 'isPosting' => ($_POST['isPosting'] ?? '') === '' ? null : $_POST['isPosting'], 'active' => ($_POST['active'] ?? '') === '' ? null : $_POST['active']];

if (($data['parentAccountID'] ?? null) && $data['parentAccountID'] == ($_GET['id'] ?? 0)) { header("Location: {$URL}&return=error1"); exit; }
try {
    $id = $_GET['id'] ?? '';
$pdo->update('UPDATE tawasulFinanceAccount SET code=:code, name=:name, type=:type, parentAccountID=:parentAccountID, isPosting=:isPosting, active=:active WHERE tawasulFinanceAccountID=:id', $data + ['id' => $id]);
    saLedger($container)->audit('Account', $id, 'edit', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
