<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/receipts_add.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('سند قبض جديد');
saRtl();
echo '<div class="sa-rtl"><h2>سند قبض جديد</h2>';
if ($m = $session->get('saError')) { echo '<div class="message">'.htmlspecialchars($m).'</div>'; $session->forget('saError'); }
$cash = $pdo->select('SELECT tawasulFinanceCashAccountID, name FROM tawasulFinanceCashAccount ORDER BY name')->fetchKeyPair();

$inv = $pdo->select("SELECT i.tawasulFinanceInvoiceID, CONCAT(i.`key`,' — ',p.surname,' ',p.preferredName,' — متبقي ',FORMAT(i.netAmount-i.paidAmount,2)) FROM tawasulFinanceInvoice i JOIN tawasulFinanceInvoicee n ON n.tawasulFinanceInvoiceeID=i.tawasulFinanceInvoiceeID JOIN tawasulPerson p ON p.tawasulPersonID=n.tawasulPersonID WHERE i.status IN ('Issued','Partial') ORDER BY p.surname, i.invoiceDueDate")->fetchKeyPair();
$form = Form::create('receipt', $session->get('absoluteURL').'/modules/TawasulFinance/receipts_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));

$row = $form->addRow(); $row->addLabel('invoiceID', 'الفاتورة'); $row->addSelect('invoiceID')->fromArray($inv)->selected($_GET['invoice'] ?? '')->required()->placeholder();
$row = $form->addRow(); $row->addLabel('tawasulFinanceCashAccountID', 'الصندوق/البنك'); $row->addSelect('tawasulFinanceCashAccountID')->fromArray($cash)->required();
$row = $form->addRow(); $row->addLabel('date', 'التاريخ'); $row->addDate('date')->setValue(Format::date(date('Y-m-d')))->required();
$row = $form->addRow(); $row->addLabel('amount', 'المبلغ (يسمح بالدفع الجزئي)'); $row->addNumber('amount')->decimalPlaces(2)->required();
$row = $form->addRow(); $row->addLabel('method', 'طريقة الدفع'); $row->addSelect('method')->fromArray(['Cash' => 'نقداً', 'Transfer' => 'تحويل', 'Cheque' => 'شيك', 'Card' => 'بطاقة']);
$row = $form->addRow(); $row->addLabel('reference', 'رقم المرجع/الشيك'); $row->addTextField('reference');
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo $form->getOutput();
echo '</div>';
