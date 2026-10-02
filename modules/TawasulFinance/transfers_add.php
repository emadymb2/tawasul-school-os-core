<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/transfers_add.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('التحويل بين الصناديق والبنوك');
saRtl();
echo '<div class="sa-rtl"><h2>التحويل بين الصناديق والبنوك</h2>';
if ($m = $session->get('saError')) { echo '<div class="message">'.htmlspecialchars($m).'</div>'; $session->forget('saError'); }
$cash = $pdo->select('SELECT tawasulFinanceCashAccountID, name FROM tawasulFinanceCashAccount ORDER BY name')->fetchKeyPair();
$form = Form::create('trf', $session->get('absoluteURL').'/modules/TawasulFinance/transfers_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));

$row = $form->addRow(); $row->addLabel('from', 'من'); $row->addSelect('from')->fromArray($cash)->required();
$row = $form->addRow(); $row->addLabel('to', 'إلى'); $row->addSelect('to')->fromArray($cash)->required();
$row = $form->addRow(); $row->addLabel('date', 'التاريخ'); $row->addDate('date')->setValue(Format::date(date('Y-m-d')))->required();
$row = $form->addRow(); $row->addLabel('amount', 'المبلغ'); $row->addNumber('amount')->decimalPlaces(2)->required();
$row = $form->addRow(); $row->addLabel('memo', 'البيان'); $row->addTextField('memo');
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo $form->getOutput();

$rows = $pdo->select("SELECT t.date, f.name fn, o.name tn, t.amount, t.memo FROM tawasulFinanceTransfer t JOIN tawasulFinanceCashAccount f ON f.tawasulFinanceCashAccountID=t.tawasulFinanceFromCashAccountID JOIN tawasulFinanceCashAccount o ON o.tawasulFinanceCashAccountID=t.toCashAccountID ORDER BY t.date DESC LIMIT 50")->fetchAll();
echo '<h3>آخر التحويلات</h3>'.saTable(['التاريخ', 'من', 'إلى', 'المبلغ', 'البيان'], array_map(fn($r) => [$r['date'], $r['fn'], $r['tn'], saMoney($container, $r['amount']), htmlspecialchars($r['memo'] ?? '')], $rows), [3]);
echo '</div>';
