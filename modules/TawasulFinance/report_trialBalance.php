<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/report_trialBalance.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('ميزان المراجعة');
saRtl();
echo '<div class="sa-rtl"><h2>ميزان المراجعة</h2>';
echo '<p>'.nl2br(htmlspecialchars(saSetting($container, 'schoolHeader', $session->get('organisationName')))).'</p>';
$L = saLedger($container);

[$from, $to] = saRange();
saFilter('/modules/TawasulFinance/report_trialBalance.php', $from, $to);
$open = []; foreach ($L->balances(null, date('Y-m-d', strtotime($from.' -1 day'))) as $r) $open[$r['id']] = $r['debit'] - $r['credit'];
$rows = []; $t = [0, 0, 0, 0];
foreach ($L->balances($from, $to) as $r) {
    $o = $open[$r['id']] ?? 0; $c = $o + $r['debit'] - $r['credit'];
    if (!$o && !(float)$r['debit'] && !(float)$r['credit']) continue;
    $rows[] = [$r['code'], $r['name'], $o, $r['debit'], $r['credit'], $c];
    $t[0] += $o; $t[1] += $r['debit']; $t[2] += $r['credit']; $t[3] += $c;
}
if (($_GET['export'] ?? '') == 'csv') saExportCsv('trial_balance', ['الكود', 'الحساب', 'رصيد أول المدة', 'مدين', 'دائن', 'رصيد آخر المدة'], $rows);
saExportLinks('/modules/TawasulFinance/report_trialBalance.php&from='.$from.'&to='.$to);
$m = fn($v) => saMoney($container, $v);
echo saTable(['الكود', 'الحساب', 'أول المدة (مدين+/دائن-)', 'حركة مدين', 'حركة دائن', 'آخر المدة'], array_map(fn($r) => [$r[0], $r[1], $m($r[2]), $m($r[3]), $m($r[4]), $m($r[5])], $rows), [2, 3, 4, 5], ['', 'الإجمالي', $m($t[0]), $m($t[1]), $m($t[2]), $m($t[3])]);
echo abs($t[1] - $t[2]) < 0.01 ? '<p style="color:green">الميزان متوازن</p>' : '<p class="error">تحذير: الميزان غير متوازن</p>';
echo '</div>';
