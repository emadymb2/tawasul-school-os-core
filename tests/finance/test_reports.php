<?php
/**
 * Verify the financial reports against known-good postings.
 *
 * The postings below are chosen so the expected trial balance can be computed by
 * hand, which is the only way to catch a sign or grouping error in the report.
 */
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../modules/TawasulFinance/src/bootstrap.php';

use Tos\Module\TawasulFinance\Support\ConnectionAdapter;

$admin = new PDO('mysql:host=localhost', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$scratch = 'tawasul_report_test';
$admin->exec("DROP DATABASE IF EXISTS `$scratch`");
$admin->exec("CREATE DATABASE `$scratch`");
$admin->exec("USE `$scratch`");

$moduleTables = [];
require __DIR__ . '/../../modules/TawasulFinance/manifest.php';
foreach ($moduleTables as $sql) { $admin->exec($sql); }

$conn = new ConnectionAdapter($admin);
(new \Tos\Module\TawasulFinance\Domain\ChartOfAccounts($conn))->seed();

$journal = new \Tos\Module\TawasulFinance\Domain\JournalGateway($conn);
$journal->setDependencies(
    new \Tos\Module\TawasulFinance\Domain\FiscalYearGateway($conn),
    new \Tos\Module\TawasulFinance\Domain\AccountGateway($conn)
);
$reports = new \Tos\Module\TawasulFinance\Domain\LedgerReportGateway($conn);

$acc = [];
foreach ($admin->query('SELECT code, tawasulFinanceAccountID FROM tawasulFinanceAccount') as $r) $acc[$r['code']] = $r['tawasulFinanceAccountID'];

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = '') {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? "  PASS  " : "  FAIL  ") . $label . ($detail ? "  -- {$detail}" : '') . "\n";
}
function near(float $a, float $b) : bool { return abs($a - $b) < 0.005; }
function allTrue(array $pairs) : bool { foreach ($pairs as $p) if (!$p) return false; return true; }

$year = date('Y');

// --- Expected ledger ------------------------------------------------------
// 1. Dr AR 25,000 / Cr Tuition 25,000
// 2. Dr Bank 25,000 / Cr AR 25,000
// 3. Dr Salaries 40,000 / Cr Bank 40,000
// 4. Dr Bank 2,000 / Cr AR 2,000  (partial payment on a new invoice, Dr AR first)
$plan = [
    [['1200', 25000, 0], ['4100', 0, 25000]],
    [['1110', 25000, 0], ['1200', 0, 25000]],
    [['5100', 40000, 0], ['1110', 0, 40000]],
];
foreach ($plan as $i => $lines) {
    $r = $journal->postEntry(
        ['date' => "{$year}-01-10", 'description' => "Test {$i}", 'documentType' => 'JV'],
        array_map(fn($l) => ['tawasulFinanceAccountID' => $acc[$l[0]], 'debit' => $l[1], 'credit' => $l[2]], $lines)
    );
    if (!$r['success']) { echo "setup failed: ".implode('; ', $r['errors'])."\n"; exit(1); }
}

// --- Empty trial balance on a clean period --------------------------------
check('empty ledger balances', $reports->ledgerIsBalanced());

// --- Trial balance totals -------------------------------------------------
$tb = $reports->trialBalance();
check('trial balance is balanced', $tb['isBalanced'],
      "debits={$tb['debitTotal']} credits={$tb['creditTotal']}");

// Expected: Dr Bank 25,000 (from 25,000 in - 40,000 out = -15,000 => 15,000 credit)
//            Dr Salaries 40,000, Cr Tuition 25,000, Cr AR 25,000
$bal = [];
foreach ($tb['rows'] as $r) {
    if ($r['balanceType'] !== '') $bal[$r['code']] = [$r['balanceType'], (float) $r['balance']];
}
echo "        balances: ";
foreach ($bal as $code => [$side, $amt]) echo "{$code}={$side} " . number_format($amt, 2) . "  ";
echo "\n";

// Assets are debit-normal: bank shows as a credit balance of 15,000.
// Bank is an Asset, so it is debit-normal. Having paid out more than it
// received leaves a credit balance, reported as a negative amount.
check('Bank shows credit 15,000 when overdrawn', ($bal['1110'][0] ?? '') === 'Credit' && near($bal['1110'][1], -15000.0),
      ($bal['1110'][0] ?? 'n/a') . ' ' . ($bal['1110'][1] ?? 0));
check('AR fully collected', !isset($bal['1200']), 'AR should net to zero and be absent');
check('Tuition revenue is credit 25,000', ($bal['4100'][0] ?? '') === 'Credit' && near($bal['4100'][1], 25000.0),
      ($bal['4100'][1] ?? 0));
check('Salaries expense is debit 40,000', ($bal['5100'][0] ?? '') === 'Debit' && near($bal['5100'][1], 40000.0),
      ($bal['5100'][1] ?? 0));

// --- Column-wise trial balance adds up ------------------------------------
check('debit column totals match', near($tb['debitTotal'], array_sum(array_map(fn($r) => $r['debitBalance'], $tb['rows']))));
check('credit column totals match', near($tb['creditTotal'], array_sum(array_map(fn($r) => $r['creditBalance'], $tb['rows']))));

