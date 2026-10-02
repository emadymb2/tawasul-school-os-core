<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/budgets_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/budgets_delete.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$id = $_GET['id'] ?? '';
$pdo->delete('DELETE FROM tawasulFinanceBudget WHERE tawasulFinanceBudgetID=:id', ['id' => $id]);
saLedger($container)->audit('Budget', $id, 'delete');
header("Location: {$URL}&return=success0");
