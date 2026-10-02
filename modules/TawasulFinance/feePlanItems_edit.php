<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/feePlanItems_edit.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('بنود خطط الرسوم', 'feePlanItems_manage.php')->add(__('Edit'));
$values = $pdo->select('SELECT * FROM tawasulFinanceFeePlanItem WHERE tawasulFinanceFeePlanItemID=:id', ['id' => $_GET['id'] ?? ''])->fetch();
if (!$values) { $page->addError(__('The specified record cannot be found.')); return; }

$form = Form::create('feePlanItems', $session->get('absoluteURL').'/modules/TawasulFinance/feePlanItems_editProcess.php?id='.$values['tawasulFinanceFeePlanItemID']);
$form->addHiddenValue('address', $session->get('address'));
$row = $form->addRow(); $row->addLabel('tawasulFinanceFeePlanID', 'الخطة'); $row->addSelect('tawasulFinanceFeePlanID')->fromQuery($pdo, "SELECT tawasulFinanceFeePlanID AS value, name AS name FROM tawasulFinanceFeePlan ORDER BY name")->placeholder()->selected($values['tawasulFinanceFeePlanID'] ?? '');
$row = $form->addRow(); $row->addLabel('tawasulFinanceFeeItemID', 'البند'); $row->addSelect('tawasulFinanceFeeItemID')->fromQuery($pdo, "SELECT tawasulFinanceFeeItemID AS value, name AS name FROM tawasulFinanceFeeItem ORDER BY name")->placeholder()->selected($values['tawasulFinanceFeeItemID'] ?? '');
$row = $form->addRow(); $row->addLabel('amount', 'المبلغ'); $row->addNumber('amount')->decimalPlaces(2)->setValue($values['amount'] ?? '')->required();
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo '<div class="sa-rtl">'.$form->getOutput().'</div>';
