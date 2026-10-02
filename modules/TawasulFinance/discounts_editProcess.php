<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/discounts_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/discounts_edit.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'kind' => ($_POST['kind'] ?? '') === '' ? null : $_POST['kind'], 'method' => ($_POST['method'] ?? '') === '' ? null : $_POST['method'], 'value' => ($_POST['value'] ?? '') === '' ? null : $_POST['value'], 'tawasulFinanceExpenseAccountID' => ($_POST['tawasulFinanceExpenseAccountID'] ?? '') === '' ? null : $_POST['tawasulFinanceExpenseAccountID']];

try {
    $id = $_GET['id'] ?? '';
$pdo->update('UPDATE tawasulFinanceDiscount SET name=:name, kind=:kind, method=:method, value=:value, tawasulFinanceExpenseAccountID=:tawasulFinanceExpenseAccountID WHERE tawasulFinanceDiscountID=:id', $data + ['id' => $id]);
    saLedger($container)->audit('Discount', $id, 'edit', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
