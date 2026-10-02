<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/studentDiscounts_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/studentDiscounts_delete.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$id = $_GET['id'] ?? '';
$pdo->delete('DELETE FROM tawasulFinanceStudentDiscount WHERE tawasulFinanceStudentDiscountID=:id', ['id' => $id]);
saLedger($container)->audit('StudentDiscount', $id, 'delete');
header("Location: {$URL}&return=success0");
