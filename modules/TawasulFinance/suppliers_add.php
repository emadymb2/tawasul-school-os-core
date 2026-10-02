<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/suppliers_add.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('الموردون', 'suppliers_manage.php')->add(__('Add'));
$values = [];

$form = Form::create('suppliers', $session->get('absoluteURL').'/modules/TawasulFinance/suppliers_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$row = $form->addRow(); $row->addLabel('name', 'المورد'); $row->addTextField('name')->maxLength(150)->setValue($values['name'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('phone', 'الهاتف'); $row->addTextField('phone')->maxLength(150)->setValue($values['phone'] ?? '');
$row = $form->addRow(); $row->addLabel('email', 'البريد'); $row->addTextField('email')->maxLength(150)->setValue($values['email'] ?? '');
$row = $form->addRow(); $row->addLabel('taxNumber', 'الرقم الضريبي'); $row->addTextField('taxNumber')->maxLength(150)->setValue($values['taxNumber'] ?? '');
$row = $form->addRow(); $row->addLabel('tawasulFinancePayableAccountID', 'حساب الدائنين'); $row->addSelect('tawasulFinancePayableAccountID')->fromArray(saAccountOptions($pdo))->placeholder()->selected($values['tawasulFinancePayableAccountID'] ?? '');
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo '<div class="sa-rtl">'.$form->getOutput().'</div>';
