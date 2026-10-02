<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\{Ledger,Billing,Payables,Payroll,Depreciation,PeriodClose,LedgerException};

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/invoices_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/invoices_generate.php')) { header("Location: {$URL}&return=error0"); exit; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$ledger = saLedger($container);
$cfg = saConfig($container);
try {
    // Billing::generateForSchedule() reads the issue and due dates off the
    // schedule itself; a blank field here means "use the schedule's dates".
    $raised = (new Billing($ledger, $cfg))->generateForSchedule(
        $_POST['tawasulFinanceBillingScheduleID'] ?? null,
        $_POST['invoiceIssueDate'] ?: null,
        null
    );
    $session->set('saError', 'تم توليد '.$raised.' فاتورة');

} catch (LedgerException $e) {
    $session->set('saError', $e->getMessage()); header("Location: {$URL}&return=error1"); exit;
} catch (\Throwable $e) {
    $session->set('saError', $e->getMessage()); header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
