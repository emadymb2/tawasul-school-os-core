<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\{Ledger,Billing,Payables,Payroll,Depreciation,PeriodClose,LedgerException};

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/payroll_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/payroll_manage.php')) { header("Location: {$URL}&return=error0"); exit; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$ledger = saLedger($container);
$cfg = saConfig($container);
try {
    (new Payroll($ledger, $cfg))->run($_POST['month'], $_POST['tawasulFinanceCashAccountID'] ?: null);

} catch (LedgerException $e) {
    $session->set('saError', $e->getMessage()); header("Location: {$URL}&return=error1"); exit;
} catch (\Throwable $e) {
    $session->set('saError', $e->getMessage()); header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
