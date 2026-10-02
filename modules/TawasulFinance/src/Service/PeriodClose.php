<?php
namespace Tos\Module\TawasulFinance\Service;

class PeriodClose
{
    public function __construct(protected Ledger $ledger, protected array $cfg) {}

    public function closePeriod($tawasulFinancePeriodID)
    {
        $db = $this->ledger->db();
        if ($db->selectOne("SELECT COUNT(*) FROM tawasulFinanceJournalEntry WHERE tawasulFinancePeriodID=:p AND status='Draft'", ['p' => $tawasulFinancePeriodID])) throw new LedgerException('يوجد قيود مسودة غير معتمدة في الفترة');
        $db->update("UPDATE tawasulFinancePeriod SET status='Closed' WHERE tawasulFinancePeriodID=:p", ['p' => $tawasulFinancePeriodID]);
        $this->ledger->audit('Period', $tawasulFinancePeriodID, 'close');
    }

    /** Year-end: close revenue & expense into retained earnings on the last day, then lock the year. */
    public function closeYear($tawasulFinanceFiscalYearID)
    {
        $db = $this->ledger->db();
        $fy = $db->select("SELECT * FROM tawasulFinanceFiscalYear WHERE tawasulFinanceFiscalYearID=:f", ['f' => $tawasulFinanceFiscalYearID])->fetch();
        $rows = $this->ledger->balances($fy['firstDay'], $fy['lastDay']);
        $lines = []; $net = 0;
        foreach ($rows as $r) {
            if (!in_array($r['type'], ['Revenue', 'Expense'])) continue;
            $bal = round($r['debit'] - $r['credit'], 2);
            if ($bal == 0) continue;
            $lines[] = $bal > 0 ? ['tawasulFinanceAccountID' => $r['id'], 'credit' => $bal] : ['tawasulFinanceAccountID' => $r['id'], 'debit' => -$bal];
            $net += $bal;
        }
        if ($lines) {
            $re = $this->ledger->accountIDByCode($this->cfg['retainedEarningsCode']);
            $lines[] = $net > 0 ? ['tawasulFinanceAccountID' => $re, 'debit' => $net] : ['tawasulFinanceAccountID' => $re, 'credit' => -$net];
            $this->ledger->post($fy['lastDay'], 'قيد إقفال السنة المالية '.$fy['name'], $lines, 'JV', 'YearClose', $tawasulFinanceFiscalYearID);
        }
        $db->update("UPDATE tawasulFinancePeriod SET status='Closed' WHERE tawasulFinanceFiscalYearID=:f", ['f' => $tawasulFinanceFiscalYearID]);
        $db->update("UPDATE tawasulFinanceFiscalYear SET status='Closed' WHERE tawasulFinanceFiscalYearID=:f", ['f' => $tawasulFinanceFiscalYearID]);
        $this->ledger->audit('FiscalYear', $tawasulFinanceFiscalYearID, 'close', ['net' => $net]);
    }
}
