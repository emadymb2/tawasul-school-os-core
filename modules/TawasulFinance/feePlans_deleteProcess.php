<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/feePlans_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feePlans_delete.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$id = $_GET['id'] ?? '';
$pdo->delete('DELETE FROM tawasulFinanceFeePlan WHERE tawasulFinanceFeePlanID=:id', ['id' => $id]);
saLedger($container)->audit('FeePlan', $id, 'delete');
header("Location: {$URL}&return=success0");
