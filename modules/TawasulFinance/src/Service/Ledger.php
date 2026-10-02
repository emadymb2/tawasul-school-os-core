<?php
namespace Tos\Module\TawasulFinance\Service;

use TawasulOS\Contracts\Database\Connection;

/**
 * Central double-entry posting engine. Every financial operation in the
 * module MUST go through post() so the ledger is the single source of truth.
 */
class Ledger
{
    const EPS = 0.005;

    public function __construct(protected Connection $db, protected $userID = null) {}

    public function nextNumber(string $type): string
    {
        $this->db->statement("UPDATE tawasulFinanceSequence SET nextNumber = LAST_INSERT_ID(nextNumber + 1) WHERE documentType=:t", ['t' => $type]);
        $n = (int) $this->db->selectOne("SELECT LAST_INSERT_ID()") - 1;
        $prefix = $this->db->selectOne("SELECT prefix FROM tawasulFinanceSequence WHERE documentType=:t", ['t' => $type]);
        return $prefix.str_pad($n, 6, '0', STR_PAD_LEFT);
    }

    public function openPeriodFor(string $date)
    {
        $p = $this->db->selectOne("SELECT p.tawasulFinancePeriodID FROM tawasulFinancePeriod p
            JOIN tawasulFinanceFiscalYear f ON f.tawasulFinanceFiscalYearID=p.tawasulFinanceFiscalYearID
            WHERE :d BETWEEN p.startDate AND p.endDate AND p.status='Open' AND f.status='Open'", ['d' => $date]);
        if (!$p) throw new LedgerException('لا توجد فترة محاسبية مفتوحة للتاريخ '.$date);
        return $p;
    }

    public function accountIDByCode(string $code)
    {
        $id = $this->db->selectOne("SELECT tawasulFinanceAccountID FROM tawasulFinanceAccount WHERE code=:c", ['c' => $code]);
        if (!$id) throw new LedgerException('الحساب غير موجود: '.$code);
        return $id;
    }

    /**
     * @param array $lines [['tawasulFinanceAccountID'=>, 'debit'=>, 'credit'=>, 'tawasulFinanceCostCenterID'=>?, 'tawasulPersonID'=>?, 'memo'=>?], ...]
     * @return int journal entry ID
     */
    public function post(string $date, string $description, array $lines, string $docType = 'JV', ?string $sourceType = null, $sourceID = null, bool $draft = false)
    {
        $lines = array_values(array_filter($lines, fn($l) => round((float)($l['debit'] ?? 0), 2) != 0 || round((float)($l['credit'] ?? 0), 2) != 0));
        if (count($lines) < 2) throw new LedgerException('القيد يجب أن يحتوي على سطرين على الأقل');
        $dr = $cr = 0;
        foreach ($lines as $l) {
            $d = round((float)($l['debit'] ?? 0), 2); $c = round((float)($l['credit'] ?? 0), 2);
            if ($d < 0 || $c < 0) throw new LedgerException('لا يسمح بمبالغ سالبة');
            if ($d > 0 && $c > 0) throw new LedgerException('السطر لا يكون مديناً ودائناً معاً');
            $ok = $this->db->selectOne("SELECT isPosting FROM tawasulFinanceAccount WHERE tawasulFinanceAccountID=:id AND active='Y'", ['id' => $l['tawasulFinanceAccountID']]);
            if ($ok !== 'Y') throw new LedgerException('الحساب رقم '.$l['tawasulFinanceAccountID'].' ليس حساباً تفصيلياً نشطاً');
            $dr += $d; $cr += $c;
        }
        if (abs($dr - $cr) > self::EPS) throw new LedgerException(sprintf('القيد غير متوازن: مدين %.2f / دائن %.2f', $dr, $cr));
        $tawasulFinancePeriodID = $this->openPeriodFor($date);

        $this->db->beginTransaction();
        try {
            $num = $this->nextNumber($docType);
            $id = $this->db->insert("INSERT INTO tawasulFinanceJournalEntry (documentNumber,documentType,date,tawasulFinancePeriodID,description,sourceType,sourceID,status,tawasulPersonIDCreator,tawasulPersonIDPoster,timestampPoster)
                VALUES (:n,:t,:d,:p,:desc,:st,:sid,:s,:u,:pu,:tp)", [
                'n' => $num, 't' => $docType, 'd' => $date, 'p' => $tawasulFinancePeriodID, 'desc' => $description, 'st' => $sourceType, 'sid' => $sourceID,
                's' => $draft ? 'Draft' : 'Posted', 'u' => $this->userID, 'pu' => $draft ? null : $this->userID, 'tp' => $draft ? null : date('Y-m-d H:i:s')]);
            foreach ($lines as $l) {
                $this->db->insert("INSERT INTO tawasulFinanceJournalLine (tawasulFinanceJournalEntryID,tawasulFinanceAccountID,tawasulFinanceCostCenterID,tawasulPersonID,debit,credit,memo) VALUES (:e,:a,:cc,:p,:d,:c,:m)", [
                    'e' => $id, 'a' => $l['tawasulFinanceAccountID'], 'cc' => $l['tawasulFinanceCostCenterID'] ?: null, 'p' => $l['tawasulPersonID'] ?? null,
                    'd' => round((float)($l['debit'] ?? 0), 2), 'c' => round((float)($l['credit'] ?? 0), 2), 'm' => $l['memo'] ?? null]);
            }
            $this->audit('JournalEntry', $id, $draft ? 'draft' : 'post', ['number' => $num, 'total' => $dr]);
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    public function approveDraft($entryID)
    {
        $e = $this->db->selectOne("SELECT date FROM tawasulFinanceJournalEntry WHERE tawasulFinanceJournalEntryID=:id AND status='Draft'", ['id' => $entryID]);
        if (!$e) throw new LedgerException('القيد غير موجود أو معتمد مسبقاً');
        $this->openPeriodFor($e);
        $this->db->update("UPDATE tawasulFinanceJournalEntry SET status='Posted', tawasulPersonIDPoster=:u, timestampPoster=NOW() WHERE tawasulFinanceJournalEntryID=:id", ['u' => $this->userID, 'id' => $entryID]);
        $this->audit('JournalEntry', $entryID, 'approve');
    }

    /** Posted entries are never deleted — they are reversed with a mirror entry. */
    public function reverse($entryID, string $date, string $reason = '')
    {
        $e = $this->db->selectOne("SELECT documentNumber FROM tawasulFinanceJournalEntry WHERE tawasulFinanceJournalEntryID=:id AND status='Posted'", ['id' => $entryID]);
        if (!$e) throw new LedgerException('لا يمكن عكس هذا القيد');
        $lines = $this->db->select("SELECT tawasulFinanceAccountID,tawasulFinanceCostCenterID,tawasulPersonID,credit AS debit,debit AS credit,memo FROM tawasulFinanceJournalLine WHERE tawasulFinanceJournalEntryID=:id", ['id' => $entryID])->fetchAll();
        $newID = $this->post($date, 'عكس القيد '.$e.($reason ? ' - '.$reason : ''), $lines, 'REV', 'Reversal', $entryID);
        $this->db->update("UPDATE tawasulFinanceJournalEntry SET status='Reversed' WHERE tawasulFinanceJournalEntryID=:id", ['id' => $entryID]);
        $this->db->update("UPDATE tawasulFinanceJournalEntry SET reversedEntryID=:o WHERE tawasulFinanceJournalEntryID=:id", ['o' => $entryID, 'id' => $newID]);
        return $newID;
    }

    /** Balances per account between dates (posted + reversed entries both count, since reversal is a separate entry). */
    public function balances(?string $from, string $to, ?int $tawasulFinanceCostCenterID = null): array
    {
        $p = ['to' => $to]; $w = "e.status IN ('Posted','Reversed') AND e.date <= :to";
        if ($from) { $w .= " AND e.date >= :from"; $p['from'] = $from; }
        if ($tawasulFinanceCostCenterID) { $w .= " AND l.tawasulFinanceCostCenterID = :cc"; $p['cc'] = $tawasulFinanceCostCenterID; }
        return $this->db->select("SELECT a.tawasulFinanceAccountID AS id, a.code, a.name, a.type,
                COALESCE(SUM(l.debit),0) AS debit, COALESCE(SUM(l.credit),0) AS credit
            FROM tawasulFinanceAccount a
            LEFT JOIN tawasulFinanceJournalLine l ON l.tawasulFinanceAccountID=a.tawasulFinanceAccountID
            LEFT JOIN tawasulFinanceJournalEntry e ON e.tawasulFinanceJournalEntryID=l.tawasulFinanceJournalEntryID AND $w
            WHERE a.isPosting='Y' AND (e.tawasulFinanceJournalEntryID IS NOT NULL OR l.tawasulFinanceJournalLineID IS NULL)
            GROUP BY a.tawasulFinanceAccountID ORDER BY a.code", $p)->fetchAll();
    }

    public static function natural(array $row): float
    {
        $d = (float)$row['debit'] - (float)$row['credit'];
        return in_array($row['type'], ['Asset', 'Expense']) ? $d : -$d;
    }

    public function audit(string $table, $id, string $action, array $data = [])
    {
        $this->db->insert("INSERT INTO tawasulFinanceAuditLog (tableName,recordID,action,data,tawasulPersonID,ip) VALUES (:t,:r,:a,:d,:u,:ip)", [
            't' => $table, 'r' => (int)$id, 'a' => $action, 'd' => json_encode($data, JSON_UNESCAPED_UNICODE), 'u' => $this->userID, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
    }

    public function db(): Connection { return $this->db; }

    /**
     * The person the ledger is acting for. Billing needs it to stamp the author
     * of an invoice it raises on someone else's behalf.
     */
    public function userID() { return $this->userID; }
}
