<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/discounts_add.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('الخصومات والمنح', 'discounts_manage.php')->add(__('Add'));
$values = [];

$form = Form::create('discounts', $session->get('absoluteURL').'/modules/TawasulFinance/discounts_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$row = $form->addRow(); $row->addLabel('name', 'الاسم'); $row->addTextField('name')->maxLength(150)->setValue($values['name'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('kind', 'النوع'); $row->addSelect('kind')->fromArray(['Sibling' => 'أخوة', 'Merit' => 'تفوق', 'Exemption' => 'إعفاء', 'Staff' => 'أبناء موظفين', 'Other' => 'أخرى'])->selected($values['kind'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('method', 'الطريقة'); $row->addSelect('method')->fromArray(['Percent' => 'نسبة', 'Fixed' => 'مبلغ ثابت'])->selected($values['method'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('value', 'القيمة'); $row->addNumber('value')->decimalPlaces(2)->setValue($values['value'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('tawasulFinanceExpenseAccountID', 'حساب الخصم'); $row->addSelect('tawasulFinanceExpenseAccountID')->fromArray(saAccountOptions($pdo))->placeholder()->selected($values['tawasulFinanceExpenseAccountID'] ?? '');
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo '<div class="sa-rtl">'.$form->getOutput().'</div>';
