<?php
namespace Tos\Module\TawasulFinance\Service;

class Payroll
{
    public function __construct(protected Ledger $ledger, protected array $cfg) {}

    /** Build & post monthly payroll: Dr expenses / Cr deductions & advances / Cr salaries payable (or cash). */
    public function run(string $month, $tawasulFinanceCashAccountID = null)
    {
        $db = $this->ledger->db();
        if ($db->selectOne("SELECT COUNT(*) FROM tawasulFinancePayrollRun WHERE month=:m", ['m' => $month])) throw new LedgerException('تم تشغيل رواتب هذا الشهر مسبقاً');
        $rows = $db->select("SELECT s.tawasulPersonID, s.tawasulFinanceSalaryComponentID, s.amount, s.tawasulFinanceCostCenterID, c.type, c.tawasulFinanceAccountID, c.name
            FROM tawasulFinanceStaffSalary s JOIN tawasulFinanceSalaryComponent c ON c.tawasulFinanceSalaryComponentID=s.tawasulFinanceSalaryComponentID
            JOIN tawasulPerson p ON p.tawasulPersonID=s.tawasulPersonID WHERE p.status='Full'")->fetchAll();
        if (!$rows) throw new LedgerException('لا توجد رواتب معرفة');
        $earn = $ded = 0; $agg = [];
        foreach ($rows as $r) {
            $key = $r['tawasulFinanceAccountID'].'|'.$r['tawasulFinanceCostCenterID'].'|'.$r['type'];
            $agg[$key] = ($agg[$key] ?? 0) + $r['amount'];
            if ($r['type'] == 'Earning') $earn += $r['amount']; else $ded += $r['amount'];
        }
        $net = round($earn - $ded, 2);
        $date = date('Y-m-t', strtotime($month.'-01'));
        $id = $db->insert("INSERT INTO tawasulFinancePayrollRun (month,tawasulFinanceCashAccountID,totalEarnings,totalDeductions,netPay,status) VALUES (:m,:c,:e,:d,:n,'Posted')",
            ['m' => $month, 'c' => $tawasulFinanceCashAccountID ?: null, 'e' => $earn, 'd' => $ded, 'n' => $net]);
        foreach ($rows as $r) $db->insert("INSERT INTO tawasulFinancePayrollLine (tawasulFinancePayrollRunID,tawasulPersonID,tawasulFinanceSalaryComponentID,amount) VALUES (:r,:p,:c,:a)", ['r' => $id, 'p' => $r['tawasulPersonID'], 'c' => $r['tawasulFinanceSalaryComponentID'], 'a' => $r['amount']]);
        $lines = [];
        foreach ($agg as $k => $amt) {
            [$acc, $cc, $type] = explode('|', $k);
            $lines[] = $type == 'Earning' ? ['tawasulFinanceAccountID' => $acc, 'debit' => $amt, 'tawasulFinanceCostCenterID' => $cc ?: null] : ['tawasulFinanceAccountID' => $acc, 'credit' => $amt];
        }
        $credit = $tawasulFinanceCashAccountID ? $db->selectOne("SELECT tawasulFinanceAccountID FROM tawasulFinanceCashAccount WHERE tawasulFinanceCashAccountID=:c", ['c' => $tawasulFinanceCashAccountID])
            : $this->ledger->accountIDByCode($this->cfg['salariesPayableCode']);
        $lines[] = ['tawasulFinanceAccountID' => $credit, 'credit' => $net, 'memo' => 'صافي الرواتب'];
        $je = $this->ledger->post($date, 'مسير رواتب شهر '.$month, $lines, 'PAY', 'Payroll', $id);
        $db->update("UPDATE tawasulFinancePayrollRun SET tawasulFinanceJournalEntryID=:j WHERE tawasulFinancePayrollRunID=:i", ['j' => $je, 'i' => $id]);
        return $id;
    }
}
