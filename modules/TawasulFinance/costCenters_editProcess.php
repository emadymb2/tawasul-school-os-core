<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/costCenters_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/costCenters_edit.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['code' => ($_POST['code'] ?? '') === '' ? null : $_POST['code'], 'name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'type' => ($_POST['type'] ?? '') === '' ? null : $_POST['type']];

try {
    $id = $_GET['id'] ?? '';
$pdo->update('UPDATE tawasulFinanceCostCenter SET code=:code, name=:name, type=:type WHERE tawasulFinanceCostCenterID=:id', $data + ['id' => $id]);
    saLedger($container)->audit('CostCenter', $id, 'edit', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
