<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/report_cashFlow.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('قائمة التدفقات النقدية (مبسطة)');
saRtl();
echo '<div class="sa-rtl"><h2>قائمة التدفقات النقدية (مبسطة)</h2>';
echo '<p>'.nl2br(htmlspecialchars(saSetting($container, 'schoolHeader', $session->get('organisationName')))).'</p>';
$L = saLedger($container);

[$from, $to] = saRange();
saFilter('/modules/TawasulFinance/report_cashFlow.php', $from, $to);
$cashAccs = $pdo->select('SELECT tawasulFinanceAccountID FROM tawasulFinanceCashAccount')->fetchAll(PDO::FETCH_COLUMN) ?: [0];
$in = implode(',', array_map('intval', $cashAccs));
$open = (float)$pdo->selectOne("SELECT COALESCE(SUM(l.debit-l.credit),0) FROM tawasulFinanceJournalLine l JOIN tawasulFinanceJournalEntry e USING (tawasulFinanceJournalEntryID) WHERE l.tawasulFinanceAccountID IN ($in) AND e.status<>'Draft' AND e.date<:f", ['f' => $from]);
$rows = $pdo->select("SELECT COALESCE(e.sourceType,'Manual') src, SUM(l.debit-l.credit) net FROM tawasulFinanceJournalLine l JOIN tawasulFinanceJournalEntry e USING (tawasulFinanceJournalEntryID)
    WHERE l.tawasulFinanceAccountID IN ($in) AND e.status<>'Draft' AND e.date BETWEEN :f AND :t AND COALESCE(e.sourceType,'')<>'Transfer' GROUP BY src", ['f' => $from, 't' => $to])->fetchAll();
$names = ['Receipt' => 'تحصيل رسوم الطلاب', 'PaymentVoucher' => 'مدفوعات الموردين والمصروفات', 'Payroll' => 'الرواتب', 'Manual' => 'قيود يدوية/أخرى', 'Reversal' => 'قيود عكسية'];
$sum = 0; $out = [];
foreach ($rows as $r) { $out[] = [$names[$r['src']] ?? $r['src'], saMoney($container, $r['net'])]; $sum += $r['net']; }
echo saTable(['البند', 'صافي التدفق'], array_merge([['الرصيد النقدي أول المدة', saMoney($container, $open)]], $out), [1], ['الرصيد النقدي آخر المدة', saMoney($container, $open + $sum)]);
echo '</div>';
