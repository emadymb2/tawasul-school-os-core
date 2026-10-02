<?php
require_once __DIR__ . '/../../tawasul.php';
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/assets_manage.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/assets_add.php')) {
    header("Location: {$URL}&return=error0"); exit;
}
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);

$data = ['name' => ($_POST['name'] ?? '') === '' ? null : $_POST['name'], 'acquisitionDate' => ($_POST['acquisitionDate'] ?? '') === '' ? null : $_POST['acquisitionDate'], 'cost' => ($_POST['cost'] ?? '') === '' ? null : $_POST['cost'], 'salvageValue' => ($_POST['salvageValue'] ?? '') === '' ? null : $_POST['salvageValue'], 'usefulLifeYears' => ($_POST['usefulLifeYears'] ?? '') === '' ? null : $_POST['usefulLifeYears'], 'method' => ($_POST['method'] ?? '') === '' ? null : $_POST['method'], 'tawasulFinanceAssetAccountID' => ($_POST['tawasulFinanceAssetAccountID'] ?? '') === '' ? null : $_POST['tawasulFinanceAssetAccountID'], 'tawasulFinanceAccumDepAccountID' => ($_POST['tawasulFinanceAccumDepAccountID'] ?? '') === '' ? null : $_POST['tawasulFinanceAccumDepAccountID'], 'tawasulFinanceExpenseAccountID' => ($_POST['tawasulFinanceExpenseAccountID'] ?? '') === '' ? null : $_POST['tawasulFinanceExpenseAccountID'], 'tawasulFinanceCostCenterID' => ($_POST['tawasulFinanceCostCenterID'] ?? '') === '' ? null : $_POST['tawasulFinanceCostCenterID']];
if (!empty($data['acquisitionDate'])) $data['acquisitionDate'] = \TawasulOS\Services\Format::dateConvert($data['acquisitionDate']);
try {
    $id = $pdo->insert('INSERT INTO tawasulFinanceAsset (name,acquisitionDate,cost,salvageValue,usefulLifeYears,method,tawasulFinanceAssetAccountID,tawasulFinanceAccumDepAccountID,tawasulFinanceExpenseAccountID,tawasulFinanceCostCenterID) VALUES (:name,:acquisitionDate,:cost,:salvageValue,:usefulLifeYears,:method,:tawasulFinanceAssetAccountID,:tawasulFinanceAccumDepAccountID,:tawasulFinanceExpenseAccountID,:tawasulFinanceCostCenterID)', $data);
    saLedger($container)->audit('Asset', $id, 'add', $data);
} catch (\Throwable $e) {
    header("Location: {$URL}&return=error2"); exit;
}
header("Location: {$URL}&return=success0");
