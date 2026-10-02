<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/studentDiscounts_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/studentDiscounts_edit.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['tawasulPersonID' => ($_POST['tawasulPersonID'] ?? '') === '' ? null : $_POST['tawasulPersonID'], 'tawasulFinanceDiscountID' => ($_POST['tawasulFinanceDiscountID'] ?? '') === '' ? null : $_POST['tawasulFinanceDiscountID'], 'tawasulSchoolYearID' => ($_POST['tawasulSchoolYearID'] ?? '') === '' ? null : $_POST['tawasulSchoolYearID'], 'tawasulPersonIDApprover' => ($_POST['tawasulPersonIDApprover'] ?? '') === '' ? null : $_POST['tawasulPersonIDApprover']];

try {
    $id = $_GET['id'] ?? '';
$pdo->update('UPDATE tawasulFinanceStudentDiscount SET tawasulPersonID=:tawasulPersonID, tawasulFinanceDiscountID=:tawasulFinanceDiscountID, tawasulSchoolYearID=:tawasulSchoolYearID, tawasulPersonIDApprover=:tawasulPersonIDApprover WHERE tawasulFinanceStudentDiscountID=:id', $data + ['id' => $id]);
    saLedger($container)->audit('StudentDiscount', $id, 'edit', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
