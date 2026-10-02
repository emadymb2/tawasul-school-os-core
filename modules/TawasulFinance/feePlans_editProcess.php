<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/feePlans_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feePlans_edit.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'tawasulSchoolYearID' => ($_POST['tawasulSchoolYearID'] ?? '') === '' ? null : $_POST['tawasulSchoolYearID'], 'tawasulYearGroupID' => ($_POST['tawasulYearGroupID'] ?? '') === '' ? null : $_POST['tawasulYearGroupID'], 'installments' => ($_POST['installments'] ?? '') === '' ? null : $_POST['installments'], 'tawasulFinanceCostCenterID' => ($_POST['tawasulFinanceCostCenterID'] ?? '') === '' ? null : $_POST['tawasulFinanceCostCenterID']];

try {
    $id = $_GET['id'] ?? '';
$pdo->update('UPDATE tawasulFinanceFeePlan SET name=:name, tawasulSchoolYearID=:tawasulSchoolYearID, tawasulYearGroupID=:tawasulYearGroupID, installments=:installments, tawasulFinanceCostCenterID=:tawasulFinanceCostCenterID WHERE tawasulFinanceFeePlanID=:id', $data + ['id' => $id]);
    saLedger($container)->audit('FeePlan', $id, 'edit', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
