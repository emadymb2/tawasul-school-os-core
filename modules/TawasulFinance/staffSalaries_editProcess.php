<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/staffSalaries_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/staffSalaries_edit.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['tawasulPersonID' => ($_POST['tawasulPersonID'] ?? '') === '' ? null : $_POST['tawasulPersonID'], 'tawasulFinanceSalaryComponentID' => ($_POST['tawasulFinanceSalaryComponentID'] ?? '') === '' ? null : $_POST['tawasulFinanceSalaryComponentID'], 'amount' => ($_POST['amount'] ?? '') === '' ? null : $_POST['amount'], 'tawasulFinanceCostCenterID' => ($_POST['tawasulFinanceCostCenterID'] ?? '') === '' ? null : $_POST['tawasulFinanceCostCenterID']];

try {
    $id = $_GET['id'] ?? '';
$pdo->update('UPDATE tawasulFinanceStaffSalary SET tawasulPersonID=:tawasulPersonID, tawasulFinanceSalaryComponentID=:tawasulFinanceSalaryComponentID, amount=:amount, tawasulFinanceCostCenterID=:tawasulFinanceCostCenterID WHERE tawasulFinanceStaffSalaryID=:id', $data + ['id' => $id]);
    saLedger($container)->audit('StaffSalary', $id, 'edit', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
