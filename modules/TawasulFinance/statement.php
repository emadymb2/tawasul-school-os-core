<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/statement.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('كشف حساب الطالب');
saRtl();
echo '<div class="sa-rtl"><h2>كشف حساب الطالب</h2>';

$role = $session->get('tawasulRoleIDCurrentCategory');
if ($role == 'Parent') {
    $kids = $pdo->select("SELECT p.tawasulPersonID, CONCAT(p.surname,' ',p.preferredName) FROM tawasulFamilyAdult fa JOIN tawasulFamilyChild fc USING (tawasulFamilyID) JOIN tawasulPerson p ON p.tawasulPersonID=fc.tawasulPersonID WHERE fa.tawasulPersonID=:me AND fa.childDataAccess='Y'", ['me' => $session->get('tawasulPersonID')])->fetchKeyPair();
} else {
    // An invoice is addressed to an invoicee, so the billed person is reached
    // through it rather than held on the invoice.
    $kids = $pdo->select("SELECT DISTINCT p.tawasulPersonID, CONCAT(p.surname,' ',p.preferredName) n FROM tawasulFinanceInvoice i JOIN tawasulFinanceInvoicee n ON n.tawasulFinanceInvoiceeID=i.tawasulFinanceInvoiceeID JOIN tawasulPerson p ON p.tawasulPersonID=n.tawasulPersonID ORDER BY n")->fetchKeyPair();
}
$pid = $_GET['student'] ?? array_key_first($kids);
if (!isset($kids[$pid])) { echo '<p>لا يوجد طلاب متاحون.</p></div>'; return; }
echo '<form method="get" class="noprint"><input type="hidden" name="q" value="/modules/TawasulFinance/statement.php"><select name="student">';
foreach ($kids as $k => $v) echo '<option value="'.$k.'"'.($k == $pid ? ' selected' : '').'>'.htmlspecialchars($v).'</option>';
echo '</select> <input type="submit" value="عرض"></form><h3>'.htmlspecialchars($kids[$pid]).'</h3>';
// The surviving invoice model issues one invoice per billing-schedule run, so
// there is no installment number to show. The billed person is joined through
// the invoicee on both sides of the union.
$mv = $pdo->select("(SELECT i.invoiceIssueDate d, i.`key` ref, 'فاتورة' des, i.netAmount dr, 0 cr FROM tawasulFinanceInvoice i JOIN tawasulFinanceInvoicee n ON n.tawasulFinanceInvoiceeID=i.tawasulFinanceInvoiceeID WHERE n.tawasulPersonID=:p AND i.status<>'Cancelled')
    UNION ALL (SELECT r.date, r.receiptNumber, 'سند قبض', 0, r.amount FROM tawasulFinanceReceipt r JOIN tawasulFinanceInvoice i USING (tawasulFinanceInvoiceID) JOIN tawasulFinanceInvoicee n ON n.tawasulFinanceInvoiceeID=i.tawasulFinanceInvoiceeID WHERE n.tawasulPersonID=:p2) ORDER BY d, ref", ['p' => $pid, 'p2' => $pid])->fetchAll();
$b = 0; $out = [];
foreach ($mv as $r) { $b += $r['dr'] - $r['cr']; $out[] = [saDate($container, $r['d']), $r['ref'], $r['des'], saMoney($container, $r['dr']), saMoney($container, $r['cr']), saMoney($container, $b)]; }
echo saTable(['التاريخ', 'المرجع', 'البيان', 'مدين', 'دائن', 'الرصيد'], $out, [3, 4, 5]).'<h3>الرصيد المستحق: '.saMoney($container, $b).'</h3><a class="button noprint" href="javascript:window.print()">طباعة</a>';
echo '</div>';
