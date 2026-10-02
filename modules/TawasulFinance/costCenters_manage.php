<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/costCenters_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('مراكز التكلفة');
saRtl();
$accs = saAccountOptions($pdo);
$rows = $pdo->select('SELECT t.* FROM tawasulFinanceCostCenter t  ORDER BY t.code')->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('costCenters', array_keys($rows[0] ?? []), $rows);

$table = DataTable::create('costCenters');
$table->setTitle('مراكز التكلفة');
$table->addHeaderAction('add', __('Add'))->setURL('/modules/TawasulFinance/costCenters_add.php')->displayLabel();
$table->addColumn('code', 'الكود');
$table->addColumn('name', 'الاسم');
$table->addColumn('type', 'النوع')->format(fn($r) => (['Stage' => 'مرحلة', 'Grade' => 'صف', 'Activity' => 'نشاط', 'Department' => 'قسم'])[$r['type']] ?? $r['type']);
$table->addActionColumn()->addParam('id')->format(function ($r, $actions) {
    $actions->addAction('edit', __('Edit'))->setURL('/modules/TawasulFinance/costCenters_edit.php');
    $actions->addAction('delete', __('Delete'))->setURL('/modules/TawasulFinance/costCenters_delete.php');
});
echo '<div class="sa-rtl">';
saExportLinks('/modules/TawasulFinance/costCenters_manage.php');
echo $table->render(array_map(fn($r) => $r + ['id' => $r['tawasulFinanceCostCenterID']], $rows));
echo '</div>';
