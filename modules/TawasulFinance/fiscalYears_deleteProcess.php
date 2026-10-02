<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/fiscalYears_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/fiscalYears_delete.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$id = $_GET['id'] ?? '';
if ($pdo->selectOne('SELECT COUNT(*) FROM tawasulFinancePeriod WHERE tawasulFinanceFiscalYearID=:id', ['id' => $id])) { header("Location: {$URL}&return=error3"); exit; }
$pdo->delete('DELETE FROM tawasulFinanceFiscalYear WHERE tawasulFinanceFiscalYearID=:id', ['id' => $id]);
saLedger($container)->audit('FiscalYear', $id, 'delete');
header("Location: {$URL}&return=success0");
