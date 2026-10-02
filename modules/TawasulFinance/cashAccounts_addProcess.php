<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/cashAccounts_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/cashAccounts_add.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'type' => ($_POST['type'] ?? '') === '' ? null : $_POST['type'], 'bankAccountNumber' => ($_POST['bankAccountNumber'] ?? '') === '' ? null : $_POST['bankAccountNumber'], 'tawasulFinanceAccountID' => ($_POST['tawasulFinanceAccountID'] ?? '') === '' ? null : $_POST['tawasulFinanceAccountID']];

try {
    $id = $pdo->insert('INSERT INTO tawasulFinanceCashAccount (name,type,bankAccountNumber,tawasulFinanceAccountID) VALUES (:name,:type,:bankAccountNumber,:tawasulFinanceAccountID)', $data);
    saLedger($container)->audit('CashAccount', $id, 'add', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
