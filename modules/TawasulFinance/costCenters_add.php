<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/costCenters_add.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('مراكز التكلفة', 'costCenters_manage.php')->add(__('Add'));
$values = [];

$form = Form::create('costCenters', $session->get('absoluteURL').'/modules/TawasulFinance/costCenters_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$row = $form->addRow(); $row->addLabel('code', 'الكود'); $row->addTextField('code')->maxLength(150)->setValue($values['code'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('name', 'الاسم'); $row->addTextField('name')->maxLength(150)->setValue($values['name'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('type', 'النوع'); $row->addSelect('type')->fromArray(['Stage' => 'مرحلة', 'Grade' => 'صف', 'Activity' => 'نشاط', 'Department' => 'قسم'])->selected($values['type'] ?? '')->required();
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo '<div class="sa-rtl">'.$form->getOutput().'</div>';
