<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feeItems_add.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('بنود الرسوم', 'feeItems_manage.php')->add(__('Add'));
$values = [];

$form = Form::create('feeItems', $session->get('absoluteURL').'/modules/TawasulFinance/feeItems_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$row = $form->addRow(); $row->addLabel('name', 'البند'); $row->addTextField('name')->maxLength(150)->setValue($values['name'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('category', 'الفئة'); $row->addSelect('category')->fromArray(['Registration' => 'تسجيل', 'Tuition' => 'دراسية', 'Transport' => 'نقل', 'Uniform' => 'زي', 'Activity' => 'أنشطة', 'Other' => 'أخرى'])->selected($values['category'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('tawasulFinanceRevenueAccountID', 'حساب الإيراد'); $row->addSelect('tawasulFinanceRevenueAccountID')->fromArray(saAccountOptions($pdo))->placeholder()->selected($values['tawasulFinanceRevenueAccountID'] ?? '');
$row = $form->addRow(); $row->addLabel('defaultAmount', 'المبلغ الافتراضي'); $row->addNumber('defaultAmount')->decimalPlaces(2)->setValue($values['defaultAmount'] ?? '')->required();
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo '<div class="sa-rtl">'.$form->getOutput().'</div>';
