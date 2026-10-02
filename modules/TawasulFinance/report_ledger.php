<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/report_ledger.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('دفتر الأستاذ');
saRtl();
echo '<div class="sa-rtl"><h2>دفتر الأستاذ</h2>';
echo '<p>'.nl2br(htmlspecialchars(saSetting($container, 'schoolHeader', $session->get('organisationName')))).'</p>';
$L = saLedger($container);

[$from, $to] = saRange(); $acc = $_GET['account'] ?? '';
$opts = ''; foreach (saAccountOptions($pdo) as $k => $v) $opts .= '<option value="'.$k.'"'.($k == $acc ? ' selected' : '').'>'.htmlspecialchars($v).'</option>';
saFilter('/modules/TawasulFinance/report_ledger.php', $from, $to, 'الحساب <select name="account">'.$opts.'</select>');
if ($acc) {
    $open = (float)$pdo->selectOne("SELECT COALESCE(SUM(l.debit-l.credit),0) FROM tawasulFinanceJournalLine l JOIN tawasulFinanceJournalEntry e USING (tawasulFinanceJournalEntryID) WHERE l.tawasulFinanceAccountID=:a AND e.status<>'Draft' AND e.date<:f", ['a' => $acc, 'f' => $from]);
    $mv = $pdo->select("SELECT e.date, e.documentNumber, COALESCE(l.memo, e.description) d, l.debit, l.credit FROM tawasulFinanceJournalLine l JOIN tawasulFinanceJournalEntry e USING (tawasulFinanceJournalEntryID)
        WHERE l.tawasulFinanceAccountID=:a AND e.status<>'Draft' AND e.date BETWEEN :f AND :t ORDER BY e.date, e.tawasulFinanceJournalEntryID", ['a' => $acc, 'f' => $from, 't' => $to])->fetchAll();
    $bal = $open; $rows = [['', '', 'رصيد أول المدة', '', '', $open]];
    foreach ($mv as $r) { $bal += $r['debit'] - $r['credit']; $rows[] = [$r['date'], $r['documentNumber'], $r['d'], $r['debit'], $r['credit'], $bal]; }
    if (($_GET['export'] ?? '') == 'csv') saExportCsv('ledger', ['التاريخ', 'الرقم', 'البيان', 'مدين', 'دائن', 'الرصيد'], $rows);
    saExportLinks('/modules/TawasulFinance/report_ledger.php&account='.$acc.'&from='.$from.'&to='.$to);
    $m = fn($v) => $v === '' ? '' : saMoney($container, $v);
    echo saTable(['التاريخ', 'الرقم', 'البيان', 'مدين', 'دائن', 'الرصيد'], array_map(fn($r) => [$r[0], $r[1], htmlspecialchars($r[2]), $m($r[3]), $m($r[4]), $m($r[5])], $rows), [3, 4, 5]);
}
echo '</div>';
