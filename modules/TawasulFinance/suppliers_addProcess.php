<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/suppliers_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/suppliers_add.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'phone' => ($_POST['phone'] ?? '') === '' ? null : $_POST['phone'], 'email' => ($_POST['email'] ?? '') === '' ? null : $_POST['email'], 'taxNumber' => ($_POST['taxNumber'] ?? '') === '' ? null : $_POST['taxNumber'], 'tawasulFinancePayableAccountID' => ($_POST['tawasulFinancePayableAccountID'] ?? '') === '' ? null : $_POST['tawasulFinancePayableAccountID']];

try {
    $id = $pdo->insert('INSERT INTO tawasulFinanceSupplier (name,phone,email,taxNumber,tawasulFinancePayableAccountID) VALUES (:name,:phone,:email,:taxNumber,:tawasulFinancePayableAccountID)', $data);
    saLedger($container)->audit('Supplier', $id, 'add', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
