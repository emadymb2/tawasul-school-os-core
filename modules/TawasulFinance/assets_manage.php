<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/assets_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('الأصول الثابتة');
saRtl();
$accs = saAccountOptions($pdo);
$rows = $pdo->select('SELECT t.*, j9.name AS costCenterID_label FROM tawasulFinanceAsset t LEFT JOIN tawasulFinanceCostCenter j9 ON j9.tawasulFinanceCostCenterID=t.tawasulFinanceCostCenterID ORDER BY t.tawasulFinanceAssetID DESC')->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('assets', array_keys($rows[0] ?? []), $rows);

$table = DataTable::create('assets');
$table->setTitle('الأصول الثابتة');
$table->addHeaderAction('add', __('Add'))->setURL('/modules/TawasulFinance/assets_add.php')->displayLabel();
$table->addColumn('name', 'الأصل');
$table->addColumn('acquisitionDate', 'تاريخ الشراء');
$table->addColumn('cost', 'التكلفة')->format(fn($r) => saMoney($container, $r['cost']));
$table->addColumn('salvageValue', 'القيمة التخريدية')->format(fn($r) => saMoney($container, $r['salvageValue']));
$table->addColumn('usefulLifeYears', 'العمر الإنتاجي (سنوات)')->format(fn($r) => saMoney($container, $r['usefulLifeYears']));
$table->addColumn('method', 'طريقة الإهلاك')->format(fn($r) => (['StraightLine' => 'قسط ثابت', 'DecliningBalance' => 'قسط متناقص'])[$r['method']] ?? $r['method']);
$table->addColumn('tawasulFinanceAssetAccountID', 'حساب الأصل')->format(fn($r) => $accs[$r['tawasulFinanceAssetAccountID']] ?? '');
$table->addColumn('tawasulFinanceAccumDepAccountID', 'حساب مجمع الإهلاك')->format(fn($r) => $accs[$r['tawasulFinanceAccumDepAccountID']] ?? '');
$table->addColumn('tawasulFinanceExpenseAccountID', 'حساب مصروف الإهلاك')->format(fn($r) => $accs[$r['tawasulFinanceExpenseAccountID']] ?? '');
$table->addColumn('costCenterID_label', 'مركز التكلفة');
$table->addActionColumn()->addParam('id')->format(function ($r, $actions) {
    $actions->addAction('edit', __('Edit'))->setURL('/modules/TawasulFinance/assets_edit.php');
    $actions->addAction('delete', __('Delete'))->setURL('/modules/TawasulFinance/assets_delete.php');
});
echo '<div class="sa-rtl">';
saExportLinks('/modules/TawasulFinance/assets_manage.php');
echo $table->render(array_map(fn($r) => $r + ['id' => $r['tawasulFinanceAssetID']], $rows));
echo '</div>';
