<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/journal_manage.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('القيود اليومية');
saRtl();
echo '<div class="sa-rtl"><h2>القيود اليومية</h2>';
if ($m = $session->get('saError')) { echo '<div class="message">'.htmlspecialchars($m).'</div>'; $session->forget('saError'); }

[$from, $to] = saRange();
saFilter('/modules/TawasulFinance/journal_manage.php', $from, $to);
echo '<a class="button noprint" href="'.$session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/journal_add.php">+ قيد جديد</a>';
$rows = $pdo->select("SELECT e.*, SUM(l.debit) AS total FROM tawasulFinanceJournalEntry e JOIN tawasulFinanceJournalLine l USING (tawasulFinanceJournalEntryID)
    WHERE e.date BETWEEN :f AND :t GROUP BY e.tawasulFinanceJournalEntryID ORDER BY e.date DESC, e.tawasulFinanceJournalEntryID DESC", ['f' => $from, 't' => $to])->fetchAll();
$st = ['Draft' => 'مسودة', 'Posted' => 'مرحّل', 'Reversed' => 'معكوس'];
$out = array_map(fn($r) => [$r['documentNumber'], saDate($container, $r['date']), htmlspecialchars($r['description']), $r['sourceType'] ?: 'يدوي', $st[$r['status']], saMoney($container, $r['total']),
    '<a href="'.$session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/journal_view.php&id='.$r['tawasulFinanceJournalEntryID'].'">عرض</a>'], $rows);
echo saTable(['الرقم', 'التاريخ', 'البيان', 'المصدر', 'الحالة', 'المبلغ', ''], $out, [5]);
echo '</div>';
