<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feePlanItems_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('بنود خطط الرسوم');
saRtl();
$accs = saAccountOptions($pdo);
$rows = $pdo->select('SELECT t.*, j0.name AS feePlanID_label, j1.name AS feeItemID_label FROM tawasulFinanceFeePlanItem t LEFT JOIN tawasulFinanceFeePlan j0 ON j0.tawasulFinanceFeePlanID=t.tawasulFinanceFeePlanID LEFT JOIN tawasulFinanceFeeItem j1 ON j1.tawasulFinanceFeeItemID=t.tawasulFinanceFeeItemID ORDER BY t.tawasulFinanceFeePlanItemID DESC')->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('feePlanItems', array_keys($rows[0] ?? []), $rows);

$table = DataTable::create('feePlanItems');
$table->setTitle('بنود خطط الرسوم');
$table->addHeaderAction('add', __('Add'))->setURL('/modules/TawasulFinance/feePlanItems_add.php')->displayLabel();
$table->addColumn('feePlanID_label', 'الخطة');
$table->addColumn('feeItemID_label', 'البند');
$table->addColumn('amount', 'المبلغ')->format(fn($r) => saMoney($container, $r['amount']));
$table->addActionColumn()->addParam('id')->format(function ($r, $actions) {
    $actions->addAction('edit', __('Edit'))->setURL('/modules/TawasulFinance/feePlanItems_edit.php');
    $actions->addAction('delete', __('Delete'))->setURL('/modules/TawasulFinance/feePlanItems_delete.php');
});
echo '<div class="sa-rtl">';
saExportLinks('/modules/TawasulFinance/feePlanItems_manage.php');
echo $table->render(array_map(fn($r) => $r + ['id' => $r['tawasulFinanceFeePlanItemID']], $rows));
echo '</div>';
