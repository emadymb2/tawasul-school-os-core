<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/vouchers_add.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('سند صرف جديد');
saRtl();
echo '<div class="sa-rtl"><h2>سند صرف جديد</h2>';
if ($m = $session->get('saError')) { echo '<div class="message">'.htmlspecialchars($m).'</div>'; $session->forget('saError'); }
$cash = $pdo->select('SELECT tawasulFinanceCashAccountID, name FROM tawasulFinanceCashAccount ORDER BY name')->fetchKeyPair();
$ccs = $pdo->select('SELECT tawasulFinanceCostCenterID, name FROM tawasulFinanceCostCenter ORDER BY code')->fetchKeyPair();

$bills = $pdo->select("SELECT b.tawasulFinancePurchaseBillID, CONCAT(b.billNumber,' — ',s.name,' — متبقي ',FORMAT(b.amount+b.taxAmount-b.paidAmount,2)) FROM tawasulFinancePurchaseBill b JOIN tawasulFinanceSupplier s ON s.tawasulFinanceSupplierID=b.supplierID WHERE b.status IN ('Open','Partial')")->fetchKeyPair();
$form = Form::create('voucher', $session->get('absoluteURL').'/modules/TawasulFinance/vouchers_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));

$row = $form->addRow(); $row->addLabel('billID', 'فاتورة مورد (فارغ = مصروف مباشر)'); $row->addSelect('billID')->fromArray($bills)->placeholder()->selected($_GET['bill'] ?? '');
$row = $form->addRow(); $row->addLabel('tawasulFinanceExpenseAccountID', 'حساب المصروف (للمصروف المباشر)'); $row->addSelect('tawasulFinanceExpenseAccountID')->fromArray(saAccountOptions($pdo))->placeholder();
$row = $form->addRow(); $row->addLabel('tawasulFinanceCostCenterID', 'مركز التكلفة'); $row->addSelect('tawasulFinanceCostCenterID')->fromArray($ccs)->placeholder();
$row = $form->addRow(); $row->addLabel('tawasulFinanceCashAccountID', 'من الصندوق/البنك'); $row->addSelect('tawasulFinanceCashAccountID')->fromArray($cash)->required();
$row = $form->addRow(); $row->addLabel('date', 'التاريخ'); $row->addDate('date')->setValue(Format::date(date('Y-m-d')))->required();
$row = $form->addRow(); $row->addLabel('amount', 'المبلغ'); $row->addNumber('amount')->decimalPlaces(2)->required();
$row = $form->addRow(); $row->addLabel('method', 'الطريقة'); $row->addSelect('method')->fromArray(['Cash' => 'نقداً', 'Transfer' => 'تحويل', 'Cheque' => 'شيك']);
$row = $form->addRow(); $row->addLabel('reference', 'المرجع'); $row->addTextField('reference');
$row = $form->addRow(); $row->addLabel('memo', 'البيان'); $row->addTextField('memo');
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo $form->getOutput();
echo '</div>';
