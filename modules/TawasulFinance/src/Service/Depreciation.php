<?php
namespace Tos\Module\TawasulFinance\Service;

class Depreciation
{
    public function __construct(protected Ledger $ledger) {}

    public static function monthly(array $a, float $accumulated): float
    {
        $cost = (float)$a['cost']; $salv = (float)$a['salvageValue']; $life = max(1, (int)$a['usefulLifeYears']);
        $remaining = max(0, $cost - $salv - $accumulated);
        if ($remaining <= 0) return 0;
        $amt = $a['method'] == 'DecliningBalance'
            ? ($cost - $accumulated) * (2 / $life) / 12      // double-declining
            : ($cost - $salv) / $life / 12;                  // straight line
        return round(min($amt, $remaining), 2);
    }

    /** Post one month of depreciation for all assets, as of $periodEnd (Y-m-t). */
    public function run(string $periodEnd): int
    {
        $db = $this->ledger->db(); $n = 0;
        $assets = $db->select("SELECT * FROM tawasulFinanceAsset WHERE acquisitionDate <= :d", ['d' => $periodEnd])->fetchAll();
        foreach ($assets as $a) {
            $id = $a['tawasulFinanceAssetID'];
            if ($db->selectOne("SELECT COUNT(*) FROM tawasulFinanceDepreciation WHERE tawasulFinanceAssetID=:a AND periodEnd=:p", ['a' => $id, 'p' => $periodEnd])) continue;
            $acc = (float)$db->selectOne("SELECT COALESCE(SUM(amount),0) FROM tawasulFinanceDepreciation WHERE tawasulFinanceAssetID=:a", ['a' => $id]);
            $amt = self::monthly($a, $acc);
            if ($amt <= 0) continue;
            $je = $this->ledger->post($periodEnd, 'إهلاك '.$a['name'], [
                ['tawasulFinanceAccountID' => $a['tawasulFinanceExpenseAccountID'], 'debit' => $amt, 'tawasulFinanceCostCenterID' => $a['tawasulFinanceCostCenterID']],
                ['tawasulFinanceAccountID' => $a['tawasulFinanceAccumDepAccountID'], 'credit' => $amt],
            ], 'DEP', 'Depreciation', $id);
            $db->insert("INSERT INTO tawasulFinanceDepreciation (tawasulFinanceAssetID,periodEnd,amount,tawasulFinanceJournalEntryID) VALUES (:a,:p,:m,:j)", ['a' => $id, 'p' => $periodEnd, 'm' => $amt, 'j' => $je]);
            $n++;
        }
        return $n;
    }
}
