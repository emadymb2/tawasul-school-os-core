<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
This program is free software: you can redistribute it and either version 3 of the License, or
(at your option) any later version.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.

TawasulOS — Financial reports gateway.
*/

namespace Tos\Module\TawasulFinance\Domain;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;

/**
 * Ledger Report Gateway
 *
 * Reports read posted entries only. Drafts and reversed entries are excluded so
 * the books always reflect committed activity: a reversed entry is excluded
 * because the compensating entry restores the correct balance, and including
 * both would double-count.
 */
class LedgerReportGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulFinanceJournalEntry';
    private static $primaryKey = 'tawasulFinanceJournalEntryID';

    private static $searchableColumns = [];

    /** Cached result of hasPersonTable(). */
    private $hasPersonTable;

    /**
     * Trial balance as at a date.
     *
     * Returns one row per posting account with movement and closing balance.
     * A debit-normal account (asset, expense) shows a positive balance when it
     * has been debited more than credited; a credit-normal account the reverse.
     *
     * @param string|null $asAt YYYY-MM-DD. Null means all time.
     * @return array Rows with account identity, debitTotal, creditTotal, balance
     *               and a balanceType of Debit or Credit.
     */
    public function trialBalance(?string $asAt = null, ?string $from = null, ?array $types = null) : array
    {
        $params = [];
        $where = ["j.status = 'Posted'", "a.isPosting = 'Y'"];

        if ($asAt !== null) {
            $where[] = 'j.date <= :asAt';
            $params[':asAt'] = $asAt;
        }

        if ($from !== null) {
            $where[] = 'j.date >= :from';
            $params[':from'] = $from;
        }

        if (!empty($types)) {
            $placeholders = [];
            foreach (array_values($types) as $i => $t) {
                $placeholders[] = ':type' . $i;
                $params[':type' . $i] = $t;
            }
            $where[] = 'a.type IN (' . implode(',', $placeholders) . ')';
        }

        $sql = 'SELECT a.tawasulFinanceAccountID, a.code, a.name, a.type,
                       COALESCE(SUM(l.debit), 0) AS debitTotal,
                       COALESCE(SUM(l.credit), 0) AS creditTotal,
                       COALESCE(SUM(l.debit - l.credit), 0) AS net
                  FROM tawasulFinanceJournalLine l
                  JOIN tawasulFinanceJournalEntry j
                    ON j.tawasulFinanceJournalEntryID = l.tawasulFinanceJournalEntryID
                  JOIN tawasulFinanceAccount a
                    ON a.tawasulFinanceAccountID = l.tawasulFinanceAccountID
                 WHERE ' . implode(' AND ', $where) . '
                 GROUP BY a.tawasulFinanceAccountID, a.code, a.name, a.type
                 ORDER BY a.code';

        $rows = $this->fetchRows($sql, $params);

        $debitTotal = 0.0;
        $creditTotal = 0.0;

        foreach ($rows as $i => $row) {
            // net = total debits - total credits. A positive net always means debits
            // dominate, so it is a debit balance, whatever the account type.
            // A negative net is a credit balance. This holds for a credit-normal
            // account too: revenue credited 25,000 has net -25,000 and belongs
            // in the credit column.
            //
            // What differs by type is the sign we report the balance with, so
            // that a revenue balance reads as a positive number.
            $net = (float) $row['net'];
            $isDebitNormal = in_array($row['type'], AccountGateway::DEBIT_TYPES, true);

            $debitBalance = max($net, 0.0);
            $creditBalance = max(-$net, 0.0);

            $rows[$i]['balance'] = $isDebitNormal ? $debitBalance - $creditBalance : $creditBalance - $debitBalance;
            $rows[$i]['balanceType'] = $debitBalance > 0 ? 'Debit' : ($creditBalance > 0 ? 'Credit' : '');
            $rows[$i]['debitBalance'] = $debitBalance;
            $rows[$i]['creditBalance'] = $creditBalance;

            $debitTotal += $debitBalance;
            $creditTotal += $creditBalance;
        }

        return [
            'rows' => $rows,
            'debitTotal' => $debitTotal,
            'creditTotal' => $creditTotal,
            // A non-zero difference means the ledger is corrupt, which the UI
            // surfaces rather than hiding.
            'isBalanced' => abs($debitTotal - $creditTotal) < 0.005,
        ];
    }

    /**
     * General ledger: every posted line, optionally for one account, with a
     * running balance.
     *
     * @return array Rows plus opening and closing balances.
     */
    public function generalLedger(?string $asAt = null, ?string $from = null, ?int $accountID = null) : array
    {
        $params = [];
        $where = ["j.status = 'Posted'"];

        if ($asAt !== null) {
            $where[] = 'j.date <= :asAt';
            $params[':asAt'] = $asAt;
        }

        if ($from !== null) {
            $where[] = 'j.date >= :from';
            $params[':from'] = $from;
        }

        if ($accountID !== null) {
            $where[] = 'l.tawasulFinanceAccountID = :account';
            $params[':account'] = $accountID;
        }

        // A finance-only database has no tawasulPerson, so the creator columns
        // are dropped along with their join. The leading space matters: this
        // clause is concatenated straight after the period join above, and
        // without it MySQL reads "...tawasulFinancePeriodIDLEFT JOIN".
        $creatorCols = $this->hasPersonTable()
            ? ', creator.surname AS creatorSurname, creator.preferredName AS creatorPreferredName'
            : '';
        $creatorJoin = $this->hasPersonTable()
            ? '
                  LEFT JOIN tawasulPerson creator
                    ON creator.tawasulPersonID = j.tawasulPersonIDCreator'
            : '';

        $sql = 'SELECT l.tawasulFinanceJournalLineID, l.tawasulFinanceJournalEntryID,
                       l.tawasulFinanceAccountID, a.code AS accountCode, a.name AS accountName,
                       a.type AS accountType,
                       j.documentNumber, j.documentType, j.date, j.description AS entryDescription,
                       p.name AS periodName, l.debit, l.credit, l.memo' . $creatorCols . '
                  FROM tawasulFinanceJournalLine l
                  JOIN tawasulFinanceJournalEntry j
                    ON j.tawasulFinanceJournalEntryID = l.tawasulFinanceJournalEntryID
                  JOIN tawasulFinanceAccount a
                    ON a.tawasulFinanceAccountID = l.tawasulFinanceAccountID
             LEFT JOIN tawasulFinancePeriod p
                    ON p.tawasulFinancePeriodID = j.tawasulFinancePeriodID' . $creatorJoin . '
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY j.date, j.documentNumber, l.tawasulFinanceJournalLineID';

        $rows = $this->fetchRows($sql, $params);

        if ($accountID === null) {
            return ['rows' => $rows, 'opening' => 0.0, 'closing' => 0.0, 'isDebitNormal' => true];
        }

        $account = $this->fetchRows('SELECT type FROM tawasulFinanceAccount WHERE tawasulFinanceAccountID = :id', [':id' => $accountID]);
        $type = $account[0]['type'] ?? 'Asset';
        $isDebitNormal = in_array($type, AccountGateway::DEBIT_TYPES, true);

        $running = 0.0;
        foreach ($rows as $i => $row) {
            $running += (float) $row['debit'] - (float) $row['credit'];
            // Report the running balance in the account's own sign convention.
            $rows[$i]['balance'] = $isDebitNormal ? $running : -$running;
        }

        return [
            'rows' => $rows,
            'opening' => 0.0,
            'closing' => $isDebitNormal ? $running : -$running,
            'isDebitNormal' => $isDebitNormal,
        ];
    }

    /**
     * Balances per account type, for a dashboard or an income summary.
     */
    public function accountTypeSummary(?string $asAt = null) : array
    {
        $trial = $this->trialBalance($asAt);

        $summary = [];
        foreach ($trial['rows'] as $row) {
            $type = $row['type'];
            if (!isset($summary[$type])) {
                $summary[$type] = ['type' => $type, 'debit' => 0.0, 'credit' => 0.0];
            }
            $summary[$type]['debit'] += $row['debitBalance'];
            $summary[$type]['credit'] += $row['creditBalance'];
        }

        return array_values($summary);
    }

    /**
     * Whether the ledger nets to zero, used as a health check after posting.
     */
    public function ledgerIsBalanced(?string $asAt = null) : bool
    {
        return $this->trialBalance($asAt)['isBalanced'];
    }

    /**
     * Whether the core tawasulPerson table is available on this connection.
     *
     * Cached per request: the ledger is read often and the answer cannot change
     * within a single process.
     */
    private function hasPersonTable() : bool
    {
        if ($this->hasPersonTable === null) {
            try {
                $this->db()->getConnection()->query('SELECT 1 FROM tawasulPerson LIMIT 1');
                $this->hasPersonTable = true;
            } catch (\PDOException $e) {
                $this->hasPersonTable = false;
            }
        }

        return $this->hasPersonTable;
    }

    /**
     * Fetch rows as plain arrays.
     *
     * Prefers fetchRows() where the connection offers it, so CLI and test
     * adapters can be used without diverging from production behaviour.
     */
    private function fetchRows(string $sql, array $params = []) : array
    {
        $db = $this->db();

        if (method_exists($db, 'fetchRows')) {
            return $db->fetchRows($sql, $params);
        }

        $statement = $db->getConnection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }
}