<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/suppliers_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('الموردون');
saRtl();
$accs = saAccountOptions($pdo);
$rows = $pdo->select('SELECT t.* FROM tawasulFinanceSupplier t  ORDER BY t.tawasulFinanceSupplierID DESC')->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('suppliers', array_keys($rows[0] ?? []), $rows);

$table = DataTable::create('suppliers');
$table->setTitle('الموردون');
$table->addHeaderAction('add', __('Add'))->setURL('/modules/TawasulFinance/suppliers_add.php')->displayLabel();
$table->addColumn('name', 'المورد');
$table->addColumn('phone', 'الهاتف');
$table->addColumn('email', 'البريد');
$table->addColumn('taxNumber', 'الرقم الضريبي');
$table->addColumn('tawasulFinancePayableAccountID', 'حساب الدائنين')->format(fn($r) => $accs[$r['tawasulFinancePayableAccountID']] ?? '');
$table->addActionColumn()->addParam('id')->format(function ($r, $actions) {
    $actions->addAction('edit', __('Edit'))->setURL('/modules/TawasulFinance/suppliers_edit.php');
    $actions->addAction('delete', __('Delete'))->setURL('/modules/TawasulFinance/suppliers_delete.php');
});
echo '<div class="sa-rtl">';
saExportLinks('/modules/TawasulFinance/suppliers_manage.php');
echo $table->render(array_map(fn($r) => $r + ['id' => $r['tawasulFinanceSupplierID']], $rows));
echo '</div>';
