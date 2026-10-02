<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/feePlans_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feePlans_add.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'tawasulSchoolYearID' => ($_POST['tawasulSchoolYearID'] ?? '') === '' ? null : $_POST['tawasulSchoolYearID'], 'tawasulYearGroupID' => ($_POST['tawasulYearGroupID'] ?? '') === '' ? null : $_POST['tawasulYearGroupID'], 'installments' => ($_POST['installments'] ?? '') === '' ? null : $_POST['installments'], 'tawasulFinanceCostCenterID' => ($_POST['tawasulFinanceCostCenterID'] ?? '') === '' ? null : $_POST['tawasulFinanceCostCenterID']];

try {
    $id = $pdo->insert('INSERT INTO tawasulFinanceFeePlan (name,tawasulSchoolYearID,tawasulYearGroupID,installments,tawasulFinanceCostCenterID) VALUES (:name,:tawasulSchoolYearID,:tawasulYearGroupID,:installments,:tawasulFinanceCostCenterID)', $data);
    saLedger($container)->audit('FeePlan', $id, 'add', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
