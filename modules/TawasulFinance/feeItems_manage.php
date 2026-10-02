<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feeItems_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('بنود الرسوم');
saRtl();
$accs = saAccountOptions($pdo);
$rows = $pdo->select('SELECT t.* FROM tawasulFinanceFeeItem t  ORDER BY t.tawasulFinanceFeeItemID DESC')->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('feeItems', array_keys($rows[0] ?? []), $rows);

$table = DataTable::create('feeItems');
$table->setTitle('بنود الرسوم');
$table->addHeaderAction('add', __('Add'))->setURL('/modules/TawasulFinance/feeItems_add.php')->displayLabel();
$table->addColumn('name', 'البند');
$table->addColumn('category', 'الفئة')->format(fn($r) => (['Registration' => 'تسجيل', 'Tuition' => 'دراسية', 'Transport' => 'نقل', 'Uniform' => 'زي', 'Activity' => 'أنشطة', 'Other' => 'أخرى'])[$r['category']] ?? $r['category']);
$table->addColumn('tawasulFinanceRevenueAccountID', 'حساب الإيراد')->format(fn($r) => $accs[$r['tawasulFinanceRevenueAccountID']] ?? '');
$table->addColumn('defaultAmount', 'المبلغ الافتراضي')->format(fn($r) => saMoney($container, $r['defaultAmount']));
$table->addActionColumn()->addParam('id')->format(function ($r, $actions) {
    $actions->addAction('edit', __('Edit'))->setURL('/modules/TawasulFinance/feeItems_edit.php');
    $actions->addAction('delete', __('Delete'))->setURL('/modules/TawasulFinance/feeItems_delete.php');
});
echo '<div class="sa-rtl">';
saExportLinks('/modules/TawasulFinance/feeItems_manage.php');
echo $table->render(array_map(fn($r) => $r + ['id' => $r['tawasulFinanceFeeItemID']], $rows));
echo '</div>';
