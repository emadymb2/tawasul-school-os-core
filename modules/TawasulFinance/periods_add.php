<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/periods_add.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('الفترات المحاسبية', 'periods_manage.php')->add(__('Add'));
$values = [];

$form = Form::create('periods', $session->get('absoluteURL').'/modules/TawasulFinance/periods_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$row = $form->addRow(); $row->addLabel('tawasulFinanceFiscalYearID', 'السنة المالية'); $row->addSelect('tawasulFinanceFiscalYearID')->fromQuery($pdo, "SELECT tawasulFinanceFiscalYearID AS value, name AS name FROM tawasulFinanceFiscalYear ORDER BY name")->placeholder()->selected($values['tawasulFinanceFiscalYearID'] ?? '');
$row = $form->addRow(); $row->addLabel('name', 'الاسم'); $row->addTextField('name')->maxLength(150)->setValue($values['name'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('startDate', 'من'); $row->addDate('startDate')->setValue(isset($values['startDate']) ? \TawasulOS\Services\Format::date($values['startDate']) : '');
$row = $form->addRow(); $row->addLabel('endDate', 'إلى'); $row->addDate('endDate')->setValue(isset($values['endDate']) ? \TawasulOS\Services\Format::date($values['endDate']) : '');
$row = $form->addRow(); $row->addLabel('status', 'الحالة'); $row->addSelect('status')->fromArray(['Open' => 'مفتوحة', 'Closed' => 'مغلقة'])->selected($values['status'] ?? '')->required();
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo '<div class="sa-rtl">'.$form->getOutput().'</div>';
