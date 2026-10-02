<?php
/**
 * Prove the seeded chart is postable: build a clean schema from the manifest,
 * seed it, then post real double-entry transactions through the engine.
 */
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../modules/TawasulFinance/src/bootstrap.php';

use Tos\Module\TawasulFinance\Support\ConnectionAdapter;

$admin = new PDO('mysql:host=localhost', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$scratch = 'tawasul_coa_clean_test';
$admin->exec("DROP DATABASE IF EXISTS `$scratch`");
$admin->exec("CREATE DATABASE `$scratch`");
$admin->exec("USE `$scratch`");

$moduleTables = [];
require __DIR__ . '/../../modules/TawasulFinance/manifest.php';
foreach ($moduleTables as $sql) {
    $admin->exec($sql);
}
echo "clean schema built from manifest (" . count($moduleTables) . " statements)\n";

$conn = new ConnectionAdapter($admin);

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = '') {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? "  PASS  " : "  FAIL  ") . $label . ($detail ? "  -- {$detail}" : '') . "\n";
}

// --- Seed from empty ------------------------------------------------------
// The expected account count is read from the chart itself rather than written
// out here: the SchoolAccounting merge added accounts to it, and a hardcoded
// number only goes stale.
$chartCount = count((new ReflectionClass(\Tos\Module\TawasulFinance\Domain\ChartOfAccounts::class))
    ->getConstant('CHART'));

$seeder = new \Tos\Module\TawasulFinance\Domain\ChartOfAccounts($conn);
$result = $seeder->seed();

check("all {$chartCount} accounts inserted on empty DB", $result['accounts']['inserted'] === $chartCount, "inserted={$result['accounts']['inserted']}");
check('no accounts updated on empty DB', $result['accounts']['updated'] === 0, "updated={$result['accounts']['updated']}");
check('fiscal year created', $result['fiscalYear'] === true);
check('sequences seeded', $result['sequences'] === 10);
check('cost centres seeded', $result['costCentres'] === 6);
check('salary components seeded', $result['salaryComponents'] === 7);

// --- Re-seed must be a no-op ---------------------------------------------
$again = (new \Tos\Module\TawasulFinance\Domain\ChartOfAccounts($conn))->seed();
check('re-seed inserts nothing', $again['accounts']['inserted'] === 0, "inserted={$again['accounts']['inserted']}");
check('re-seed creates no second fiscal year', $again['fiscalYear'] === false);

$total = (int) $admin->query('SELECT COUNT(*) FROM tawasulFinanceAccount')->fetchColumn();
check('no duplicate accounts after re-seed', $total === $chartCount, "total={$total}, chart={$chartCount}");

// --- Structural integrity of the chart -----------------------------------
$orphans = (int) $admin->query(
    'SELECT COUNT(*) FROM tawasulFinanceAccount c
       LEFT JOIN tawasulFinanceAccount p ON p.tawasulFinanceAccountID = c.parentAccountID
      WHERE c.parentAccountID IS NOT NULL AND p.tawasulFinanceAccountID IS NULL'
)->fetchColumn();
check('no orphaned accounts', $orphans === 0, "orphans={$orphans}");

$headings = $admin->query("SELECT COUNT(*) FROM tawasulFinanceAccount WHERE isPosting='N'")->fetchColumn();
check('headings present', (int)$headings > 0, "headings={$headings}");

// Every posting account must have a code, and types must be from the allowed set.
$badType = (int) $admin->query(
    "SELECT COUNT(*) FROM tawasulFinanceAccount WHERE type NOT IN ('Asset','Liability','Equity','Revenue','Expense')"
)->fetchColumn();
check('all account types valid', $badType === 0, "bad={$badType}");

// --- Posting against the seeded chart ------------------------------------
$acc = [];
foreach ($admin->query('SELECT code, tawasulFinanceAccountID FROM tawasulFinanceAccount') as $r) {
    $acc[$r['code']] = $r['tawasulFinanceAccountID'];
}

