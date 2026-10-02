<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/studentDiscounts_add.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('خصومات الطلاب', 'studentDiscounts_manage.php')->add(__('Add'));
$values = [];

$form = Form::create('studentDiscounts', $session->get('absoluteURL').'/modules/TawasulFinance/studentDiscounts_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$row = $form->addRow(); $row->addLabel('tawasulPersonID', 'الطالب'); $row->addSelect('tawasulPersonID')->fromQuery($pdo, 'SELECT tawasulPersonID AS value, CONCAT(surname, " ", preferredName) AS name FROM tawasulPerson WHERE status=\'Full\' ORDER BY name')->placeholder()->selected($values['tawasulPersonID'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('tawasulFinanceDiscountID', 'الخصم'); $row->addSelect('tawasulFinanceDiscountID')->fromQuery($pdo, "SELECT tawasulFinanceDiscountID AS value, name AS name FROM tawasulFinanceDiscount ORDER BY name")->placeholder()->selected($values['tawasulFinanceDiscountID'] ?? '');
$row = $form->addRow(); $row->addLabel('tawasulSchoolYearID', 'السنة الدراسية'); $row->addSelect('tawasulSchoolYearID')->fromQuery($pdo, 'SELECT tawasulSchoolYearID AS value, name AS name FROM tawasulSchoolYear ORDER BY name')->placeholder()->selected($values['tawasulSchoolYearID'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('tawasulPersonIDApprover', 'اعتمده'); $row->addSelect('tawasulPersonIDApprover')->fromQuery($pdo, 'SELECT tawasulPersonID AS value, CONCAT(surname, " ", preferredName) AS name FROM tawasulPerson WHERE status=\'Full\' ORDER BY name')->placeholder()->selected($values['tawasulPersonIDApprover'] ?? '')->required();
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo '<div class="sa-rtl">'.$form->getOutput().'</div>';
