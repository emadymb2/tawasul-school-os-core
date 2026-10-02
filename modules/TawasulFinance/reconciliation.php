<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/reconciliation.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('المطابقة البنكية');
saRtl();
echo '<div class="sa-rtl"><h2>المطابقة البنكية</h2>';
if ($m = $session->get('saError')) { echo '<div class="message">'.htmlspecialchars($m).'</div>'; $session->forget('saError'); }
$cash = $pdo->select('SELECT tawasulFinanceCashAccountID, name FROM tawasulFinanceCashAccount ORDER BY name')->fetchKeyPair();

$cid = $_GET['cash'] ?? array_key_first($cash); $date = $_GET['date'] ?? date('Y-m-d');
echo '<form method="get" class="noprint"><input type="hidden" name="q" value="/modules/TawasulFinance/reconciliation.php">الحساب <select name="cash">';
foreach ($cash as $k => $v) echo '<option value="'.$k.'"'.($k == $cid ? ' selected' : '').'>'.htmlspecialchars($v).'</option>';
echo '</select> حتى تاريخ <input type="date" name="date" value="'.htmlspecialchars($date).'"> <input type="submit" value="عرض"></form>';
if ($cid) {
    $acc = $pdo->selectOne('SELECT tawasulFinanceAccountID FROM tawasulFinanceCashAccount WHERE tawasulFinanceCashAccountID=:c', ['c' => $cid]);
    $book = (float)$pdo->selectOne("SELECT COALESCE(SUM(l.debit-l.credit),0) FROM tawasulFinanceJournalLine l JOIN tawasulFinanceJournalEntry e USING (tawasulFinanceJournalEntryID) WHERE l.tawasulFinanceAccountID=:a AND e.status<>'Draft' AND e.date<=:d", ['a' => $acc, 'd' => $date]);
    echo '<p>الرصيد الدفتري: <b>'.saMoney($container, $book).'</b></p>';
    $mv = $pdo->select("SELECT e.date, e.documentNumber, e.description, l.debit, l.credit FROM tawasulFinanceJournalLine l JOIN tawasulFinanceJournalEntry e USING (tawasulFinanceJournalEntryID) WHERE l.tawasulFinanceAccountID=:a AND e.status<>'Draft' AND e.date<=:d ORDER BY e.date DESC LIMIT 100", ['a' => $acc, 'd' => $date])->fetchAll();
    echo '<form method="post" action="'.$session->get('absoluteURL').'/modules/TawasulFinance/reconciliationProcess.php"><input type="hidden" name="cash" value="'.$cid.'"><input type="hidden" name="date" value="'.htmlspecialchars($date).'"><input type="hidden" name="book" value="'.$book.'">
        رصيد كشف البنك <input type="number" step="0.01" name="statement" required> ملاحظات (شيكات معلقة/إيداعات بالطريق) <input name="notes" style="width:30%"> <input type="submit" value="حفظ المطابقة"></form>';
    echo '<h4>حركات الحساب</h4>'.saTable(['التاريخ', 'الرقم', 'البيان', 'مدين', 'دائن'], array_map(fn($r) => [$r['date'], $r['documentNumber'], htmlspecialchars($r['description']), saMoney($container, $r['debit']), saMoney($container, $r['credit'])], $mv), [3, 4]);
    $h = $pdo->select('SELECT statementDate, statementBalance, bookBalance, difference, notes FROM tawasulFinanceBankReconciliation WHERE tawasulFinanceCashAccountID=:c ORDER BY statementDate DESC', ['c' => $cid])->fetchAll();
    echo '<h4>سجل المطابقات</h4>'.saTable(['التاريخ', 'رصيد الكشف', 'الرصيد الدفتري', 'الفرق', 'ملاحظات'], array_map(fn($r) => [$r['statementDate'], saMoney($container, $r['statementBalance']), saMoney($container, $r['bookBalance']), saMoney($container, $r['difference']), htmlspecialchars($r['notes'] ?? '')], $h), [1, 2, 3]);
}
echo '</div>';
