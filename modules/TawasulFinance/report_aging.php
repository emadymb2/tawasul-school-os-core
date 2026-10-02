<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/report_aging.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('أعمار الديون والمتأخرات');
saRtl();
echo '<div class="sa-rtl"><h2>أعمار الديون والمتأخرات</h2>';
echo '<p>'.nl2br(htmlspecialchars(saSetting($container, 'schoolHeader', $session->get('organisationName')))).'</p>';
$L = saLedger($container);

$asOf = $_GET['to'] ?? date('Y-m-d');
echo '<form method="get" class="noprint"><input type="hidden" name="q" value="/modules/TawasulFinance/report_aging.php">كما في <input type="date" name="to" value="'.htmlspecialchars($asOf).'"> <input type="submit" value="عرض"></form>';
$p = []; for ($k = 1; $k <= 7; $k++) $p['d'.$k] = $asOf;
$rows = $pdo->select("SELECT CONCAT(p.surname,' ',p.preferredName) student, yg.name grade,
    SUM(CASE WHEN DATEDIFF(:d1,i.invoiceDueDate) <= 0 THEN i.netAmount-i.paidAmount ELSE 0 END) cur,
    SUM(CASE WHEN DATEDIFF(:d2,i.invoiceDueDate) BETWEEN 1 AND 30 THEN i.netAmount-i.paidAmount ELSE 0 END) b30,
    SUM(CASE WHEN DATEDIFF(:d3,i.invoiceDueDate) BETWEEN 31 AND 60 THEN i.netAmount-i.paidAmount ELSE 0 END) b60,
    SUM(CASE WHEN DATEDIFF(:d4,i.invoiceDueDate) BETWEEN 61 AND 90 THEN i.netAmount-i.paidAmount ELSE 0 END) b90,
    SUM(CASE WHEN DATEDIFF(:d5,i.invoiceDueDate) > 90 THEN i.netAmount-i.paidAmount ELSE 0 END) b90p,
    SUM(i.netAmount-i.paidAmount) total
  FROM tawasulFinanceInvoice i
  JOIN tawasulFinanceInvoicee n ON n.tawasulFinanceInvoiceeID=i.tawasulFinanceInvoiceeID
  JOIN tawasulPerson p ON p.tawasulPersonID=n.tawasulPersonID
  LEFT JOIN tawasulStudentEnrolment se ON se.tawasulPersonID=n.tawasulPersonID AND se.tawasulSchoolYearID=i.tawasulSchoolYearID LEFT JOIN tawasulYearGroup yg ON yg.tawasulYearGroupID=se.tawasulYearGroupID
  WHERE i.status IN ('Issued','Partial') AND i.invoiceIssueDate<=:d6 GROUP BY n.tawasulPersonID HAVING total>0 ORDER BY total DESC", array_diff_key($p, ['d7' => 1]))->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('aging', array_keys($rows[0] ?? []), $rows);
saExportLinks('/modules/TawasulFinance/report_aging.php&to='.$asOf);
$m = fn($v) => saMoney($container, $v); $s = fn($k) => $m(array_sum(array_column($rows, $k)));
echo saTable(['الطالب', 'الصف', 'غير مستحق', '1-30 يوم', '31-60', '61-90', '+90', 'الإجمالي'], array_map(fn($r) => [htmlspecialchars($r['student']), $r['grade'], $m($r['cur']), $m($r['b30']), $m($r['b60']), $m($r['b90']), $m($r['b90p']), '<b>'.$m($r['total']).'</b>'], $rows), [2, 3, 4, 5, 6, 7],
    ['الإجمالي', '', $s('cur'), $s('b30'), $s('b60'), $s('b90'), $s('b90p'), $s('total')]);
echo '</div>';