$journal = new \Tos\Module\TawasulFinance\Domain\JournalGateway($conn);
$journal->setDependencies(
    new \Tos\Module\TawasulFinance\Domain\FiscalYearGateway($conn),
    new \Tos\Module\TawasulFinance\Domain\AccountGateway($conn)
);

$month = date('Y-m-05');

// A realistic school transaction: invoice a family for tuition.
$r = $journal->postEntry(
    ['date' => $month, 'description' => 'Tuition invoice INV-0001', 'documentType' => 'JV'],
    [
        ['tawasulFinanceAccountID' => $acc['1200'], 'debit' => 25000.00, 'credit' => 0, 'memo' => 'Receivable'],
        ['tawasulFinanceAccountID' => $acc['4100'], 'debit' => 0, 'credit' => 25000.00, 'memo' => 'Tuition'],
    ]
);
check('tuition invoice posts against seeded chart', $r['success'], implode('; ', $r['errors']));

if ($r['success']) {
    $row = $admin->query("SELECT documentNumber, status, tawasulFinancePeriodID FROM tawasulFinanceJournalEntry WHERE tawasulFinanceJournalEntryID = {$r['id']}")->fetch(PDO::FETCH_ASSOC);
    check('entry numbered from JV sequence', $row['documentNumber'] === 'JV-1', $row['documentNumber']);
    check('entry resolved to a period', !empty($row['tawasulFinancePeriodID']), "period={$row['tawasulFinancePeriodID']}");
    check('entry marked Posted', $row['status'] === 'Posted', $row['status']);
}

// Family pays it off.
$r2 = $journal->postEntry(
    ['date' => $month, 'description' => 'Payment received', 'documentType' => 'JV'],
    [
        ['tawasulFinanceAccountID' => $acc['1110'], 'debit' => 25000.00, 'credit' => 0],
        ['tawasulFinanceAccountID' => $acc['1200'], 'debit' => 0, 'credit' => 25000.00],
    ]
);
check('receipt posts', $r2['success'], implode('; ', $r2['errors']));

// A salary cost, to prove expense accounts work too.
$r3 = $journal->postEntry(
    ['date' => $month, 'description' => 'Monthly salaries', 'documentType' => 'JV'],
    [
        ['tawasulFinanceAccountID' => $acc['5100'], 'debit' => 40000.00, 'credit' => 0],
        ['tawasulFinanceAccountID' => $acc['1110'], 'debit' => 0, 'credit' => 40000.00],
    ]
);
check('salary posting works', $r3['success'], implode('; ', $r3['errors']));

// Net trial balance must be zero.
$tb = $admin->query(
    "SELECT SUM(l.debit) - SUM(l.credit) AS diff
       FROM tawasulFinanceJournalLine l
       JOIN tawasulFinanceJournalEntry j ON j.tawasulFinanceJournalEntryID = l.tawasulFinanceJournalEntryID
      WHERE j.status = 'Posted'"
)->fetch(PDO::FETCH_ASSOC);
check('trial balance nets to zero', abs((float) $tb['diff']) < 0.005, "diff={$tb['diff']}");

// A seeded heading must be rejected.
$r4 = $journal->postEntry(
    ['date' => $month, 'description' => 'Post to heading'],
    [
        ['tawasulFinanceAccountID' => $acc['1000'], 'debit' => 10, 'credit' => 0],
        ['tawasulFinanceAccountID' => $acc['4100'], 'debit' => 0, 'credit' => 10],
    ]
);
check('seeded heading rejected', !$r4['success'] && str_contains(implode(' ', $r4['errors']), 'heading'), implode('; ', $r4['errors']));

echo "\n{$pass} passed, {$fail} failed\n";
$admin->exec("DROP DATABASE `$scratch`");
echo "scratch dropped.\n";
exit($fail > 0 ? 1 : 0);