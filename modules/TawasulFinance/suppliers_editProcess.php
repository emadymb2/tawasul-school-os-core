<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/suppliers_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/suppliers_edit.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'phone' => ($_POST['phone'] ?? '') === '' ? null : $_POST['phone'], 'email' => ($_POST['email'] ?? '') === '' ? null : $_POST['email'], 'taxNumber' => ($_POST['taxNumber'] ?? '') === '' ? null : $_POST['taxNumber'], 'tawasulFinancePayableAccountID' => ($_POST['tawasulFinancePayableAccountID'] ?? '') === '' ? null : $_POST['tawasulFinancePayableAccountID']];

try {
    $id = $_GET['id'] ?? '';
$pdo->update('UPDATE tawasulFinanceSupplier SET name=:name, phone=:phone, email=:email, taxNumber=:taxNumber, tawasulFinancePayableAccountID=:tawasulFinancePayableAccountID WHERE tawasulFinanceSupplierID=:id', $data + ['id' => $id]);
    saLedger($container)->audit('Supplier', $id, 'edit', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
