<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/bills_manage.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('فواتير المشتريات');
saRtl();
echo '<div class="sa-rtl"><h2>فواتير المشتريات</h2>';
if ($m = $session->get('saError')) { echo '<div class="message">'.htmlspecialchars($m).'</div>'; $session->forget('saError'); }

$a = $session->get('absoluteURL');
echo '<a class="button noprint" href="'.$a.'/index.php?q=/modules/TawasulFinance/bills_add.php">+ فاتورة مشتريات</a>';
$rows = $pdo->select("SELECT b.*, s.name supplier FROM tawasulFinancePurchaseBill b JOIN tawasulFinanceSupplier s ON s.tawasulFinanceSupplierID=b.supplierID ORDER BY b.date DESC LIMIT 500")->fetchAll();
echo saTable(['الرقم', 'التاريخ', 'المورد', 'البيان', 'المبلغ', 'الضريبة', 'المدفوع', 'الحالة', ''], array_map(fn($r) => [$r['billNumber'], saDate($container, $r['date']), htmlspecialchars($r['supplier']), htmlspecialchars($r['description'] ?? ''),
    saMoney($container, $r['amount']), saMoney($container, $r['taxAmount']), saMoney($container, $r['paidAmount']), $r['status'],
    $r['status'] != 'Paid' ? '<a href="'.$a.'/index.php?q=/modules/TawasulFinance/vouchers_add.php&bill='.$r['tawasulFinancePurchaseBillID'].'">سداد</a>' : ''], $rows), [4, 5, 6]);
echo '</div>';
