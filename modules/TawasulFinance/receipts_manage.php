<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/receipts_manage.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('سندات القبض');
saRtl();
echo '<div class="sa-rtl"><h2>سندات القبض</h2>';
if ($m = $session->get('saError')) { echo '<div class="message">'.htmlspecialchars($m).'</div>'; $session->forget('saError'); }

[$from, $to] = saRange();
saFilter('/modules/TawasulFinance/receipts_manage.php', $from, $to);
$a = $session->get('absoluteURL');
echo '<a class="button noprint" href="'.$a.'/index.php?q=/modules/TawasulFinance/receipts_add.php">+ سند قبض</a>';
$rows = $pdo->select("SELECT r.*, i.`key` AS invoiceNumber, CONCAT(p.surname,' ',p.preferredName) student, c.name cash FROM tawasulFinanceReceipt r
    JOIN tawasulFinanceInvoice i USING (tawasulFinanceInvoiceID) JOIN tawasulFinanceInvoicee n ON n.tawasulFinanceInvoiceeID=i.tawasulFinanceInvoiceeID JOIN tawasulPerson p ON p.tawasulPersonID=n.tawasulPersonID
    JOIN tawasulFinanceCashAccount c ON c.tawasulFinanceCashAccountID=r.tawasulFinanceCashAccountID WHERE r.date BETWEEN :f AND :t ORDER BY r.date DESC", ['f' => $from, 't' => $to])->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('receipts', array_keys($rows[0] ?? []), $rows);
saExportLinks('/modules/TawasulFinance/receipts_manage.php&from='.$from.'&to='.$to);
$m = ['Cash' => 'نقداً', 'Transfer' => 'تحويل', 'Cheque' => 'شيك', 'Card' => 'بطاقة'];
echo saTable(['الرقم', 'التاريخ', 'الطالب', 'الفاتورة', 'الصندوق', 'الطريقة', 'المبلغ', ''], array_map(fn($r) => [$r['receiptNumber'], saDate($container, $r['date']), htmlspecialchars($r['student']), $r['invoiceNumber'], $r['cash'], $m[$r['method']], saMoney($container, $r['amount']),
    '<a target="_blank" href="'.$a.'/index.php?q=/modules/TawasulFinance/receipts_print.php&id='.$r['tawasulFinanceReceiptID'].'">إيصال</a>'], $rows), [6], ['الإجمالي', '', '', '', '', '', saMoney($container, array_sum(array_column($rows, 'amount'))), '']);
echo '</div>';
