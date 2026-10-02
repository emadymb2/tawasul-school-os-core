<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/cashAccounts_edit.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('الصناديق والبنوك', 'cashAccounts_manage.php')->add(__('Edit'));
$values = $pdo->select('SELECT * FROM tawasulFinanceCashAccount WHERE tawasulFinanceCashAccountID=:id', ['id' => $_GET['id'] ?? ''])->fetch();
if (!$values) { $page->addError(__('The specified record cannot be found.')); return; }

$form = Form::create('cashAccounts', $session->get('absoluteURL').'/modules/TawasulFinance/cashAccounts_editProcess.php?id='.$values['tawasulFinanceCashAccountID']);
$form->addHiddenValue('address', $session->get('address'));
$row = $form->addRow(); $row->addLabel('name', 'الاسم'); $row->addTextField('name')->maxLength(150)->setValue($values['name'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('type', 'النوع'); $row->addSelect('type')->fromArray(['Cash' => 'صندوق', 'Bank' => 'بنك'])->selected($values['type'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('bankAccountNumber', 'رقم الحساب/IBAN'); $row->addTextField('bankAccountNumber')->maxLength(150)->setValue($values['bankAccountNumber'] ?? '');
$row = $form->addRow(); $row->addLabel('tawasulFinanceAccountID', 'الحساب المحاسبي'); $row->addSelect('tawasulFinanceAccountID')->fromArray(saAccountOptions($pdo))->placeholder()->selected($values['tawasulFinanceAccountID'] ?? '');
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo '<div class="sa-rtl">'.$form->getOutput().'</div>';
