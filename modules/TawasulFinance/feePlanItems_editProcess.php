<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/feePlanItems_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feePlanItems_edit.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['tawasulFinanceFeePlanID' => ($_POST['tawasulFinanceFeePlanID'] ?? '') === '' ? null : $_POST['tawasulFinanceFeePlanID'], 'tawasulFinanceFeeItemID' => ($_POST['tawasulFinanceFeeItemID'] ?? '') === '' ? null : $_POST['tawasulFinanceFeeItemID'], 'amount' => ($_POST['amount'] ?? '') === '' ? null : $_POST['amount']];

try {
    $id = $_GET['id'] ?? '';
$pdo->update('UPDATE tawasulFinanceFeePlanItem SET tawasulFinanceFeePlanID=:tawasulFinanceFeePlanID, tawasulFinanceFeeItemID=:tawasulFinanceFeeItemID, amount=:amount WHERE tawasulFinanceFeePlanItemID=:id', $data + ['id' => $id]);
    saLedger($container)->audit('FeePlanItem', $id, 'edit', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
