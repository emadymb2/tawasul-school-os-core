<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feePlans_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('خطط الرسوم');
saRtl();
$accs = saAccountOptions($pdo);
$rows = $pdo->select('SELECT t.*, j1.name AS tawasulSchoolYearID_label, j2.name AS tawasulYearGroupID_label, j4.name AS costCenterID_label FROM tawasulFinanceFeePlan t LEFT JOIN tawasulSchoolYear j1 ON j1.tawasulSchoolYearID=t.tawasulSchoolYearID LEFT JOIN tawasulYearGroup j2 ON j2.tawasulYearGroupID=t.tawasulYearGroupID LEFT JOIN tawasulFinanceCostCenter j4 ON j4.tawasulFinanceCostCenterID=t.tawasulFinanceCostCenterID ORDER BY t.tawasulFinanceFeePlanID DESC')->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('feePlans', array_keys($rows[0] ?? []), $rows);

$table = DataTable::create('feePlans');
$table->setTitle('خطط الرسوم');
$table->addHeaderAction('add', __('Add'))->setURL('/modules/TawasulFinance/feePlans_add.php')->displayLabel();
$table->addColumn('name', 'اسم الخطة');
$table->addColumn('tawasulSchoolYearID_label', 'السنة الدراسية');
$table->addColumn('tawasulYearGroupID_label', 'الصف');
$table->addColumn('installments', 'عدد الأقساط')->format(fn($r) => saMoney($container, $r['installments']));
$table->addColumn('costCenterID_label', 'مركز التكلفة');
$table->addActionColumn()->addParam('id')->format(function ($r, $actions) {
    $actions->addAction('edit', __('Edit'))->setURL('/modules/TawasulFinance/feePlans_edit.php');
    $actions->addAction('delete', __('Delete'))->setURL('/modules/TawasulFinance/feePlans_delete.php');
});
echo '<div class="sa-rtl">';
saExportLinks('/modules/TawasulFinance/feePlans_manage.php');
echo $table->render(array_map(fn($r) => $r + ['id' => $r['tawasulFinanceFeePlanID']], $rows));
echo '</div>';
