<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/budgets_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/budgets_edit.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['tawasulFinanceFiscalYearID' => ($_POST['tawasulFinanceFiscalYearID'] ?? '') === '' ? null : $_POST['tawasulFinanceFiscalYearID'], 'tawasulFinanceAccountID' => ($_POST['tawasulFinanceAccountID'] ?? '') === '' ? null : $_POST['tawasulFinanceAccountID'], 'tawasulFinanceCostCenterID' => ($_POST['tawasulFinanceCostCenterID'] ?? '') === '' ? null : $_POST['tawasulFinanceCostCenterID'], 'amount' => ($_POST['amount'] ?? '') === '' ? null : $_POST['amount']];

try {
    $id = $_GET['id'] ?? '';
$pdo->update('UPDATE tawasulFinanceBudget SET tawasulFinanceFiscalYearID=:tawasulFinanceFiscalYearID, tawasulFinanceAccountID=:tawasulFinanceAccountID, tawasulFinanceCostCenterID=:tawasulFinanceCostCenterID, amount=:amount WHERE tawasulFinanceBudgetID=:id', $data + ['id' => $id]);
    saLedger($container)->audit('Budget', $id, 'edit', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
