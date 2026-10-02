<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/report_incomeStatement.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('قائمة الدخل');
saRtl();
echo '<div class="sa-rtl"><h2>قائمة الدخل</h2>';
echo '<p>'.nl2br(htmlspecialchars(saSetting($container, 'schoolHeader', $session->get('organisationName')))).'</p>';
$L = saLedger($container);
$ccs = $pdo->select('SELECT tawasulFinanceCostCenterID, name FROM tawasulFinanceCostCenter ORDER BY code')->fetchKeyPair();

[$from, $to] = saRange(); $cc = $_GET['cc'] ?? '';
$o = '<option value="">كل المراكز</option>'; foreach ($ccs as $k => $v) $o .= '<option value="'.$k.'"'.($k == $cc ? ' selected' : '').'>'.htmlspecialchars($v).'</option>';
saFilter('/modules/TawasulFinance/report_incomeStatement.php', $from, $to, 'مركز التكلفة <select name="cc">'.$o.'</select>');
$rev = $exp = []; $tr = $te = 0;
foreach ($L->balances($from, $to, $cc ? (int)$cc : null) as $r) {
    $v = Ledger::natural($r); if (!$v) continue;
    if ($r['type'] == 'Revenue') { $rev[] = [$r['code'], $r['name'], $v]; $tr += $v; }
    if ($r['type'] == 'Expense') { $exp[] = [$r['code'], $r['name'], $v]; $te += $v; }
}
if (($_GET['export'] ?? '') == 'csv') saExportCsv('income_statement', ['الكود', 'الحساب', 'المبلغ'], array_merge($rev, [['', 'إجمالي الإيرادات', $tr]], $exp, [['', 'إجمالي المصروفات', $te], ['', 'صافي الفائض/العجز', $tr - $te]]));
saExportLinks('/modules/TawasulFinance/report_incomeStatement.php&from='.$from.'&to='.$to.'&cc='.$cc);
$f = fn($a) => array_map(fn($r) => [$r[0], $r[1], saMoney($container, $r[2])], $a);
echo '<h3>الإيرادات</h3>'.saTable(['الكود', 'الحساب', 'المبلغ'], $f($rev), [2], ['', 'إجمالي الإيرادات', saMoney($container, $tr)]);
echo '<h3>المصروفات</h3>'.saTable(['الكود', 'الحساب', 'المبلغ'], $f($exp), [2], ['', 'إجمالي المصروفات', saMoney($container, $te)]);
echo '<h3>صافي '.($tr >= $te ? 'الفائض' : 'العجز').': '.saMoney($container, $tr - $te).'</h3>';
echo '</div>';
