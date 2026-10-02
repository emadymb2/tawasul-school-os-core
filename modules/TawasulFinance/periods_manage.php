<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/periods_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('الفترات المحاسبية');
saRtl();
$accs = saAccountOptions($pdo);
$rows = $pdo->select('SELECT t.*, j0.name AS fiscalYearID_label FROM tawasulFinancePeriod t LEFT JOIN tawasulFinanceFiscalYear j0 ON j0.tawasulFinanceFiscalYearID=t.tawasulFinanceFiscalYearID ORDER BY t.tawasulFinancePeriodID DESC')->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('periods', array_keys($rows[0] ?? []), $rows);

$table = DataTable::create('periods');
$table->setTitle('الفترات المحاسبية');
$table->addHeaderAction('add', __('Add'))->setURL('/modules/TawasulFinance/periods_add.php')->displayLabel();
$table->addColumn('fiscalYearID_label', 'السنة المالية');
$table->addColumn('name', 'الاسم');
$table->addColumn('startDate', 'من');
$table->addColumn('endDate', 'إلى');
$table->addColumn('status', 'الحالة')->format(fn($r) => (['Open' => 'مفتوحة', 'Closed' => 'مغلقة'])[$r['status']] ?? $r['status']);
$table->addActionColumn()->addParam('id')->format(function ($r, $actions) {
    $actions->addAction('edit', __('Edit'))->setURL('/modules/TawasulFinance/periods_edit.php');
    $actions->addAction('delete', __('Delete'))->setURL('/modules/TawasulFinance/periods_delete.php');
});
echo '<div class="sa-rtl">';
saExportLinks('/modules/TawasulFinance/periods_manage.php');
echo $table->render(array_map(fn($r) => $r + ['id' => $r['tawasulFinancePeriodID']], $rows));
echo '</div>';
