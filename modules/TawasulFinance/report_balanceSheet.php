<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/report_balanceSheet.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('الميزانية العمومية');
saRtl();
echo '<div class="sa-rtl"><h2>الميزانية العمومية</h2>';
echo '<p>'.nl2br(htmlspecialchars(saSetting($container, 'schoolHeader', $session->get('organisationName')))).'</p>';
$L = saLedger($container);

$to = $_GET['to'] ?? date('Y-m-d');
echo '<form method="get" class="noprint"><input type="hidden" name="q" value="/modules/TawasulFinance/report_balanceSheet.php">كما في <input type="date" name="to" value="'.htmlspecialchars($to).'"> <input type="submit" value="عرض"></form>';
$g = ['Asset' => [], 'Liability' => [], 'Equity' => []]; $t = ['Asset' => 0, 'Liability' => 0, 'Equity' => 0]; $net = 0;
foreach ($L->balances(null, $to) as $r) {
    $v = Ledger::natural($r);
    if ($r['type'] == 'Revenue') { $net += $v; continue; }
    if ($r['type'] == 'Expense') { $net -= $v; continue; }
    if (!$v) continue; $g[$r['type']][] = [$r['code'], $r['name'], $v]; $t[$r['type']] += $v;
}
$g['Equity'][] = ['', 'فائض/عجز غير مقفل', $net]; $t['Equity'] += $net;
if (($_GET['export'] ?? '') == 'csv') saExportCsv('balance_sheet', ['الكود', 'الحساب', 'المبلغ'], array_merge($g['Asset'], $g['Liability'], $g['Equity']));
saExportLinks('/modules/TawasulFinance/report_balanceSheet.php&to='.$to);
$f = fn($a) => array_map(fn($r) => [$r[0], $r[1], saMoney($container, $r[2])], $a);
echo '<h3>الأصول</h3>'.saTable(['الكود', 'الحساب', 'المبلغ'], $f($g['Asset']), [2], ['', 'إجمالي الأصول', saMoney($container, $t['Asset'])]);
echo '<h3>الخصوم</h3>'.saTable(['الكود', 'الحساب', 'المبلغ'], $f($g['Liability']), [2], ['', 'إجمالي الخصوم', saMoney($container, $t['Liability'])]);
echo '<h3>حقوق الملكية</h3>'.saTable(['الكود', 'الحساب', 'المبلغ'], $f($g['Equity']), [2], ['', 'إجمالي حقوق الملكية', saMoney($container, $t['Equity'])]);
$d = $t['Asset'] - $t['Liability'] - $t['Equity'];
echo abs($d) < 0.01 ? '<p style="color:green">الأصول = الخصوم + حقوق الملكية</p>' : '<p class="error">فرق: '.saMoney($container, $d).'</p>';
echo '</div>';