// --- Heading accounts excluded -------------------------------------------
$codes = array_column($tb['rows'], 'code');
check('headings excluded from trial balance', !in_array('1000', $codes, true) && !in_array('4000', $codes, true));
check('posting accounts included', in_array('1110', $codes, true) && in_array('4100', $codes, true));

// --- Type filter ----------------------------------------------------------
$tbAsset = $reports->trialBalance(null, null, ['Asset']);
$types = array_unique(array_column($tbAsset['rows'], 'type'));
check('type filter restricts to Asset', $types === ['Asset'], implode(',', $types));

// --- General ledger running balance ---------------------------------------
$gl = $reports->generalLedger(null, null, $acc['1200']);
check('AR ledger has 2 rows', count($gl['rows']) === 2, 'rows=' . count($gl['rows']));
check('AR running balance closes at zero', near($gl['closing'], 0.0), 'closing=' . $gl['closing']);
check('AR first line shows debit 25,000', near((float) $gl['rows'][0]['debit'], 25000.0));
check('AR second line shows credit 25,000', near((float) $gl['rows'][1]['credit'], 25000.0));

$glBank = $reports->generalLedger(null, null, $acc['1110']);
check('Bank ledger closes at credit 15,000', near($glBank['closing'], -15000.0), 'closing=' . $glBank['closing']);
check('Bank ledger is debit-normal (Asset)', $glBank['isDebitNormal'] === true);

// --- As-at date filtering -------------------------------------------------
$before = $reports->trialBalance("{$year}-01-09");
check('as-at before postings is empty', count($before['rows']) === 0 || near($before['debitTotal'], 0.0),
      'rows=' . count($before['rows']));

$after = $reports->trialBalance("{$year}-01-11");
check('as-at after postings includes activity', $after['debitTotal'] > 0, 'debits=' . $after['debitTotal']);

// --- Drafts must not appear ----------------------------------------------
$journal->saveDraft(['date' => "{$year}-01-11", 'description' => 'Unposted draft', 'documentType' => 'JV'],
    [['tawasulFinanceAccountID' => $acc['5100'], 'debit' => 999999, 'credit' => 0]]);
$tbAfterDraft = $reports->trialBalance();
check('draft excluded from trial balance', near($tbAfterDraft['debitTotal'], $tb['debitTotal']),
      "before={$tb['debitTotal']} after={$tbAfterDraft['debitTotal']}");
check('draft excluded from general ledger', count($reports->generalLedger()['rows']) === 6, 'rows=' . count($reports->generalLedger()['rows']));

// --- Reversal restores the original balance -------------------------------
$first = (int) $admin->query("SELECT tawasulFinanceJournalEntryID FROM tawasulFinanceJournalEntry WHERE documentNumber = 'JV-1'")->fetchColumn();
$journal->reverseEntry($first, "{$year}-01-12", null);
$tbRev = $reports->trialBalance();
check('ledger still balances after reversal', $tbRev['isBalanced'],
      "debits={$tbRev['debitTotal']} credits={$tbRev['creditTotal']}");
// Dr AR 25,000 / Cr Tuition 25,000 then reversed, so AR and Tuition net to zero.
$codesRev = [];
foreach ($tbRev['rows'] as $r) if ($r['balanceType'] !== '') $codesRev[$r['code']] = (float) $r['balance'];
// Reversing JV-1 (Dr AR 25,000 / Cr Tuition 25,000) writes Dr Tuition / Cr AR.
// AR was already nil after JV-2, so it now carries a 25,000 credit balance,
// while tuition nets back to zero. That is the intended accounting result:
// undoing the original invoice cancels the receivable it created.
check('reversal leaves AR in credit 25,000', near($codesRev['1200'] ?? 0.0, -25000.0), 'AR=' . ($codesRev['1200'] ?? 'absent'));
check('reversal zeroes tuition revenue', !isset($codesRev['4100']), 'rev=' . ($codesRev['4100'] ?? 'absent'));
$expected = ['1110' => -15000.0, '1200' => -25000.0, '5100' => 40000.0];
$sameKeys = array_keys($codesRev) === array_keys($expected);
$sameVals = $sameKeys && allTrue(array_map('near', array_values($codesRev), array_values($expected)));
check('reversal leaves exactly bank, AR and salaries', $sameVals,
      implode(',', array_keys($codesRev)) . ' => ' . implode(',', array_values($codesRev)));

// --- Type summary ---------------------------------------------------------
$summary = $reports->accountTypeSummary();
check('type summary produced', count($summary) >= 3, 'types=' . count($summary));
$sumDebit = array_sum(array_column($summary, 'debit'));
check('type summary debits match trial balance', near($sumDebit, $tbRev['debitTotal']));

echo "\n{$pass} passed, {$fail} failed\n";
$admin->exec("DROP DATABASE `$scratch`");
echo "scratch dropped.\n";
exit($fail > 0 ? 1 : 0);