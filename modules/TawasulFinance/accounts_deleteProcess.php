<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/accounts_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/accounts_delete.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$id = $_GET['id'] ?? '';
if ($pdo->selectOne('SELECT COUNT(*) FROM tawasulFinanceJournalLine WHERE tawasulFinanceAccountID=:id', ['id' => $id]) || $pdo->selectOne('SELECT COUNT(*) FROM tawasulFinanceAccount WHERE parentAccountID=:id', ['id' => $id])) { header("Location: {$URL}&return=error3"); exit; }
$pdo->delete('DELETE FROM tawasulFinanceAccount WHERE tawasulFinanceAccountID=:id', ['id' => $id]);
saLedger($container)->audit('Account', $id, 'delete');
header("Location: {$URL}&return=success0");
