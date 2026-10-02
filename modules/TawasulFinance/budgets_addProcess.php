<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/budgets_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/budgets_add.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['tawasulFinanceFiscalYearID' => ($_POST['tawasulFinanceFiscalYearID'] ?? '') === '' ? null : $_POST['tawasulFinanceFiscalYearID'], 'tawasulFinanceAccountID' => ($_POST['tawasulFinanceAccountID'] ?? '') === '' ? null : $_POST['tawasulFinanceAccountID'], 'tawasulFinanceCostCenterID' => ($_POST['tawasulFinanceCostCenterID'] ?? '') === '' ? null : $_POST['tawasulFinanceCostCenterID'], 'amount' => ($_POST['amount'] ?? '') === '' ? null : $_POST['amount']];

try {
    $id = $pdo->insert('INSERT INTO tawasulFinanceBudget (tawasulFinanceFiscalYearID,tawasulFinanceAccountID,tawasulFinanceCostCenterID,amount) VALUES (:tawasulFinanceFiscalYearID,:tawasulFinanceAccountID,:tawasulFinanceCostCenterID,:amount)', $data);
    saLedger($container)->audit('Budget', $id, 'add', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
