<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/salaryComponents_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/salaryComponents_add.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'type' => ($_POST['type'] ?? '') === '' ? null : $_POST['type'], 'tawasulFinanceAccountID' => ($_POST['tawasulFinanceAccountID'] ?? '') === '' ? null : $_POST['tawasulFinanceAccountID']];

try {
    $id = $pdo->insert('INSERT INTO tawasulFinanceSalaryComponent (name,type,tawasulFinanceAccountID) VALUES (:name,:type,:tawasulFinanceAccountID)', $data);
    saLedger($container)->audit('SalaryComponent', $id, 'add', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
