<?php
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__ . '/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/invoices_generate.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('توليد فواتير الطلاب');
saRtl();
echo '<div class="sa-rtl"><h2>توليد فواتير الطلاب</h2>';
if ($m = $session->get('saError')) { echo '<div class="message">'.htmlspecialchars($m).'</div>'; $session->forget('saError'); }

// Raised for every invoicee that does not already have a live invoice against
// the schedule. The surviving invoice model issues one invoice per schedule
// run rather than a numbered series of installments, so there are no installment
// dates to collect here.
echo '<p>تُنشأ فاتورة لكل مستفيد ليس لديه فاتورة سارية لنفس جدول التحصيل، مع تطبيق خصومات الطالب وترحيل قيد الاستحقاق تلقائياً.</p>';
$schedules = $pdo->select("SELECT tawasulFinanceBillingScheduleID, CONCAT(name,' — ',COALESCE(invoiceIssueDate,'')) FROM tawasulFinanceBillingSchedule WHERE active='Y' ORDER BY name")->fetchKeyPair();
if (!$schedules) { echo '<p>لا توجد جداول تحصيل نشطة. أنشئ جدول تحصيل أولاً.</p></div>'; return; }
?>
<form method="post" action="<?= $session->get('absoluteURL') ?>/modules/TawasulFinance/invoices_generateProcess.php">
<input type="hidden" name="address" value="<?= $session->get('address') ?>">
<p>جدول التحصيل <select name="tawasulFinanceBillingScheduleID" required><?php foreach ($schedules as $k => $v) echo '<option value="'.$k.'">'.htmlspecialchars($v).'</option>'; ?></select>
تاريخ الإصدار <input type="date" name="invoiceIssueDate" value="<?= date('Y-m-d') ?>"></p>
<input type="submit" value="توليد الفواتير">
</form>
<?php
echo '</div>';
