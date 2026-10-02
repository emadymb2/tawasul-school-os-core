<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/feePlanItems_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feePlanItems_add.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['tawasulFinanceFeePlanID' => ($_POST['tawasulFinanceFeePlanID'] ?? '') === '' ? null : $_POST['tawasulFinanceFeePlanID'], 'tawasulFinanceFeeItemID' => ($_POST['tawasulFinanceFeeItemID'] ?? '') === '' ? null : $_POST['tawasulFinanceFeeItemID'], 'amount' => ($_POST['amount'] ?? '') === '' ? null : $_POST['amount']];

try {
    $id = $pdo->insert('INSERT INTO tawasulFinanceFeePlanItem (tawasulFinanceFeePlanID,tawasulFinanceFeeItemID,amount) VALUES (:tawasulFinanceFeePlanID,:tawasulFinanceFeeItemID,:amount)', $data);
    saLedger($container)->audit('FeePlanItem', $id, 'add', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
