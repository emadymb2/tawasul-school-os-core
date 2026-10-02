<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/discounts_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/discounts_add.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'kind' => ($_POST['kind'] ?? '') === '' ? null : $_POST['kind'], 'method' => ($_POST['method'] ?? '') === '' ? null : $_POST['method'], 'value' => ($_POST['value'] ?? '') === '' ? null : $_POST['value'], 'tawasulFinanceExpenseAccountID' => ($_POST['tawasulFinanceExpenseAccountID'] ?? '') === '' ? null : $_POST['tawasulFinanceExpenseAccountID']];

try {
    $id = $pdo->insert('INSERT INTO tawasulFinanceDiscount (name,kind,method,value,tawasulFinanceExpenseAccountID) VALUES (:name,:kind,:method,:value,:tawasulFinanceExpenseAccountID)', $data);
    saLedger($container)->audit('Discount', $id, 'add', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
