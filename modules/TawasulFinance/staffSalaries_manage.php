<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/staffSalaries_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('رواتب الموظفين');
saRtl();
$accs = saAccountOptions($pdo);
$rows = $pdo->select('SELECT t.*, CONCAT(j0.surname, " ", j0.preferredName) AS tawasulPersonID_label, j1.name AS salaryComponentID_label, j3.name AS costCenterID_label FROM tawasulFinanceStaffSalary t LEFT JOIN tawasulPerson j0 ON j0.tawasulPersonID=t.tawasulPersonID LEFT JOIN tawasulFinanceSalaryComponent j1 ON j1.tawasulFinanceSalaryComponentID=t.tawasulFinanceSalaryComponentID LEFT JOIN tawasulFinanceCostCenter j3 ON j3.tawasulFinanceCostCenterID=t.tawasulFinanceCostCenterID ORDER BY t.tawasulFinanceStaffSalaryID DESC')->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('staffSalaries', array_keys($rows[0] ?? []), $rows);

$table = DataTable::create('staffSalaries');
$table->setTitle('رواتب الموظفين');
$table->addHeaderAction('add', __('Add'))->setURL('/modules/TawasulFinance/staffSalaries_add.php')->displayLabel();
$table->addColumn('tawasulPersonID_label', 'الموظف');
$table->addColumn('salaryComponentID_label', 'المكون');
$table->addColumn('amount', 'المبلغ الشهري')->format(fn($r) => saMoney($container, $r['amount']));
$table->addColumn('costCenterID_label', 'مركز التكلفة');
$table->addActionColumn()->addParam('id')->format(function ($r, $actions) {
    $actions->addAction('edit', __('Edit'))->setURL('/modules/TawasulFinance/staffSalaries_edit.php');
    $actions->addAction('delete', __('Delete'))->setURL('/modules/TawasulFinance/staffSalaries_delete.php');
});
echo '<div class="sa-rtl">';
saExportLinks('/modules/TawasulFinance/staffSalaries_manage.php');
echo $table->render(array_map(fn($r) => $r + ['id' => $r['tawasulFinanceStaffSalaryID']], $rows));
echo '</div>';
