<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/feeItems_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feeItems_add.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'category' => ($_POST['category'] ?? '') === '' ? null : $_POST['category'], 'tawasulFinanceRevenueAccountID' => ($_POST['tawasulFinanceRevenueAccountID'] ?? '') === '' ? null : $_POST['tawasulFinanceRevenueAccountID'], 'defaultAmount' => ($_POST['defaultAmount'] ?? '') === '' ? null : $_POST['defaultAmount']];

try {
    $id = $pdo->insert('INSERT INTO tawasulFinanceFeeItem (name,category,tawasulFinanceRevenueAccountID,defaultAmount) VALUES (:name,:category,:tawasulFinanceRevenueAccountID,:defaultAmount)', $data);
    saLedger($container)->audit('FeeItem', $id, 'add', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
