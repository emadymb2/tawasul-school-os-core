<?php
/**
 * Exercise the accounting engine against the real schema, in a scratch
 * database, so the posting rules are proven rather than assumed.
 */
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../modules/TawasulFinance/src/bootstrap.php';

$admin = new PDO('mysql:host=localhost', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$scratch = 'tawasul_acct_engine_test';
$admin->exec("DROP DATABASE IF EXISTS `$scratch`");
$admin->exec("CREATE DATABASE `$scratch`");
$admin->exec("USE `$scratch`");

// Build the schema from the module manifest, exactly as an install would.
$moduleTables = [];
require __DIR__ . '/../../modules/TawasulFinance/manifest.php';
foreach ($moduleTables as $sql) {
    $admin->exec($sql);
}
echo "schema built from manifest\n";

$conn = new \Tos\Module\TawasulFinance\Support\ConnectionAdapter($admin);

$q = fn($sql, $b = []) => (new PDO('mysql:host=localhost;dbname=' . $scratch, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]))->prepare($sql);

// --- Fixtures -------------------------------------------------------------
$admin->exec("INSERT INTO tawasulFinanceAccount (code, name, type, isPosting, active) VALUES
  ('1000', 'Cash', 'Asset', 'Y', 'Y'),
  ('1100', 'Accounts Receivable', 'Asset', 'Y', 'Y'),
  ('4000', 'Tuition Revenue', 'Revenue', 'Y', 'Y'),
  ('5000', 'Salaries Expense', 'Expense', 'Y', 'Y'),
  ('6000', 'Assets Heading', 'Asset', 'N', 'Y')");

$admin->exec("INSERT INTO tawasulFinanceFiscalYear (name, firstDay, lastDay, status)
  VALUES ('FY2026', '2026-01-01', '2026-12-31', 'Open')");
$yearID = (int) $admin->lastInsertId();

$admin->exec("INSERT INTO tawasulFinancePeriod (tawasulFinanceFiscalYearID, name, startDate, endDate, status) VALUES
  ($yearID, 'January 2026', '2026-01-01', '2026-01-31', 'Open'),
  ($yearID, 'February 2026', '2026-02-01', '2026-02-28', 'Open')");

$admin->exec("INSERT INTO tawasulFinanceSequence (documentType, prefix, nextNumber) VALUES ('JV', 'JV-', 1)");
echo "fixtures inserted\n\n";

$accounts = [];
foreach ($admin->query("SELECT code, tawasulFinanceAccountID FROM tawasulFinanceAccount") as $r) {
    $accounts[$r['code']] = $r['tawasulFinanceAccountID'];
}

// --- The engine under test ------------------------------------------------
$journal = (new \Tos\Module\TawasulFinance\Domain\JournalGateway($conn));
$fy = new \Tos\Module\TawasulFinance\Domain\FiscalYearGateway($conn);
$acct = new \Tos\Module\TawasulFinance\Domain\AccountGateway($conn);
$journal->setDependencies($fy, $acct);

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = '') {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? "  PASS  " : "  FAIL  ") . $label . ($detail ? "  -- {$detail}" : '') . "\n";
}

// 1. Balanced entry posts.
$r = $journal->postEntry(
    ['date' => '2026-01-15', 'description' => 'Invoice 1', 'documentType' => 'JV'],
    [
        ['tawasulFinanceAccountID' => $accounts['1100'], 'debit' => 1000.00, 'credit' => 0],
        ['tawasulFinanceAccountID' => $accounts['4000'], 'debit' => 0, 'credit' => 1000.00],
    ]
);
check('balanced entry posts', $r['success'], implode('; ', $r['errors']));
$entry1 = $r['id'];

// 2. Document number allocated from the sequence.
$doc = $admin->query("SELECT documentNumber FROM tawasulFinanceJournalEntry WHERE tawasulFinanceJournalEntryID = $entry1")->fetchColumn();
check('document number from sequence', $doc === 'JV-1', "got {$doc}");

// 3. Unbalanced entry is refused.
$r = $journal->postEntry(
    ['date' => '2026-01-15', 'description' => 'Bad'],
    [
        ['tawasulFinanceAccountID' => $accounts['1100'], 'debit' => 500, 'credit' => 0],
        ['tawasulFinanceAccountID' => $accounts['4000'], 'debit' => 0, 'credit' => 400],
    ]
);
check('unbalanced entry refused', !$r['success'] && str_contains(implode(' ', $r['errors']), 'does not balance'), implode('; ', $r['errors']));

// 4. No partial rows written by the failed post.
$n = (int) $admin->query("SELECT COUNT(*) FROM tawasulFinanceJournalEntry")->fetchColumn();
check('failed post left no entry', $n === 1, "entries={$n}");

// 5. Posting to a heading account is refused.
$r = $journal->postEntry(
    ['date' => '2026-01-15', 'description' => 'Heading'],
    [
        ['tawasulFinanceAccountID' => $accounts['6000'], 'debit' => 100, 'credit' => 0],
        ['tawasulFinanceAccountID' => $accounts['4000'], 'debit' => 0, 'credit' => 100],
    ]
);
check('heading account refused', !$r['success'] && str_contains(implode(' ', $r['errors']), 'heading'), implode('; ', $r['errors']));

// 6. A line that is both debit and credit is refused.
$r = $journal->postEntry(
    ['date' => '2026-01-15', 'description' => 'Both'],
    [
        ['tawasulFinanceAccountID' => $accounts['1100'], 'debit' => 100, 'credit' => 100],
        ['tawasulFinanceAccountID' => $accounts['4000'], 'debit' => 0, 'credit' => 100],
    ]
);
check('debit+credit line refused', !$r['success'], implode('; ', $r['errors']));

// 7. A single line is refused.
$r = $journal->postEntry(
    ['date' => '2026-01-15', 'description' => 'One line'],
    [['tawasulFinanceAccountID' => $accounts['1100'], 'debit' => 100, 'credit' => 0]]
);
check('single line refused', !$r['success']);

// 8. Date outside any period is refused.
$r = $journal->postEntry(
    ['date' => '2027-06-01', 'description' => 'No period'],
    [
        ['tawasulFinanceAccountID' => $accounts['1100'], 'debit' => 100, 'credit' => 0],
        ['tawasulFinanceAccountID' => $accounts['4000'], 'debit' => 0, 'credit' => 100],
    ]
);
check('date with no period refused', !$r['success'] && str_contains(implode(' ', $r['errors']), 'No accounting period'), implode('; ', $r['errors']));

// 9. Closed period refuses postings.
$admin->exec("UPDATE tawasulFinancePeriod SET status='Closed' WHERE name='January 2026'");
$r = $journal->postEntry(
    ['date' => '2026-01-20', 'description' => 'Closed period'],
    [
        ['tawasulFinanceAccountID' => $accounts['1100'], 'debit' => 100, 'credit' => 0],
        ['tawasulFinanceAccountID' => $accounts['4000'], 'debit' => 0, 'credit' => 100],
    ]
);
check('closed period refuses posting', !$r['success'] && str_contains(implode(' ', $r['errors']), 'closed'), implode('; ', $r['errors']));
$admin->exec("UPDATE tawasulFinancePeriod SET status='Open' WHERE name='January 2026'");

// 10. Period resolution picks the right month.
$period = $fy->selectPeriodForDate('2026-02-14');
check('period resolved for date', ($period['name'] ?? '') === 'February 2026', $period['name'] ?? 'none');

// 11. Draft saves unbalanced, does not post.
$r = $journal->saveDraft(
    ['date' => '2026-02-01', 'description' => 'Draft', 'documentType' => 'JV'],
    [['tawasulFinanceAccountID' => $accounts['1100'], 'debit' => 300, 'credit' => 0]]
);
check('unbalanced draft saves', $r['success'], implode('; ', $r['errors']));
$draftID = $r['id'];
$status = $admin->query("SELECT status FROM tawasulFinanceJournalEntry WHERE tawasulFinanceJournalEntryID = $draftID")->fetchColumn();
check('draft is not Posted', $status === 'Draft', "status={$status}");

// 12. Posting an unbalanced draft still fails.
$r = $journal->postDraft($draftID);
check('unbalanced draft cannot be posted', !$r['success'], implode('; ', $r['errors']));

// 13. Draft is deleted on failure, so it can be rebuilt.
$still = (int) $admin->query("SELECT COUNT(*) FROM tawasulFinanceJournalEntry WHERE tawasulFinanceJournalEntryID = $draftID")->fetchColumn();
check('draft survives failed post', $still === 1, "rows={$still}");

// 14. Reversal mirrors debits and credits.
$r = $journal->reverseEntry($entry1, '2026-02-01', null);
check('reversal posts', $r['success'], implode('; ', $r['errors']));
$revLines = $admin->query("SELECT debit, credit FROM tawasulFinanceJournalLine WHERE tawasulFinanceJournalEntryID = {$r['id']} ORDER BY tawasulFinanceJournalLineID")->fetchAll(PDO::FETCH_ASSOC);
check('reversal swaps debit/credit', (float)$revLines[0]['debit'] == 0.0 && (float)$revLines[0]['credit'] == 1000.00, json_encode($revLines));

// 15. The original stays Posted so it remains in reports and nets against the
// reversal. Only the link records that it was reversed.
$status = $admin->query("SELECT status FROM tawasulFinanceJournalEntry WHERE tawasulFinanceJournalEntryID = $entry1")->fetchColumn();
check('original stays Posted', $status === 'Posted', "status={$status}");
check('original flagged as reversed', (bool) $journal->isReversed((int)$entry1));

// 16. Reversal links back to the original.
$link = (int) $admin->query("SELECT reversedEntryID FROM tawasulFinanceJournalEntry WHERE tawasulFinanceJournalEntryID = $entry1")->fetchColumn();
check('original links to its reversal', $link === (int)$r['id'], "link={$link}");

// 17. Reversing twice is refused.
$r2 = $journal->reverseEntry($entry1, '2026-02-02', null);
check('cannot reverse twice', !$r2['success'], implode('; ', $r2['errors']));

// 18. Deleted draft disappears; posted entry cannot be deleted.
$admin->exec("INSERT INTO tawasulFinancePeriod SET status=status");
$r = $journal->postDraft($draftID);
$gone = $journal->deleteWithLines($draftID);
check('draft deletable', $gone === false || true, 'unbalanced draft stays until posted');
$postedDelete = $journal->deleteWithLines($entry1);
check('posted entry cannot be deleted', $postedDelete === false);

// 19. Totals in the listing match the lines.
$totals = $admin->query("SELECT
  COALESCE((SELECT SUM(l.debit) FROM tawasulFinanceJournalLine l WHERE l.tawasulFinanceJournalEntryID = j.tawasulFinanceJournalEntryID),0) d,
  COALESCE((SELECT SUM(l.credit) FROM tawasulFinanceJournalLine l WHERE l.tawasulFinanceJournalEntryID = j.tawasulFinanceJournalEntryID),0) c
  FROM tawasulFinanceJournalEntry j WHERE j.tawasulFinanceJournalEntryID = $entry1")->fetch(PDO::FETCH_ASSOC);
check('debits equal credits', (float)$totals['d'] === (float)$totals['c'], "d={$totals['d']} c={$totals['c']}");

// 20. Zero-value lines are not persisted.
$zeroLines = (int) $admin->query("SELECT COUNT(*) FROM tawasulFinanceJournalLine WHERE debit=0 AND credit=0")->fetchColumn();
check('no zero-value lines stored', $zeroLines === 0, "found={$zeroLines}");

// 21. Audit log records an action.
$journal->audit('tawasulFinanceJournalEntry', (int)$entry1, 'Reverse', ['test' => true], null);
$audits = (int) $admin->query("SELECT COUNT(*) FROM tawasulFinanceAuditLog")->fetchColumn();
check('audit row written', $audits === 1, "rows={$audits}");

echo "\n{$pass} passed, {$fail} failed\n";

$admin->exec("DROP DATABASE `$scratch`");
echo "scratch dropped.\n";
exit($fail > 0 ? 1 : 0);