<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/auditLog.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('سجل التدقيق');
saRtl();
echo '<div class="sa-rtl"><h2>سجل التدقيق</h2>';

[$from, $to] = saRange();
saFilter('/modules/TawasulFinance/auditLog.php', $from, $to);
$rows = $pdo->select("SELECT a.timestamp, a.tableName, a.recordID, a.action, CONCAT(p.preferredName,' ',p.surname) who, a.ip, a.data FROM tawasulFinanceAuditLog a LEFT JOIN tawasulPerson p USING (tawasulPersonID)
    WHERE DATE(a.timestamp) BETWEEN :f AND :t ORDER BY a.tawasulFinanceAuditLogID DESC LIMIT 1000", ['f' => $from, 't' => $to])->fetchAll();
if (($_GET['export'] ?? '') == 'csv') saExportCsv('audit', array_keys($rows[0] ?? []), $rows);
saExportLinks('/modules/TawasulFinance/auditLog.php&from='.$from.'&to='.$to);
echo saTable(['الوقت', 'الجدول', 'السجل', 'الإجراء', 'المستخدم', 'IP', 'البيانات'], array_map(fn($r) => [$r['timestamp'], $r['tableName'], $r['recordID'], $r['action'], htmlspecialchars($r['who'] ?? ''), $r['ip'], '<small>'.htmlspecialchars(mb_substr($r['data'] ?? '', 0, 200)).'</small>'], $rows));
echo '</div>';
