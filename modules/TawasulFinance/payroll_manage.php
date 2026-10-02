<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/payroll_manage.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('مسير الرواتب');
saRtl();
echo '<div class="sa-rtl"><h2>مسير الرواتب</h2>';
if ($m = $session->get('saError')) { echo '<div class="message">'.htmlspecialchars($m).'</div>'; $session->forget('saError'); }
$cash = $pdo->select('SELECT tawasulFinanceCashAccountID, name FROM tawasulFinanceCashAccount ORDER BY name')->fetchKeyPair();

echo '<form method="post" class="noprint" action="'.$session->get('absoluteURL').'/modules/TawasulFinance/payroll_runProcess.php">الشهر <input type="month" name="month" value="'.date('Y-m').'" required>
 الصرف <select name="tawasulFinanceCashAccountID"><option value="">استحقاق فقط (رواتب مستحقة)</option>';
foreach ($cash as $k => $v) echo '<option value="'.$k.'">صرف مباشر من '.htmlspecialchars($v).'</option>';
echo '</select> <input type="submit" value="تشغيل وترحيل الرواتب" onclick="return confirm(\'تأكيد؟\')"></form>';
$a = $session->get('absoluteURL');
$rows = $pdo->select('SELECT * FROM tawasulFinancePayrollRun ORDER BY month DESC')->fetchAll();
echo saTable(['الشهر', 'الاستحقاقات', 'الاستقطاعات', 'الصافي', 'الحالة', ''], array_map(fn($r) => [$r['month'], saMoney($container, $r['totalEarnings']), saMoney($container, $r['totalDeductions']), saMoney($container, $r['netPay']), $r['status'],
    '<a href="'.$a.'/index.php?q=/modules/TawasulFinance/payroll_run.php&id='.$r['tawasulFinancePayrollRunID'].'">الكشف</a>'], $rows), [1, 2, 3]);
echo '</div>';
