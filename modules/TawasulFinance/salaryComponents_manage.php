<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/salaryComponents_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('مكونات الراتب');
saRtl();
$accs = saAccountOptions($pdo);
$rows = $pdo->select('SELECT t.* FROM tawasulFinanceSalaryComponent t  ORDER BY t.tawasulFinanceSalaryComponentID DESC')->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('salaryComponents', array_keys($rows[0] ?? []), $rows);

$table = DataTable::create('salaryComponents');
$table->setTitle('مكونات الراتب');
$table->addHeaderAction('add', __('Add'))->setURL('/modules/TawasulFinance/salaryComponents_add.php')->displayLabel();
$table->addColumn('name', 'المكون');
$table->addColumn('type', 'النوع')->format(fn($r) => (['Earning' => 'استحقاق', 'Deduction' => 'استقطاع', 'Advance' => 'سلفة'])[$r['type']] ?? $r['type']);
$table->addColumn('tawasulFinanceAccountID', 'الحساب')->format(fn($r) => $accs[$r['tawasulFinanceAccountID']] ?? '');
$table->addActionColumn()->addParam('id')->format(function ($r, $actions) {
    $actions->addAction('edit', __('Edit'))->setURL('/modules/TawasulFinance/salaryComponents_edit.php');
    $actions->addAction('delete', __('Delete'))->setURL('/modules/TawasulFinance/salaryComponents_delete.php');
});
echo '<div class="sa-rtl">';
saExportLinks('/modules/TawasulFinance/salaryComponents_manage.php');
echo $table->render(array_map(fn($r) => $r + ['id' => $r['tawasulFinanceSalaryComponentID']], $rows));
echo '</div>';
