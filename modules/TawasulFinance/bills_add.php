<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/bills_add.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('فاتورة مشتريات جديدة');
saRtl();
echo '<div class="sa-rtl"><h2>فاتورة مشتريات جديدة</h2>';
if ($m = $session->get('saError')) { echo '<div class="message">'.htmlspecialchars($m).'</div>'; $session->forget('saError'); }
$ccs = $pdo->select('SELECT tawasulFinanceCostCenterID, name FROM tawasulFinanceCostCenter ORDER BY code')->fetchKeyPair();

$sup = $pdo->select('SELECT tawasulFinanceSupplierID, name FROM tawasulFinanceSupplier ORDER BY name')->fetchKeyPair();
$form = Form::create('bill', $session->get('absoluteURL').'/modules/TawasulFinance/bills_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));

$row = $form->addRow(); $row->addLabel('supplierID', 'المورد'); $row->addSelect('supplierID')->fromArray($sup)->required()->placeholder();
$row = $form->addRow(); $row->addLabel('date', 'التاريخ'); $row->addDate('date')->setValue(Format::date(date('Y-m-d')))->required();
$row = $form->addRow(); $row->addLabel('dueDate', 'تاريخ الاستحقاق'); $row->addDate('dueDate');
$row = $form->addRow(); $row->addLabel('tawasulFinanceExpenseAccountID', 'حساب المصروف/الأصل'); $row->addSelect('tawasulFinanceExpenseAccountID')->fromArray(saAccountOptions($pdo))->required()->placeholder();
$row = $form->addRow(); $row->addLabel('tawasulFinanceCostCenterID', 'مركز التكلفة'); $row->addSelect('tawasulFinanceCostCenterID')->fromArray($ccs)->placeholder();
$row = $form->addRow(); $row->addLabel('amount', 'المبلغ قبل الضريبة'); $row->addNumber('amount')->decimalPlaces(2)->required();
$row = $form->addRow(); $row->addLabel('taxAmount', 'الضريبة'); $row->addNumber('taxAmount')->decimalPlaces(2);
$row = $form->addRow(); $row->addLabel('description', 'البيان'); $row->addTextField('description')->required();
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo $form->getOutput();
echo '</div>';
