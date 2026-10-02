<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/accounts_add.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$page->breadcrumbs->add('دليل الحسابات', 'accounts_manage.php')->add(__('Add'));
$values = [];

$form = Form::create('accounts', $session->get('absoluteURL').'/modules/TawasulFinance/accounts_addProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$row = $form->addRow(); $row->addLabel('code', 'الكود'); $row->addTextField('code')->maxLength(150)->setValue($values['code'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('name', 'اسم الحساب'); $row->addTextField('name')->maxLength(150)->setValue($values['name'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('type', 'النوع'); $row->addSelect('type')->fromArray(['Asset' => 'أصول', 'Liability' => 'خصوم', 'Equity' => 'حقوق ملكية', 'Revenue' => 'إيرادات', 'Expense' => 'مصروفات'])->selected($values['type'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('parentAccountID', 'الحساب الأب'); $row->addSelect('parentAccountID')->fromArray(saAccountOptions($pdo))->placeholder()->selected($values['parentAccountID'] ?? '');
$row = $form->addRow(); $row->addLabel('isPosting', 'حساب تفصيلي (يقبل قيود)'); $row->addSelect('isPosting')->fromArray(['Y' => 'نعم', 'N' => 'لا'])->selected($values['isPosting'] ?? '')->required();
$row = $form->addRow(); $row->addLabel('active', 'نشط'); $row->addSelect('active')->fromArray(['Y' => 'نعم', 'N' => 'لا'])->selected($values['active'] ?? '')->required();
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo '<div class="sa-rtl">'.$form->getOutput().'</div>';
