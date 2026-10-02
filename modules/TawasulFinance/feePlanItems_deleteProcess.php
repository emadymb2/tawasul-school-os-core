<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/feePlanItems_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feePlanItems_delete.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$id = $_GET['id'] ?? '';
$pdo->delete('DELETE FROM tawasulFinanceFeePlanItem WHERE tawasulFinanceFeePlanItemID=:id', ['id' => $id]);
saLedger($container)->audit('FeePlanItem', $id, 'delete');
header("Location: {$URL}&return=success0");
