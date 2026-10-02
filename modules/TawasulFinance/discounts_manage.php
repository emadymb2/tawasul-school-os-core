<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/discounts_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('الخصومات والمنح');
saRtl();
$accs = saAccountOptions($pdo);
$rows = $pdo->select('SELECT t.* FROM tawasulFinanceDiscount t  ORDER BY t.tawasulFinanceDiscountID DESC')->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('discounts', array_keys($rows[0] ?? []), $rows);

$table = DataTable::create('discounts');
$table->setTitle('الخصومات والمنح');
$table->addHeaderAction('add', __('Add'))->setURL('/modules/TawasulFinance/discounts_add.php')->displayLabel();
$table->addColumn('name', 'الاسم');
$table->addColumn('kind', 'النوع')->format(fn($r) => (['Sibling' => 'أخوة', 'Merit' => 'تفوق', 'Exemption' => 'إعفاء', 'Staff' => 'أبناء موظفين', 'Other' => 'أخرى'])[$r['kind']] ?? $r['kind']);
$table->addColumn('method', 'الطريقة')->format(fn($r) => (['Percent' => 'نسبة', 'Fixed' => 'مبلغ ثابت'])[$r['method']] ?? $r['method']);
$table->addColumn('value', 'القيمة')->format(fn($r) => saMoney($container, $r['value']));
$table->addColumn('tawasulFinanceExpenseAccountID', 'حساب الخصم')->format(fn($r) => $accs[$r['tawasulFinanceExpenseAccountID']] ?? '');
$table->addActionColumn()->addParam('id')->format(function ($r, $actions) {
    $actions->addAction('edit', __('Edit'))->setURL('/modules/TawasulFinance/discounts_edit.php');
    $actions->addAction('delete', __('Delete'))->setURL('/modules/TawasulFinance/discounts_delete.php');
});
echo '<div class="sa-rtl">';
saExportLinks('/modules/TawasulFinance/discounts_manage.php');
echo $table->render(array_map(fn($r) => $r + ['id' => $r['tawasulFinanceDiscountID']], $rows));
echo '</div>';
