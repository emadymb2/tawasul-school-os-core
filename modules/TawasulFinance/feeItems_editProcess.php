<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/feeItems_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feeItems_edit.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'category' => ($_POST['category'] ?? '') === '' ? null : $_POST['category'], 'tawasulFinanceRevenueAccountID' => ($_POST['tawasulFinanceRevenueAccountID'] ?? '') === '' ? null : $_POST['tawasulFinanceRevenueAccountID'], 'defaultAmount' => ($_POST['defaultAmount'] ?? '') === '' ? null : $_POST['defaultAmount']];

try {
    $id = $_GET['id'] ?? '';
$pdo->update('UPDATE tawasulFinanceFeeItem SET name=:name, category=:category, tawasulFinanceRevenueAccountID=:tawasulFinanceRevenueAccountID, defaultAmount=:defaultAmount WHERE tawasulFinanceFeeItemID=:id', $data + ['id' => $id]);
    saLedger($container)->audit('FeeItem', $id, 'edit', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
