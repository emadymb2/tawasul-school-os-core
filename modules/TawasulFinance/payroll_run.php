<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/payroll_run.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('كشف الرواتب');
saRtl();
echo '<div class="sa-rtl"><h2>كشف الرواتب</h2>';

$id = $_GET['id'] ?? '';
$lines = $pdo->select("SELECT CONCAT(p.surname,' ',p.preferredName) emp, c.type, l.amount FROM tawasulFinancePayrollLine l JOIN tawasulPerson p USING (tawasulPersonID)
    JOIN tawasulFinanceSalaryComponent c ON c.tawasulFinanceSalaryComponentID=l.tawasulFinanceSalaryComponentID WHERE l.tawasulFinancePayrollRunID=:id ORDER BY emp", ['id' => $id])->fetchAll();
$by = [];
foreach ($lines as $l) { $by[$l['emp']] ??= [0, 0]; $by[$l['emp']][$l['type'] == 'Earning' ? 0 : 1] += $l['amount']; }
$out = []; foreach ($by as $e => [$er, $de]) $out[] = [htmlspecialchars($e), saMoney($container, $er), saMoney($container, $de), saMoney($container, $er - $de)];
echo saTable(['الموظف', 'الاستحقاقات', 'الاستقطاعات', 'الصافي'], $out, [1, 2, 3]).'<a class="button noprint" href="javascript:window.print()">طباعة</a>';
echo '</div>';
