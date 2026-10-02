<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/studentDiscounts_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('خصومات الطلاب');
saRtl();
$accs = saAccountOptions($pdo);
$rows = $pdo->select('SELECT t.*, CONCAT(j0.surname, " ", j0.preferredName) AS tawasulPersonID_label, j1.name AS discountID_label, j2.name AS tawasulSchoolYearID_label, CONCAT(j3.surname, " ", j3.preferredName) AS approvedByID_label FROM tawasulFinanceStudentDiscount t LEFT JOIN tawasulPerson j0 ON j0.tawasulPersonID=t.tawasulPersonID LEFT JOIN tawasulFinanceDiscount j1 ON j1.tawasulFinanceDiscountID=t.tawasulFinanceDiscountID LEFT JOIN tawasulSchoolYear j2 ON j2.tawasulSchoolYearID=t.tawasulSchoolYearID LEFT JOIN tawasulPerson j3 ON j3.tawasulPersonID=t.tawasulPersonIDApprover ORDER BY t.tawasulFinanceStudentDiscountID DESC')->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('studentDiscounts', array_keys($rows[0] ?? []), $rows);

$table = DataTable::create('studentDiscounts');
$table->setTitle('خصومات الطلاب');
$table->addHeaderAction('add', __('Add'))->setURL('/modules/TawasulFinance/studentDiscounts_add.php')->displayLabel();
$table->addColumn('tawasulPersonID_label', 'الطالب');
$table->addColumn('discountID_label', 'الخصم');
$table->addColumn('tawasulSchoolYearID_label', 'السنة الدراسية');
$table->addColumn('approvedByID_label', 'اعتمده');
$table->addActionColumn()->addParam('id')->format(function ($r, $actions) {
    $actions->addAction('edit', __('Edit'))->setURL('/modules/TawasulFinance/studentDiscounts_edit.php');
    $actions->addAction('delete', __('Delete'))->setURL('/modules/TawasulFinance/studentDiscounts_delete.php');
});
echo '<div class="sa-rtl">';
saExportLinks('/modules/TawasulFinance/studentDiscounts_manage.php');
echo $table->render(array_map(fn($r) => $r + ['id' => $r['tawasulFinanceStudentDiscountID']], $rows));
echo '</div>';
