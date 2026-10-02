<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/assets_add.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('الأصول الثابتة', 'assets_manage.php')->add(__('Add'));
$values = [];

$form = Form::create('assets', $session->get('absoluteURL').'/modules/TawasulFinance/assets_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$row = $form->addRow(); $row->addLabel('name', 'الأصل'); $row->addTextField('name')->maxLength(150)->setValue($values['name'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('acquisitionDate', 'تاريخ الشراء'); $row->addDate('acquisitionDate')->setValue(isset($values['acquisitionDate']) ? \TawasulOS\Services\Format::date($values['acquisitionDate']) : '');
$row = $form->addRow(); $row->addLabel('cost', 'التكلفة'); $row->addNumber('cost')->decimalPlaces(2)->setValue($values['cost'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('salvageValue', 'القيمة التخريدية'); $row->addNumber('salvageValue')->decimalPlaces(2)->setValue($values['salvageValue'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('usefulLifeYears', 'العمر الإنتاجي (سنوات)'); $row->addNumber('usefulLifeYears')->decimalPlaces(2)->setValue($values['usefulLifeYears'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('method', 'طريقة الإهلاك'); $row->addSelect('method')->fromArray(['StraightLine' => 'قسط ثابت', 'DecliningBalance' => 'قسط متناقص'])->selected($values['method'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('tawasulFinanceAssetAccountID', 'حساب الأصل'); $row->addSelect('tawasulFinanceAssetAccountID')->fromArray(saAccountOptions($pdo))->placeholder()->selected($values['tawasulFinanceAssetAccountID'] ?? '');
$row = $form->addRow(); $row->addLabel('tawasulFinanceAccumDepAccountID', 'حساب مجمع الإهلاك'); $row->addSelect('tawasulFinanceAccumDepAccountID')->fromArray(saAccountOptions($pdo))->placeholder()->selected($values['tawasulFinanceAccumDepAccountID'] ?? '');
$row = $form->addRow(); $row->addLabel('tawasulFinanceExpenseAccountID', 'حساب مصروف الإهلاك'); $row->addSelect('tawasulFinanceExpenseAccountID')->fromArray(saAccountOptions($pdo))->placeholder()->selected($values['tawasulFinanceExpenseAccountID'] ?? '');
$row = $form->addRow(); $row->addLabel('tawasulFinanceCostCenterID', 'مركز التكلفة'); $row->addSelect('tawasulFinanceCostCenterID')->fromQuery($pdo, "SELECT tawasulFinanceCostCenterID AS value, name AS name FROM tawasulFinanceCostCenter ORDER BY name")->placeholder()->selected($values['tawasulFinanceCostCenterID'] ?? '');
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo '<div class="sa-rtl">'.$form->getOutput().'</div>';
