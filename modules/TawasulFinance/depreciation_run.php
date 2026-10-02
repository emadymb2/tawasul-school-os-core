<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/depreciation_run.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('تشغيل الإهلاك الشهري');
saRtl();
echo '<div class="sa-rtl"><h2>تشغيل الإهلاك الشهري</h2>';
if ($m = $session->get('saError')) { echo '<div class="message">'.htmlspecialchars($m).'</div>'; $session->forget('saError'); }

echo '<form method="post" class="noprint" action="'.$session->get('absoluteURL').'/modules/TawasulFinance/depreciation_runProcess.php">إهلاك شهر <input type="month" name="month" value="'.date('Y-m').'"> <input type="submit" value="احتساب وترحيل"></form>';
$rows = $pdo->select("SELECT a.*, COALESCE(SUM(d.amount),0) accum FROM tawasulFinanceAsset a LEFT JOIN tawasulFinanceDepreciation d ON d.tawasulFinanceAssetID=a.tawasulFinanceAssetID GROUP BY a.tawasulFinanceAssetID ORDER BY a.name")->fetchAll();
echo saTable(['الأصل', 'التكلفة', 'مجمع الإهلاك', 'القيمة الدفترية', 'قسط الشهر القادم'], array_map(fn($r) => [htmlspecialchars($r['name']), saMoney($container, $r['cost']), saMoney($container, $r['accum']), saMoney($container, $r['cost'] - $r['accum']),
    saMoney($container, \Tos\Module\TawasulFinance\Service\Depreciation::monthly($r, $r['accum']))], $rows), [1, 2, 3, 4]);
echo '</div>';
