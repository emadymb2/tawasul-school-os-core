<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/salaryComponents_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/salaryComponents_delete.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$id = $_GET['id'] ?? '';
$pdo->delete('DELETE FROM tawasulFinanceSalaryComponent WHERE tawasulFinanceSalaryComponentID=:id', ['id' => $id]);
saLedger($container)->audit('SalaryComponent', $id, 'delete');
header("Location: {$URL}&return=success0");
