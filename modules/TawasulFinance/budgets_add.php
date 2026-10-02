<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/budgets_add.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('الموازنة التقديرية', 'budgets_manage.php')->add(__('Add'));
$values = [];

$form = Form::create('budgets', $session->get('absoluteURL').'/modules/TawasulFinance/budgets_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$row = $form->addRow(); $row->addLabel('tawasulFinanceFiscalYearID', 'السنة المالية'); $row->addSelect('tawasulFinanceFiscalYearID')->fromQuery($pdo, "SELECT tawasulFinanceFiscalYearID AS value, name AS name FROM tawasulFinanceFiscalYear ORDER BY name")->placeholder()->selected($values['tawasulFinanceFiscalYearID'] ?? '');
$row = $form->addRow(); $row->addLabel('tawasulFinanceAccountID', 'الحساب'); $row->addSelect('tawasulFinanceAccountID')->fromArray(saAccountOptions($pdo))->placeholder()->selected($values['tawasulFinanceAccountID'] ?? '');
$row = $form->addRow(); $row->addLabel('tawasulFinanceCostCenterID', 'مركز التكلفة'); $row->addSelect('tawasulFinanceCostCenterID')->fromQuery($pdo, "SELECT tawasulFinanceCostCenterID AS value, name AS name FROM tawasulFinanceCostCenter ORDER BY name")->placeholder()->selected($values['tawasulFinanceCostCenterID'] ?? '');
$row = $form->addRow(); $row->addLabel('amount', 'المبلغ المخطط'); $row->addNumber('amount')->decimalPlaces(2)->setValue($values['amount'] ?? '')->required();
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo '<div class="sa-rtl">'.$form->getOutput().'</div>';
