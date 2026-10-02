<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/studentDiscounts_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/studentDiscounts_add.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['tawasulPersonID' => ($_POST['tawasulPersonID'] ?? '') === '' ? null : $_POST['tawasulPersonID'], 'tawasulFinanceDiscountID' => ($_POST['tawasulFinanceDiscountID'] ?? '') === '' ? null : $_POST['tawasulFinanceDiscountID'], 'tawasulSchoolYearID' => ($_POST['tawasulSchoolYearID'] ?? '') === '' ? null : $_POST['tawasulSchoolYearID'], 'tawasulPersonIDApprover' => ($_POST['tawasulPersonIDApprover'] ?? '') === '' ? null : $_POST['tawasulPersonIDApprover']];

try {
    $id = $pdo->insert('INSERT INTO tawasulFinanceStudentDiscount (tawasulPersonID,tawasulFinanceDiscountID,tawasulSchoolYearID,tawasulPersonIDApprover) VALUES (:tawasulPersonID,:tawasulFinanceDiscountID,:tawasulSchoolYearID,:tawasulPersonIDApprover)', $data);
    saLedger($container)->audit('StudentDiscount', $id, 'add', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
