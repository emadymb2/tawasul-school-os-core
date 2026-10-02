<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/cashAccounts_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/cashAccounts_edit.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'type' => ($_POST['type'] ?? '') === '' ? null : $_POST['type'], 'bankAccountNumber' => ($_POST['bankAccountNumber'] ?? '') === '' ? null : $_POST['bankAccountNumber'], 'tawasulFinanceAccountID' => ($_POST['tawasulFinanceAccountID'] ?? '') === '' ? null : $_POST['tawasulFinanceAccountID']];

try {
    $id = $_GET['id'] ?? '';
$pdo->update('UPDATE tawasulFinanceCashAccount SET name=:name, type=:type, bankAccountNumber=:bankAccountNumber, tawasulFinanceAccountID=:tawasulFinanceAccountID WHERE tawasulFinanceCashAccountID=:id', $data + ['id' => $id]);
    saLedger($container)->audit('CashAccount', $id, 'edit', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
