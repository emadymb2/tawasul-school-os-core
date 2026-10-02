<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/vouchers_manage.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('سندات الصرف');
saRtl();
echo '<div class="sa-rtl"><h2>سندات الصرف</h2>';
if ($m = $session->get('saError')) { echo '<div class="message">'.htmlspecialchars($m).'</div>'; $session->forget('saError'); }

[$from, $to] = saRange();
saFilter('/modules/TawasulFinance/vouchers_manage.php', $from, $to);
echo '<a class="button noprint" href="'.$session->get('absoluteURL').'/index.php?q=/modules/TawasulFinance/vouchers_add.php">+ سند صرف</a>';
$rows = $pdo->select("SELECT v.*, c.name cash, b.billNumber, e.description FROM tawasulFinancePaymentVoucher v JOIN tawasulFinanceCashAccount c ON c.tawasulFinanceCashAccountID=v.tawasulFinanceCashAccountID
    LEFT JOIN tawasulFinancePurchaseBill b USING (tawasulFinancePurchaseBillID) LEFT JOIN tawasulFinanceJournalEntry e ON e.tawasulFinanceJournalEntryID=v.tawasulFinanceJournalEntryID
    WHERE v.date BETWEEN :f AND :t ORDER BY v.date DESC", ['f' => $from, 't' => $to])->fetchAll();
echo saTable(['الرقم', 'التاريخ', 'البيان', 'الفاتورة', 'الصندوق', 'المبلغ'], array_map(fn($r) => [$r['voucherNumber'], saDate($container, $r['date']), htmlspecialchars($r['description'] ?? ''), $r['billNumber'], $r['cash'], saMoney($container, $r['amount'])], $rows), [5]);
echo '</div>';
