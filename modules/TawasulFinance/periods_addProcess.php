<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/periods_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/periods_add.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['tawasulFinanceFiscalYearID' => ($_POST['tawasulFinanceFiscalYearID'] ?? '') === '' ? null : $_POST['tawasulFinanceFiscalYearID'], 'name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'startDate' => ($_POST['startDate'] ?? '') === '' ? null : $_POST['startDate'], 'endDate' => ($_POST['endDate'] ?? '') === '' ? null : $_POST['endDate'], 'status' => ($_POST['status'] ?? '') === '' ? null : $_POST['status']];
if (!empty($data['startDate'])) $data['startDate'] = \TawasulOS\Services\Format::dateConvert($data['startDate']);
if (!empty($data['endDate'])) $data['endDate'] = \TawasulOS\Services\Format::dateConvert($data['endDate']);
try {
    $id = $pdo->insert('INSERT INTO tawasulFinancePeriod (tawasulFinanceFiscalYearID,name,startDate,endDate,status) VALUES (:tawasulFinanceFiscalYearID,:name,:startDate,:endDate,:status)', $data);
    saLedger($container)->audit('Period', $id, 'add', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
