<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\{Ledger,Billing,Payables,Payroll,Depreciation,PeriodClose,LedgerException};

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/reconciliation.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/reconciliation.php')) { header("Location: {$URL}&return=error0"); exit; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$ledger = saLedger($container);
$cfg = saConfig($container);
try {

    $id = $pdo->insert('INSERT INTO tawasulFinanceBankReconciliation (tawasulFinanceCashAccountID,statementDate,statementBalance,bookBalance,difference,notes,reconciledByID) VALUES (:c,:d,:s,:b,:x,:n,:u)',
        ['c' => $_POST['cash'], 'd' => $_POST['date'], 's' => $_POST['statement'], 'b' => $_POST['book'], 'x' => round($_POST['statement'] - $_POST['book'], 2), 'n' => $_POST['notes'] ?? '', 'u' => $session->get('tawasulPersonID')]);
    $ledger->audit('BankReconciliation', $id, 'add');

} catch (LedgerException $e) {
    $session->set('saError', $e->getMessage()); header("Location: {$URL}&return=error1"); exit;
} catch (\Throwable $e) {
    $session->set('saError', $e->getMessage()); header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
