<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/fiscalYears_add.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('السنوات المالية', 'fiscalYears_manage.php')->add(__('Add'));
$values = [];

$form = Form::create('fiscalYears', $session->get('absoluteURL').'/modules/TawasulFinance/fiscalYears_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$row = $form->addRow(); $row->addLabel('name', 'الاسم'); $row->addTextField('name')->maxLength(150)->setValue($values['name'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('firstDay', 'أول يوم'); $row->addDate('firstDay')->setValue(isset($values['firstDay']) ? \TawasulOS\Services\Format::date($values['firstDay']) : '');
$row = $form->addRow(); $row->addLabel('lastDay', 'آخر يوم'); $row->addDate('lastDay')->setValue(isset($values['lastDay']) ? \TawasulOS\Services\Format::date($values['lastDay']) : '');
$row = $form->addRow(); $row->addLabel('status', 'الحالة'); $row->addSelect('status')->fromArray(['Open' => 'مفتوحة', 'Closed' => 'مغلقة'])->selected($values['status'] ?? '')->required();
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo '<div class="sa-rtl">'.$form->getOutput().'</div>';
