<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/fiscalYears_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/fiscalYears_edit.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'firstDay' => ($_POST['firstDay'] ?? '') === '' ? null : $_POST['firstDay'], 'lastDay' => ($_POST['lastDay'] ?? '') === '' ? null : $_POST['lastDay'], 'status' => ($_POST['status'] ?? '') === '' ? null : $_POST['status']];
if (!empty($data['firstDay'])) $data['firstDay'] = \TawasulOS\Services\Format::dateConvert($data['firstDay']);
if (!empty($data['lastDay'])) $data['lastDay'] = \TawasulOS\Services\Format::dateConvert($data['lastDay']);
try {
    $id = $_GET['id'] ?? '';
$pdo->update('UPDATE tawasulFinanceFiscalYear SET name=:name, firstDay=:firstDay, lastDay=:lastDay, status=:status WHERE tawasulFinanceFiscalYearID=:id', $data + ['id' => $id]);
    saLedger($container)->audit('FiscalYear', $id, 'edit', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
