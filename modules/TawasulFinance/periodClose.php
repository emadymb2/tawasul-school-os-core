<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/periodClose.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('إغلاق الفترات والسنة المالية');
saRtl();
echo '<div class="sa-rtl"><h2>إغلاق الفترات والسنة المالية</h2>';
if ($m = $session->get('saError')) { echo '<div class="message">'.htmlspecialchars($m).'</div>'; $session->forget('saError'); }

$u = $session->get('absoluteURL').'/modules/TawasulFinance/periodCloseProcess.php';
$p = $pdo->select("SELECT p.*, f.name fy FROM tawasulFinancePeriod p JOIN tawasulFinanceFiscalYear f ON f.tawasulFinanceFiscalYearID=p.tawasulFinanceFiscalYearID ORDER BY p.startDate")->fetchAll();
echo saTable(['السنة', 'الفترة', 'من', 'إلى', 'الحالة', ''], array_map(fn($r) => [$r['fy'], $r['name'], $r['startDate'], $r['endDate'], $r['status'] == 'Open' ? 'مفتوحة' : 'مغلقة',
    $r['status'] == 'Open' ? '<a href="'.$u.'?period='.$r['tawasulFinancePeriodID'].'" onclick="return confirm(\'إغلاق الفترة يمنع أي قيود عليها. متابعة؟\')">إغلاق</a>' : ''], $p));
echo '<h3>إقفال سنة مالية</h3><p>يُنشئ قيد إقفال الإيرادات والمصروفات إلى الأرباح المحتجزة ثم يغلق جميع فترات السنة. أرصدة الميزانية تنتقل تلقائياً للسنة التالية لأنها تراكمية.</p>';
$fy = $pdo->select("SELECT tawasulFinanceFiscalYearID, name FROM tawasulFinanceFiscalYear WHERE status='Open'")->fetchKeyPair();
foreach ($fy as $k => $n) echo '<a class="button" href="'.$u.'?year='.$k.'" onclick="return confirm(\'إقفال السنة نهائياً؟\')">إقفال '.htmlspecialchars($n).'</a> ';
echo '</div>';
