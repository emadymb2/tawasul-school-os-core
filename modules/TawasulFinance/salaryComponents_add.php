<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/salaryComponents_add.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('مكونات الراتب', 'salaryComponents_manage.php')->add(__('Add'));
$values = [];

$form = Form::create('salaryComponents', $session->get('absoluteURL').'/modules/TawasulFinance/salaryComponents_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$row = $form->addRow(); $row->addLabel('name', 'المكون'); $row->addTextField('name')->maxLength(150)->setValue($values['name'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('type', 'النوع'); $row->addSelect('type')->fromArray(['Earning' => 'استحقاق', 'Deduction' => 'استقطاع', 'Advance' => 'سلفة'])->selected($values['type'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('tawasulFinanceAccountID', 'الحساب'); $row->addSelect('tawasulFinanceAccountID')->fromArray(saAccountOptions($pdo))->placeholder()->selected($values['tawasulFinanceAccountID'] ?? '');
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo '<div class="sa-rtl">'.$form->getOutput().'</div>';
