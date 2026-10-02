<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/dashboard.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('لوحة المعلومات المالية');
saRtl();
echo '<div class="sa-rtl"><h2>لوحة المعلومات المالية</h2>';

$L = saLedger($container); $y0 = date('Y-01-01'); $today = date('Y-m-d');
$rev = $exp = $cash = 0;
$cashAccs = $pdo->select('SELECT tawasulFinanceAccountID FROM tawasulFinanceCashAccount')->fetchAll(PDO::FETCH_COLUMN);
foreach ($L->balances($y0, $today) as $r) { if ($r['type'] == 'Revenue') $rev += Ledger::natural($r); if ($r['type'] == 'Expense') $exp += Ledger::natural($r); }
foreach ($L->balances(null, $today) as $r) if (in_array($r['id'], $cashAccs)) $cash += Ledger::natural($r);
$ar = (float)$pdo->selectOne("SELECT COALESCE(SUM(netAmount-paidAmount),0) FROM tawasulFinanceInvoice WHERE status IN ('Issued','Partial') AND invoiceDueDate < CURDATE()");
$m = fn($v) => saMoney($container, $v);
foreach ([['الإيرادات منذ بداية السنة', $rev], ['المصروفات منذ بداية السنة', $exp], ['صافي الفائض', $rev - $exp], ['الرصيد النقدي والبنكي', $cash], ['المتأخرات المستحقة', $ar]] as [$t, $v]) echo '<div class="sa-kpi">'.$t.'<b class="sa-num">'.$m($v).'</b></div>';
$months = $pdo->select("SELECT DATE_FORMAT(e.date,'%Y-%m') m, a.type, SUM(l.credit-l.debit) v FROM tawasulFinanceJournalLine l JOIN tawasulFinanceJournalEntry e USING (tawasulFinanceJournalEntryID)
    JOIN tawasulFinanceAccount a ON a.tawasulFinanceAccountID=l.tawasulFinanceAccountID WHERE e.status<>'Draft' AND a.type IN ('Revenue','Expense') AND e.date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY m, a.type ORDER BY m")->fetchAll();
$data = []; foreach ($months as $r) { $data[$r['m']] ??= ['r' => 0, 'e' => 0]; $data[$r['m']][$r['type'] == 'Revenue' ? 'r' : 'e'] = $r['type'] == 'Revenue' ? (float)$r['v'] : -(float)$r['v']; }
$max = 1; foreach ($data as $d) $max = max($max, $d['r'], $d['e']);
echo '<h3>الإيرادات والمصروفات الشهرية</h3><div style="display:flex;align-items:flex-end;gap:10px;height:220px;border-bottom:1px solid #999;direction:ltr">';
foreach ($data as $mo => $d) echo '<div style="text-align:center;font-size:11px"><div style="display:flex;align-items:flex-end;gap:2px;height:190px"><div title="'.$m($d['r']).'" style="width:14px;background:#2f855a;height:'.round(max(0, $d['r']) / $max * 190).'px"></div><div title="'.$m($d['e']).'" style="width:14px;background:#c53030;height:'.round(max(0, $d['e']) / $max * 190).'px"></div></div>'.$mo.'</div>';
echo '</div><p><span style="color:#2f855a">■</span> إيرادات <span style="color:#c53030">■</span> مصروفات</p>';
$last = $pdo->select("SELECT documentNumber, date, description, status FROM tawasulFinanceJournalEntry ORDER BY tawasulFinanceJournalEntryID DESC LIMIT 10")->fetchAll();
echo '<h3>آخر القيود</h3>'.saTable(['الرقم', 'التاريخ', 'البيان', 'الحالة'], array_map(fn($r) => [$r['documentNumber'], $r['date'], htmlspecialchars($r['description']), $r['status']], $last));
echo '</div>';
