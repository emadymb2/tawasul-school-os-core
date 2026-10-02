<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/cashAccounts_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('الصناديق والبنوك');
saRtl();
$accs = saAccountOptions($pdo);
$rows = $pdo->select('SELECT t.* FROM tawasulFinanceCashAccount t  ORDER BY t.tawasulFinanceCashAccountID DESC')->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('cashAccounts', array_keys($rows[0] ?? []), $rows);

$table = DataTable::create('cashAccounts');
$table->setTitle('الصناديق والبنوك');
$table->addHeaderAction('add', __('Add'))->setURL('/modules/TawasulFinance/cashAccounts_add.php')->displayLabel();
$table->addColumn('name', 'الاسم');
$table->addColumn('type', 'النوع')->format(fn($r) => (['Cash' => 'صندوق', 'Bank' => 'بنك'])[$r['type']] ?? $r['type']);
$table->addColumn('bankAccountNumber', 'رقم الحساب/IBAN');
$table->addColumn('tawasulFinanceAccountID', 'الحساب المحاسبي')->format(fn($r) => $accs[$r['tawasulFinanceAccountID']] ?? '');
$table->addActionColumn()->addParam('id')->format(function ($r, $actions) {
    $actions->addAction('edit', __('Edit'))->setURL('/modules/TawasulFinance/cashAccounts_edit.php');
    $actions->addAction('delete', __('Delete'))->setURL('/modules/TawasulFinance/cashAccounts_delete.php');
});
echo '<div class="sa-rtl">';
saExportLinks('/modules/TawasulFinance/cashAccounts_manage.php');
echo $table->render(array_map(fn($r) => $r + ['id' => $r['tawasulFinanceCashAccountID']], $rows));
echo '</div>';
