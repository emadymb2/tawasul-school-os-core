<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/report_tax.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('التقرير الضريبي والزكوي');
saRtl();
echo '<div class="sa-rtl"><h2>التقرير الضريبي والزكوي</h2>';
echo '<p>'.nl2br(htmlspecialchars(saSetting($container, 'schoolHeader', $session->get('organisationName')))).'</p>';
$L = saLedger($container);

[$from, $to] = saRange();
saFilter('/modules/TawasulFinance/report_tax.php', $from, $to);
$taxAcc = $L->accountIDByCode(saSetting($container, 'taxAccountCode', '2104'));
$input = (float)$pdo->selectOne("SELECT COALESCE(SUM(taxAmount),0) FROM tawasulFinancePurchaseBill WHERE status<>'Cancelled' AND date BETWEEN :f AND :t", ['f' => $from, 't' => $to]);
$bal = (float)$pdo->selectOne("SELECT COALESCE(SUM(l.credit-l.debit),0) FROM tawasulFinanceJournalLine l JOIN tawasulFinanceJournalEntry e USING (tawasulFinanceJournalEntryID) WHERE l.tawasulFinanceAccountID=:a AND e.status<>'Draft' AND e.date BETWEEN :f AND :t", ['a' => $taxAcc, 'f' => $from, 't' => $to]);
$rev = 0; foreach ($L->balances($from, $to) as $r) if ($r['type'] == 'Revenue') $rev += Ledger::natural($r);
$rate = (float)saSetting($container, 'taxRate', 15);
$eq = 0; foreach ($L->balances(null, $to) as $r) if ($r['type'] == 'Equity') $eq += Ledger::natural($r);
$zr = (float)saSetting($container, 'zakatRate', 2.5);
$m = fn($v) => saMoney($container, $v);
echo saTable(['البند', 'المبلغ'], [['الإيرادات (إجمالي)', $m($rev)], ['ضريبة المخرجات المقدرة ('.$rate.'%) — قد يكون التعليم معفى حسب الدولة', $m($rev * $rate / 100)], ['ضريبة المدخلات (المشتريات)', $m($input)], ['صافي رصيد حساب الضريبة للفترة', $m($bal)],
    ['وعاء الزكاة التقديري (حقوق الملكية)', $m($eq)], ['الزكاة التقديرية ('.$zr.'%)', $m(max(0, $eq) * $zr / 100)]], [1]);
echo '<p><small>تقرير استرشادي؛ راجع الأنظمة الضريبية المحلية قبل الاعتماد.</small></p>';
echo '</div>';
