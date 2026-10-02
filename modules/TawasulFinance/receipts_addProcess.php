<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\{Ledger,Billing,Payables,Payroll,Depreciation,PeriodClose,LedgerException};

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/receipts_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/receipts_add.php')) { header("Location: {$URL}&return=error0"); exit; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$ledger = saLedger($container);
$cfg = saConfig($container);
try {

    $rid = (new Billing($ledger, $cfg))->receive($_POST['invoiceID'], $_POST['tawasulFinanceCashAccountID'], Format::dateConvert($_POST['date']), (float)$_POST['amount'], $_POST['method'], $_POST['reference'] ?? null, $session->get('tawasulPersonID'));
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/receipts_print.php&id='.$rid;

} catch (LedgerException $e) {
    $session->set('saError', $e->getMessage()); header("Location: {$URL}&return=error1"); exit;
} catch (\Throwable $e) {
    $session->set('saError', $e->getMessage()); header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
