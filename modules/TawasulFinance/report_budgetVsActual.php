<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/report_budgetVsActual.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('الموازنة مقابل الفعلي');
saRtl();
echo '<div class="sa-rtl"><h2>الموازنة مقابل الفعلي</h2>';
echo '<p>'.nl2br(htmlspecialchars(saSetting($container, 'schoolHeader', $session->get('organisationName')))).'</p>';
$L = saLedger($container);

$fy = $pdo->select('SELECT tawasulFinanceFiscalYearID, name FROM tawasulFinanceFiscalYear ORDER BY firstDay DESC')->fetchKeyPair();
$sel = $_GET['fy'] ?? array_key_first($fy);
echo '<form method="get" class="noprint"><input type="hidden" name="q" value="/modules/TawasulFinance/report_budgetVsActual.php">السنة <select name="fy">';
foreach ($fy as $k => $v) echo '<option value="'.$k.'"'.($k == $sel ? ' selected' : '').'>'.htmlspecialchars($v).'</option>';
echo '</select> <input type="submit" value="عرض"></form>';
if ($sel) {
    $y = $pdo->select('SELECT * FROM tawasulFinanceFiscalYear WHERE tawasulFinanceFiscalYearID=:f', ['f' => $sel])->fetch();
    $rows = $pdo->select("SELECT a.code, a.name, a.type, c.name cc, b.amount budget,
        (SELECT COALESCE(SUM(l.debit-l.credit),0) FROM tawasulFinanceJournalLine l JOIN tawasulFinanceJournalEntry e USING (tawasulFinanceJournalEntryID)
         WHERE l.tawasulFinanceAccountID=b.tawasulFinanceAccountID AND (b.tawasulFinanceCostCenterID IS NULL OR l.tawasulFinanceCostCenterID=b.tawasulFinanceCostCenterID) AND e.status<>'Draft' AND e.date BETWEEN :f AND :t) net
      FROM tawasulFinanceBudget b JOIN tawasulFinanceAccount a ON a.tawasulFinanceAccountID=b.tawasulFinanceAccountID LEFT JOIN tawasulFinanceCostCenter c ON c.tawasulFinanceCostCenterID=b.tawasulFinanceCostCenterID
      WHERE b.tawasulFinanceFiscalYearID=:y ORDER BY a.code", ['f' => $y['firstDay'], 't' => $y['lastDay'], 'y' => $sel])->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $act = in_array($r['type'], ['Revenue', 'Liability', 'Equity']) ? -$r['net'] : $r['net'];
        $var = $act - $r['budget']; $pct = (float)$r['budget'] ? round($var / $r['budget'] * 100, 1) : 0;
        $out[] = [$r['code'].' '.$r['name'], $r['cc'], saMoney($container, $r['budget']), saMoney($container, $act), saMoney($container, $var), '<span style="color:'.(abs($pct) > 10 ? 'red' : 'green').'">'.$pct.'%</span>'];
    }
    echo saTable(['الحساب', 'مركز التكلفة', 'المخطط', 'الفعلي', 'الانحراف', 'نسبة الانحراف'], $out, [2, 3, 4, 5]);
}
echo '</div>';
