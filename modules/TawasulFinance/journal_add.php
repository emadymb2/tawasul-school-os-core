<?php
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use Tos\Module\TawasulFinance\Service\Ledger;

require_once __DIR__.'/moduleFunctions.php';
if (!isActionAccessible($guid, $connection2, '/modules/TawasulFinance/journal_add.php')) { $page->addError(__('You do not have access to this action.')); return; }
$pdo = $container->get(\TawasulOS\Contracts\Database\Connection::class);
$page->breadcrumbs->add('قيد يومية جديد');
saRtl();
echo '<div class="sa-rtl"><h2>قيد يومية جديد</h2>';
if ($m = $session->get('saError')) { echo '<div class="message">'.htmlspecialchars($m).'</div>'; $session->forget('saError'); }
$ccs = $pdo->select('SELECT tawasulFinanceCostCenterID, name FROM tawasulFinanceCostCenter ORDER BY code')->fetchKeyPair();

$accs = saAccountOptions($pdo);
$accOpt = ''; foreach ($accs as $k => $v) $accOpt .= '<option value="'.$k.'">'.htmlspecialchars($v).'</option>';
$ccOpt = '<option value=""></option>'; foreach ($ccs as $k => $v) $ccOpt .= '<option value="'.$k.'">'.htmlspecialchars($v).'</option>';
?>
<form method="post" action="<?= $session->get('absoluteURL') ?>/modules/TawasulFinance/journal_addProcess.php">
<input type="hidden" name="address" value="<?= $session->get('address') ?>">
<p>التاريخ <input type="date" name="date" required value="<?= date('Y-m-d') ?>">
 البيان <input type="text" name="description" required style="width:40%">
 <label><input type="checkbox" name="draft" value="Y"> حفظ كمسودة</label>
 <label><input type="checkbox" name="isRecurring" value="Y"> متكرر</label>
 <select name="recurringFrequency"><option value="">—</option><option value="Monthly">شهري</option><option value="Quarterly">ربع سنوي</option><option value="Yearly">سنوي</option></select></p>
<table class="fullWidth" id="saLines"><tr class="head"><th>الحساب</th><th>مركز التكلفة</th><th>البيان</th><th>مدين</th><th>دائن</th></tr>
<?php for ($i = 0; $i < 6; $i++): ?>
<tr><td><select name="tawasulFinanceAccountID[]"><option value=""></option><?= $accOpt ?></select></td><td><select name="tawasulFinanceCostCenterID[]"><?= $ccOpt ?></select></td>
<td><input type="text" name="memo[]"></td><td><input class="sa-dr" type="number" step="0.01" min="0" name="debit[]"></td><td><input class="sa-cr" type="number" step="0.01" min="0" name="credit[]"></td></tr>
<?php endfor; ?>
<tr class="sa-tot"><td colspan="3">الفرق: <span id="saDiff"></span></td><td id="saDr">0</td><td id="saCr">0</td></tr></table>
<button type="button" onclick="var t=document.getElementById('saLines'),r=t.rows[1].cloneNode(true);r.querySelectorAll('input').forEach(i=>i.value='');t.rows[t.rows.length-1].before(r);">+ سطر</button>
<input type="submit" value="حفظ القيد">
</form>
<script>
document.addEventListener('input',function(){var s=c=>[...document.querySelectorAll(c)].reduce((a,i)=>a+(+i.value||0),0),d=s('.sa-dr'),k=s('.sa-cr');
document.getElementById('saDr').textContent=d.toFixed(2);document.getElementById('saCr').textContent=k.toFixed(2);var f=(d-k).toFixed(2);var e=document.getElementById('saDiff');e.textContent=f==0?'متوازن':f;e.style.color=f==0?'green':'red';});
</script>
<?php
echo '</div>';
