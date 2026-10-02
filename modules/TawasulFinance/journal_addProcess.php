<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\{Ledger,Billing,Payables,Payroll,Depreciation,PeriodClose,LedgerException};

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/journal_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/journal_add.php')) { header("Location: {$URL}&return=error0"); exit; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$ledger = saLedger($container);
$cfg = saConfig($container);
try {

    $lines = [];
    foreach ($_POST['tawasulFinanceAccountID'] ?? [] as $i => $a) {
        if (!$a) continue;
        $lines[] = ['tawasulFinanceAccountID' => $a, 'tawasulFinanceCostCenterID' => $_POST['tawasulFinanceCostCenterID'][$i] ?? null, 'memo' => $_POST['memo'][$i] ?? null, 'debit' => $_POST['debit'][$i] ?? 0, 'credit' => $_POST['credit'][$i] ?? 0];
    }
    $id = $ledger->post($_POST['date'], trim($_POST['description']), $lines, 'JV', null, null, ($_POST['draft'] ?? '') == 'Y');
    if (($_POST['isRecurring'] ?? '') == 'Y') $pdo->update("UPDATE tawasulFinanceJournalEntry SET isRecurring='Y', recurringFrequency=:f WHERE tawasulFinanceJournalEntryID=:id", ['f' => $_POST['recurringFrequency'] ?: 'Monthly', 'id' => $id]);

} catch (LedgerException $e) {
    $session->set('saError', $e->getMessage()); header("Location: {$URL}&return=error1"); exit;
} catch (\Throwable $e) {
    $session->set('saError', $e->getMessage()); header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
