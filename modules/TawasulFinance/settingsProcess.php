<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\{Ledger,Billing,Payables,Payroll,Depreciation,PeriodClose,LedgerException};

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/settings.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/settings.php')) { header("Location: {$URL}&return=error0"); exit; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$ledger = saLedger($container);
$cfg = saConfig($container);
try {

    foreach ($pdo->select("SELECT name FROM tawasulSetting WHERE scope='School Accounting'")->fetchAll(PDO::FETCH_COLUMN) as $n) {
        if (!isset($_POST[$n])) continue;
        $pdo->update("UPDATE tawasulSetting SET value=:v WHERE scope='School Accounting' AND name=:n", ['v' => $_POST[$n], 'n' => $n]);
    }
    $ledger->audit('Setting', 0, 'update', $_POST);

} catch (LedgerException $e) {
    $session->set('saError', $e->getMessage()); header("Location: {$URL}&return=error1"); exit;
} catch (\Throwable $e) {
    $session->set('saError', $e->getMessage()); header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
