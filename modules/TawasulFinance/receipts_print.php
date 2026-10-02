<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/receipts_print.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('إيصال قبض');
saRtl();
echo '<div class="sa-rtl"><h2>إيصال قبض</h2>';

$r = $pdo->select("SELECT r.*, i.`key` AS invoiceNumber, i.netAmount, i.paidAmount, CONCAT(p.surname,' ',p.preferredName) student, CONCAT(u.preferredName,' ',u.surname) cashier FROM tawasulFinanceReceipt r
    JOIN tawasulFinanceInvoice i USING (tawasulFinanceInvoiceID) JOIN tawasulFinanceInvoicee n ON n.tawasulFinanceInvoiceeID=i.tawasulFinanceInvoiceeID JOIN tawasulPerson p ON p.tawasulPersonID=n.tawasulPersonID LEFT JOIN tawasulPerson u ON u.tawasulPersonID=r.tawasulPersonIDReceiver
    WHERE tawasulFinanceReceiptID=:id", ['id' => $_GET['id'] ?? ''])->fetch();
if (!$r) { echo 'غير موجود</div>'; return; }
echo '<div style="border:2px solid #333;padding:20px;max-width:600px">'.nl2br(htmlspecialchars(saSetting($container, 'schoolHeader', $session->get('organisationName'))));
echo '<h3 style="text-align:center">سند قبض رقم '.$r['receiptNumber'].'</h3>';
echo saTable(['', ''], [['التاريخ', saDate($container, $r['date'])], ['استلمنا من ولي أمر الطالب', htmlspecialchars($r['student'])], ['مبلغ وقدره', '<b>'.saMoney($container, $r['amount']).'</b>'],
    ['وذلك عن فاتورة', $r['invoiceNumber']], ['طريقة الدفع', $r['method'].' '.htmlspecialchars($r['reference'] ?? '')], ['المتبقي على الفاتورة', saMoney($container, $r['netAmount'] - $r['paidAmount'])], ['أمين الصندوق', htmlspecialchars($r['cashier'] ?? '')]]);
echo '<p style="margin-top:40px">التوقيع: ______________</p></div><a class="button noprint" href="javascript:window.print()">طباعة</a>';
echo '</div>';
