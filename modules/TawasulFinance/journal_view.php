<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/journal_view.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('عرض القيد');
saRtl();
echo '<div class="sa-rtl"><h2>عرض القيد</h2>';
if ($m = $session->get('saError')) { echo '<div class="message">'.htmlspecialchars($m).'</div>'; $session->forget('saError'); }

$id = $_GET['id'] ?? '';
$e = $pdo->select("SELECT * FROM tawasulFinanceJournalEntry WHERE tawasulFinanceJournalEntryID=:id", ['id' => $id])->fetch();
if (!$e) { echo '<div class="error">غير موجود</div></div>'; return; }
echo '<p><b>'.$e['documentNumber'].'</b> — '.saDate($container, $e['date']).' — '.htmlspecialchars($e['description']).' — الحالة: '.$e['status'].'</p>';
$l = $pdo->select("SELECT CONCAT(a.code,' - ',a.name) acc, c.name cc, l.memo, l.debit, l.credit FROM tawasulFinanceJournalLine l JOIN tawasulFinanceAccount a ON a.tawasulFinanceAccountID=l.tawasulFinanceAccountID
    LEFT JOIN tawasulFinanceCostCenter c ON c.tawasulFinanceCostCenterID=l.tawasulFinanceCostCenterID WHERE l.tawasulFinanceJournalEntryID=:id", ['id' => $id])->fetchAll();
$dr = array_sum(array_column($l, 'debit')); $cr = array_sum(array_column($l, 'credit'));
echo saTable(['الحساب', 'مركز التكلفة', 'البيان', 'مدين', 'دائن'], array_map(fn($r) => [$r['acc'], $r['cc'], htmlspecialchars($r['memo'] ?? ''), saMoney($container, $r['debit']), saMoney($container, $r['credit'])], $l), [3, 4], ['الإجمالي', '', '', saMoney($container, $dr), saMoney($container, $cr)]);
$u = $session->get('absoluteURL').'/modules/TawasulFinance/journal_actionProcess.php?id='.$id;
echo '<div class="noprint">';
if ($e['status'] == 'Draft') echo '<a class="button" href="'.$u.'&do=approve">اعتماد وترحيل</a> ';
if ($e['status'] == 'Posted') echo '<form method="post" action="'.$u.'&do=reverse" style="display:inline">تاريخ العكس <input type="date" name="date" value="'.date('Y-m-d').'"> السبب <input name="reason"> <input type="submit" value="عكس القيد" onclick="return confirm(\'تأكيد؟\')"></form>';
echo ' <a class="button" href="javascript:window.print()">طباعة</a></div>';
$log = $pdo->select("SELECT a.action, a.timestamp, CONCAT(p.preferredName,' ',p.surname) who FROM tawasulFinanceAuditLog a LEFT JOIN tawasulPerson p USING (tawasulPersonID) WHERE tableName='JournalEntry' AND recordID=:id", ['id' => (int)$id])->fetchAll();
echo '<h4>سجل التدقيق</h4>'.saTable(['الإجراء', 'الوقت', 'المستخدم'], $log);
echo '</div>';
