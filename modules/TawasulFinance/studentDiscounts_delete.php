<?php
use TawasulOS\Forms\Form;
use TawasulOS\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/studentDiscounts_delete.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$form = \TawasulOS\Forms\Prefab\DeleteForm::createForm($session->get('absoluteURL').'/modules/TawasulFinance/studentDiscounts_deleteProcess.php?id='.urlencode($_GET['id'] ?? ''));
echo $form->getOutput();
