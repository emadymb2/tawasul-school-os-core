<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feePlans_add.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('خطط الرسوم', 'feePlans_manage.php')->add(__('Add'));
$values = [];

$form = Form::create('feePlans', $session->get('absoluteURL').'/modules/TawasulFinance/feePlans_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$row = $form->addRow(); $row->addLabel('name', 'اسم الخطة'); $row->addTextField('name')->maxLength(150)->setValue($values['name'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('tawasulSchoolYearID', 'السنة الدراسية'); $row->addSelect('tawasulSchoolYearID')->fromQuery($pdo, 'SELECT tawasulSchoolYearID AS value, name AS name FROM tawasulSchoolYear ORDER BY name')->placeholder()->selected($values['tawasulSchoolYearID'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('tawasulYearGroupID', 'الصف'); $row->addSelect('tawasulYearGroupID')->fromQuery($pdo, 'SELECT tawasulYearGroupID AS value, name AS name FROM tawasulYearGroup ORDER BY name')->placeholder()->selected($values['tawasulYearGroupID'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('installments', 'عدد الأقساط'); $row->addNumber('installments')->decimalPlaces(2)->setValue($values['installments'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('tawasulFinanceCostCenterID', 'مركز التكلفة'); $row->addSelect('tawasulFinanceCostCenterID')->fromQuery($pdo, "SELECT tawasulFinanceCostCenterID AS value, name AS name FROM tawasulFinanceCostCenter ORDER BY name")->placeholder()->selected($values['tawasulFinanceCostCenterID'] ?? '');
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo '<div class="sa-rtl">'.$form->getOutput().'</div>';
