<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/fiscalYears_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('السنوات المالية');
saRtl();
$accs = saAccountOptions($pdo);
$rows = $pdo->select('SELECT t.* FROM tawasulFinanceFiscalYear t  ORDER BY t.tawasulFinanceFiscalYearID DESC')->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('fiscalYears', array_keys($rows[0] ?? []), $rows);

$table = DataTable::create('fiscalYears');
$table->setTitle('السنوات المالية');
$table->addHeaderAction('add', __('Add'))->setURL('/modules/TawasulFinance/fiscalYears_add.php')->displayLabel();
$table->addColumn('name', 'الاسم');
$table->addColumn('firstDay', 'أول يوم');
$table->addColumn('lastDay', 'آخر يوم');
$table->addColumn('status', 'الحالة')->format(fn($r) => (['Open' => 'مفتوحة', 'Closed' => 'مغلقة'])[$r['status']] ?? $r['status']);
$table->addActionColumn()->addParam('id')->format(function ($r, $actions) {
    $actions->addAction('edit', __('Edit'))->setURL('/modules/TawasulFinance/fiscalYears_edit.php');
    $actions->addAction('delete', __('Delete'))->setURL('/modules/TawasulFinance/fiscalYears_delete.php');
});
echo '<div class="sa-rtl">';
saExportLinks('/modules/TawasulFinance/fiscalYears_manage.php');
echo $table->render(array_map(fn($r) => $r + ['id' => $r['tawasulFinanceFiscalYearID']], $rows));
echo '</div>';
