<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/staffSalaries_add.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('رواتب الموظفين', 'staffSalaries_manage.php')->add(__('Add'));
$values = [];

$form = Form::create('staffSalaries', $session->get('absoluteURL').'/modules/TawasulFinance/staffSalaries_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$row = $form->addRow(); $row->addLabel('tawasulPersonID', 'الموظف'); $row->addSelect('tawasulPersonID')->fromQuery($pdo, 'SELECT tawasulPersonID AS value, CONCAT(surname, " ", preferredName) AS name FROM tawasulPerson WHERE status=\'Full\' ORDER BY name')->placeholder()->selected($values['tawasulPersonID'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('tawasulFinanceSalaryComponentID', 'المكون'); $row->addSelect('tawasulFinanceSalaryComponentID')->fromQuery($pdo, "SELECT tawasulFinanceSalaryComponentID AS value, name AS name FROM tawasulFinanceSalaryComponent ORDER BY name")->placeholder()->selected($values['tawasulFinanceSalaryComponentID'] ?? '');
$row = $form->addRow(); $row->addLabel('amount', 'المبلغ الشهري'); $row->addNumber('amount')->decimalPlaces(2)->setValue($values['amount'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('tawasulFinanceCostCenterID', 'مركز التكلفة'); $row->addSelect('tawasulFinanceCostCenterID')->fromQuery($pdo, "SELECT tawasulFinanceCostCenterID AS value, name AS name FROM tawasulFinanceCostCenter ORDER BY name")->placeholder()->selected($values['tawasulFinanceCostCenterID'] ?? '');
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo '<div class="sa-rtl">'.$form->getOutput().'</div>';
