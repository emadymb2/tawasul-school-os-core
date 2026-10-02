<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/settings.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('إعدادات المحاسبة');
saRtl();
echo '<div class="sa-rtl"><h2>إعدادات المحاسبة</h2>';
$form = Form::create('settings', $session->get('absoluteURL').'/modules/TawasulFinance/settingsProcess.php');
$form->addHiddenValue('address', $session->get('address'));

foreach ($pdo->select("SELECT name, nameDisplay, value FROM tawasulSetting WHERE scope='School Accounting'")->fetchAll() as $s) {
    $row = $form->addRow(); $row->addLabel($s['name'], $s['nameDisplay']);
    if ($s['name'] == 'schoolHeader') $row->addTextArea($s['name'])->setValue($s['value'])->setRows(3);
    elseif ($s['name'] == 'showHijri') $row->addYesNo($s['name'])->selected($s['value']);
    elseif ($s['name'] == 'currencyPosition') $row->addSelect($s['name'])->fromArray(['before' => 'قبل المبلغ', 'after' => 'بعد المبلغ'])->selected($s['value']);
    else $row->addTextField($s['name'])->setValue($s['value']);
}
$row = $form->addRow(); $row->addFooter(); $row->addSubmit();
echo $form->getOutput();

echo '<h3>ترقيم المستندات</h3>'.saTable(['النوع', 'البادئة', 'الرقم التالي'], $pdo->select('SELECT * FROM tawasulFinanceSequence')->fetchAll());
echo '</div>';
