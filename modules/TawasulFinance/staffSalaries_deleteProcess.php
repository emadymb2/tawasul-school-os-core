<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/staffSalaries_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/staffSalaries_delete.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$id = $_GET['id'] ?? '';
$pdo->delete('DELETE FROM tawasulFinanceStaffSalary WHERE tawasulFinanceStaffSalaryID=:id', ['id' => $id]);
saLedger($container)->audit('StaffSalary', $id, 'delete');
header("Location: {$URL}&return=success0");
