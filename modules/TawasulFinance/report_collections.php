<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/report_collections.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('تقرير التحصيل');
saRtl();
echo '<div class="sa-rtl"><h2>تقرير التحصيل</h2>';
echo '<p>'.nl2br(htmlspecialchars(saSetting($container, 'schoolHeader', $session->get('organisationName')))).'</p>';
$L = saLedger($container);

[$from, $to] = saRange();
saFilter('/modules/TawasulFinance/report_collections.php', $from, $to);
$rows = $pdo->select("SELECT COALESCE(yg.name,'—') grade, SUM(i.netAmount) billed, SUM(i.paidAmount) paid FROM tawasulFinanceInvoice i
    LEFT JOIN tawasulStudentEnrolment se ON se.tawasulPersonID=i.tawasulPersonID AND se.tawasulSchoolYearID=i.tawasulSchoolYearID LEFT JOIN tawasulYearGroup yg ON yg.tawasulYearGroupID=se.tawasulYearGroupID
    WHERE i.status<>'Cancelled' AND i.invoiceDueDate BETWEEN :f AND :t GROUP BY grade ORDER BY grade", ['f' => $from, 't' => $to])->fetchAll();
$m = fn($v) => saMoney($container, $v);
echo '<h3>نسبة التحصيل حسب الصف</h3>'.saTable(['الصف', 'المفوتر', 'المحصل', 'المتبقي', 'نسبة التحصيل'], array_map(fn($r) => [$r['grade'], $m($r['billed']), $m($r['paid']), $m($r['billed'] - $r['paid']), ((float)$r['billed'] ? round($r['paid'] / $r['billed'] * 100, 1) : 0).'%'], $rows), [1, 2, 3, 4]);
$d = $pdo->select("SELECT r.date, c.name cash, r.method, SUM(r.amount) total FROM tawasulFinanceReceipt r JOIN tawasulFinanceCashAccount c ON c.tawasulFinanceCashAccountID=r.tawasulFinanceCashAccountID WHERE r.date BETWEEN :f AND :t GROUP BY r.date, cash, r.method ORDER BY r.date DESC", ['f' => $from, 't' => $to])->fetchAll();
echo '<h3>التحصيل اليومي حسب الصندوق</h3>'.saTable(['التاريخ', 'الصندوق', 'الطريقة', 'المبلغ'], array_map(fn($r) => [$r['date'], $r['cash'], $r['method'], $m($r['total'])], $d), [3], ['الإجمالي', '', '', $m(array_sum(array_column($d, 'total')))]);
echo '</div>';
