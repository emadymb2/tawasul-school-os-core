<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/feeItems_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feeItems_delete.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$id = $_GET['id'] ?? '';
$pdo->delete('DELETE FROM tawasulFinanceFeeItem WHERE tawasulFinanceFeeItemID=:id', ['id' => $id]);
saLedger($container)->audit('FeeItem', $id, 'delete');
header("Location: {$URL}&return=success0");
