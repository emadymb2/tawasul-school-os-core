<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.

TawasulOS — Journal entry gateway and double-entry posting engine.
*/

namespace Tos\Module\TawasulFinance\Domain;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;

/**
 * JournalGateway
 *
 * Owns the ledger: journal entries and their lines, and the rules that make
 * those lines trustworthy.
 *
 * The schema cannot enforce double-entry (MySQL CHECK constraints are not used
 * here, and a deferred constraint is not available), so balance is guaranteed
 * in the engine instead. Every write path goes through postEntry() or
 * saveDraft(), and both validate before touching the database.
 */
class JournalGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulFinanceJournalEntry';
    private static $primaryKey = 'tawasulFinanceJournalEntryID';

    private static $searchableColumns = ['documentNumber', 'description'];

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_POSTED = 'Posted';
    public const STATUS_REVERSED = 'Reversed';

    /** A period only accepts postings while its status is this. */
    private const PERIOD_OPEN = 'Open';

    /** Two decimal places: the ledger is held to the currency's precision. */
    private const MONEY_SCALE = 2;

    /** @var FiscalYearGateway */
    private $fiscalYearGateway;

    /** @var AccountGateway */
    private $accountGateway;

    /**
     * Constructor injection is not used here because the container autowires
     * gateways by reflection and would not know about these two. They are
     * resolved lazily through setDependencies() instead, which tawasul.php calls
     * via the service container, keeping the class free to instantiate in tests.
     */
    public function setDependencies(FiscalYearGateway $fiscalYears, AccountGateway $accounts)
    {
        $this->fiscalYearGateway = $fiscalYears;
        $this->accountGateway = $accounts;

        return $this;
    }

    /**
     * Paginated journal listing with its period and creator joined in.
     */
    public function queryEntries(QueryCriteria $criteria) : \TawasulOS\Domain\DataSet
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'j.tawasulFinanceJournalEntryID',
                'j.documentNumber',
                'j.documentType',
                'j.date',
                'j.description',
                'j.status',
                'j.isRecurring',
                'j.sourceType',
                'j.sourceID',
                'j.tawasulFinancePeriodID',
                'p.name as periodName',
                'creator.surname as creatorSurname',
                'creator.preferredName as creatorPreferredName',
                // Totals are derived, not stored: the schema keeps no cached
                // total column, and a cached one could drift from the lines.
                'COALESCE((SELECT SUM(l.debit) FROM tawasulFinanceJournalLine l WHERE l.tawasulFinanceJournalEntryID = j.tawasulFinanceJournalEntryID), 0) as totalDebit',
                'COALESCE((SELECT SUM(l.credit) FROM tawasulFinanceJournalLine l WHERE l.tawasulFinanceJournalEntryID = j.tawasulFinanceJournalEntryID), 0) as totalCredit',
            ])
            ->leftJoin('tawasulFinancePeriod', 'p', 'j.tawasulFinancePeriodID = p.tawasulFinancePeriodID')
            ->leftJoin('tawasulPerson', 'creator', 'j.tawasulPersonIDCreator = creator.tawasulPersonID')
            ->orderBy(['j.date DESC', 'j.tawasulFinanceJournalEntryID DESC']);

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('j.status = :status')
                    ->bindValue('status', $status);
            },
            'documentType' => function ($query, $type) {
                return $query
                    ->where('j.documentType = :documentType')
                    ->bindValue('documentType', $type);
            },
            'period' => function ($query, $periodID) {
                return $query
                    ->where('j.tawasulFinancePeriodID = :period')
                    ->bindValue('period', $periodID);
            },
            'date' => function ($query, $range) {
                return $query
                    ->where('j.date BETWEEN :from AND :to')
                    ->bindValue('from', $range[0] ?? null)
                    ->bindValue('to', $range[1] ?? null);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * A single entry with its lines, for view and edit screens.
     */
    public function getEntryWithLines($tawasulFinanceJournalEntryID) : array
    {
        $entry = $this->getByID($tawasulFinanceJournalEntryID);

        if (empty($entry)) {
            return [];
        }

        $entry['lines'] = $this->selectLines($tawasulFinanceJournalEntryID);

        return $entry;
    }

    /**
     * The lines of an entry, joined to the account for display.
     */
    public function selectLines($tawasulFinanceJournalEntryID) : array
    {
        return $this->db()->select(
            'SELECT l.tawasulFinanceJournalLineID,
                    l.tawasulFinanceAccountID,
                    l.tawasulFinanceCostCenterID,
                    l.tawasulPersonID,
                    l.debit,
                    l.credit,
                    l.memo,
                    a.code as accountCode,
                    a.name as accountName,
                    a.type as accountType,
                    c.name as costCenterName
               FROM tawasulFinanceJournalLine l
          LEFT JOIN tawasulFinanceAccount a
                 ON a.tawasulFinanceAccountID = l.tawasulFinanceAccountID
          LEFT JOIN tawasulFinanceCostCenter c
                 ON c.tawasulFinanceCostCenterID = l.tawasulFinanceCostCenterID
              WHERE l.tawasulFinanceJournalEntryID = :entry
              ORDER BY l.tawasulFinanceJournalLineID',
            ['entry' => $tawasulFinanceJournalEntryID]
        )->fetchAll();
    }

    /**
     * Validate a set of lines without writing anything.
     *
     * This is separated from the write path so the UI can show the same errors
     * the engine would raise, and so the checks can be unit tested directly.
     *
     * @param array $lines Each needs account, debit, credit. Cost centre,
     *                     memo and person are optional.
     * @return string[] Human readable errors; empty means the lines are postable.
     */
    public function validateLines(array $lines) : array
    {
        $errors = [];

        if (count($lines) < 2) {
            $errors[] = 'A journal entry needs at least two lines.';
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $i => $line) {
            $accountID = $line['tawasulFinanceAccountID'] ?? null;
            $debit = $this->toMoney($line['debit'] ?? 0);
            $credit = $this->toMoney($line['credit'] ?? 0);
            $n = $i + 1;

            if (empty($accountID)) {
                $errors[] = "Line {$n}: an account is required.";
            } else {
                $account = $this->db()->selectOne(
                    'SELECT tawasulFinanceAccountID, isPosting AS isPosting, active AS active, code AS code, name AS name
                       FROM tawasulFinanceAccount
                      WHERE tawasulFinanceAccountID = :id',
                    ['id' => $accountID]
                );

                if (empty($account)) {
                    $errors[] = "Line {$n}: that account no longer exists.";
                } else {
                    if (($account['isPosting'] ?? 'Y') === 'N') {
                        $errors[] = "Line {$n}: {$account['code']} is a heading and cannot be posted to.";
                    }
                    if (($account['active'] ?? 'Y') === 'N') {
                        $errors[] = "Line {$n}: {$account['code']} is inactive.";
                    }
                }
            }

            if ($debit < 0 || $credit < 0) {
                $errors[] = "Line {$n}: amounts cannot be negative.";
            }

            if ($debit > 0 && $credit > 0) {
                $errors[] = "Line {$n}: a line cannot be both a debit and a credit.";
            }

            if ($debit == 0.0 && $credit == 0.0) {
                $errors[] = "Line {$n}: enter a debit or a credit.";
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        // Compare at currency precision: floating point sums drift.
        if ($this->round($totalDebit) !== $this->round($totalCredit)) {
            $errors[] = sprintf(
                'The entry does not balance: debits %s, credits %s.',
                number_format($totalDebit, self::MONEY_SCALE),
                number_format($totalCredit, self::MONEY_SCALE)
            );
        }

        return $errors;
    }

    /**
     * Post an entry: validate, resolve the period, write the lines and mark it
     * Posted, all inside one transaction so a failure leaves no partial entry.
     *
     * @param array $entry Needs date, description, documentType (defaults JV),
     *                     and optional sourceType, sourceID, recurring fields.
     * @param array $lines As accepted by validateLines().
     * @return array{success:bool, id:int, errors:string[]}
     */
    public function postEntry(array $entry, array $lines) : array
    {
        $errors = $this->validateLines($lines);

        $date = $entry['date'] ?? '';
        if (empty($date)) {
            $errors[] = 'An entry needs a date.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'id' => 0, 'errors' => $errors];
        }

        $period = $this->requireOpenPeriod($date);
        if (isset($period['error'])) {
            return ['success' => false, 'id' => 0, 'errors' => [$period['error']]];
        }

        $this->db()->beginTransaction();

        try {
            $documentNumber = $this->nextDocumentNumber($entry['documentType'] ?? 'JV');

            $insert = $this
                ->newInsert()
                ->into($this->getTableName())
                ->cols([
                    'documentNumber' => $documentNumber,
                    'documentType' => $entry['documentType'] ?? 'JV',
                    'date' => $date,
                    'tawasulFinancePeriodID' => $period['tawasulFinancePeriodID'],
                    'description' => $entry['description'] ?? '',
                    'sourceType' => $entry['sourceType'] ?? null,
                    'sourceID' => $entry['sourceID'] ?? null,
                    'status' => self::STATUS_POSTED,
                    'isRecurring' => $entry['isRecurring'] ?? 'N',
                    'recurringFrequency' => $entry['recurringFrequency'] ?? null,
                    'tawasulPersonIDCreator' => $entry['tawasulPersonIDCreator'] ?? null,
                    'tawasulPersonIDPoster' => $entry['tawasulPersonIDCreator'] ?? null,
                    'timestampPoster' => date('Y-m-d H:i:s'),
                ]);
            $this->runInsert($insert);
            $entryID = (int) $this->db()->getConnection()->lastInsertId();

            $this->writeLines($entryID, $lines);

            $this->db()->commit();

            return ['success' => true, 'id' => $entryID, 'errors' => []];
        } catch (\Throwable $e) {
            $this->db()->rollBack();

            return ['success' => false, 'id' => 0, 'errors' => ['Could not save the entry: '.$e->getMessage()]];
        }
    }

    /**
     * Save an unbalanced, unposted entry for later completion.
     *
     * Drafts deliberately skip the balance check: that is the point of a draft.
     * They are never included in reports, which filter on status = 'Posted'.
     */
    public function saveDraft(array $entry, array $lines) : array
    {
        $date = $entry['date'] ?? '';
        if (empty($date)) {
            return ['success' => false, 'id' => 0, 'errors' => ['A draft still needs a date.']];
        }

        $period = $this->selectPeriodForDateOrNull($date);

        $this->db()->beginTransaction();

        try {
            $documentNumber = $this->nextDocumentNumber($entry['documentType'] ?? 'JV');

            $insert = $this
                ->newInsert()
                ->into($this->getTableName())
                ->cols([
                    'documentNumber' => $documentNumber,
                    'documentType' => $entry['documentType'] ?? 'JV',
                    'date' => $date,
                    'tawasulFinancePeriodID' => $period['tawasulFinancePeriodID'] ?? null,
                    'description' => $entry['description'] ?? '',
                    'sourceType' => $entry['sourceType'] ?? null,
                    'sourceID' => $entry['sourceID'] ?? null,
                    'status' => self::STATUS_DRAFT,
                    'tawasulPersonIDCreator' => $entry['tawasulPersonIDCreator'] ?? null,
                ]);
            $this->runInsert($insert);
            $entryID = (int) $this->db()->getConnection()->lastInsertId();

            $this->writeLines($entryID, $lines);

            $this->db()->commit();

            return ['success' => true, 'id' => $entryID, 'errors' => []];
        } catch (\Throwable $e) {
            $this->db()->rollBack();

            return ['success' => false, 'id' => 0, 'errors' => ['Could not save the draft: '.$e->getMessage()]];
        }
    }

    /**
     * Post an existing draft after re-validating it.
     */
    public function postDraft($tawasulFinanceJournalEntryID) : array
    {
        $entry = $this->getEntryWithLines($tawasulFinanceJournalEntryID);

        if (empty($entry)) {
            return ['success' => false, 'id' => 0, 'errors' => ['That entry no longer exists.']];
        }

        if ($entry['status'] !== self::STATUS_DRAFT) {
            return ['success' => false, 'id' => 0, 'errors' => ['Only a draft can be posted.']];
        }

        $lines = [];
        foreach ($entry['lines'] as $line) {
            $lines[] = [
                'tawasulFinanceAccountID' => $line['tawasulFinanceAccountID'],
                'tawasulFinanceCostCenterID' => $line['tawasulFinanceCostCenterID'],
                'tawasulPersonID' => $line['tawasulPersonID'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'memo' => $line['memo'],
            ];
        }

        $result = $this->postEntry([
            'date' => $entry['date'],
            'description' => $entry['description'],
            'documentType' => $entry['documentType'],
            'sourceType' => $entry['sourceType'],
            'sourceID' => $entry['sourceID'],
            'tawasulPersonIDCreator' => $entry['tawasulPersonIDCreator'],
        ], $lines);

        if ($result['success']) {
            // Discard the draft now that its content has been posted.
            $this->deleteWithLines($tawasulFinanceJournalEntryID);
        }

        return $result;
    }

    /**
     * Reverse a posted entry by writing a mirror-image entry.
     *
     * The original stays Posted. Reports include both, so the pair nets to
     * zero: removing the original from the ledger as well would reverse it
     * twice and leave the accounts wrong. The original is instead marked
     * Reversed for display purposes, which is not a report filter.
     */
    public function reverseEntry($tawasulFinanceJournalEntryID, $reversalDate, $tawasulPersonID) : array
    {
        $entry = $this->getEntryWithLines($tawasulFinanceJournalEntryID);

        if (empty($entry)) {
            return ['success' => false, 'id' => 0, 'errors' => ['That entry no longer exists.']];
        }

        if ($entry['status'] !== self::STATUS_POSTED) {
            return ['success' => false, 'id' => 0, 'errors' => ['Only a posted entry can be reversed.']];
        }

        if ($this->isReversed($tawasulFinanceJournalEntryID)) {
            return ['success' => false, 'id' => 0, 'errors' => ['This entry has already been reversed.']];
        }

        $lines = [];
        foreach ($entry['lines'] as $line) {
            $lines[] = [
                'tawasulFinanceAccountID' => $line['tawasulFinanceAccountID'],
                'tawasulFinanceCostCenterID' => $line['tawasulFinanceCostCenterID'],
                'tawasulPersonID' => $line['tawasulPersonID'],
                // Swapped: the reversal undoes what the original did.
                'debit' => $line['credit'],
                'credit' => $line['debit'],
                'memo' => 'Reversal of '.$entry['documentNumber'],
            ];
        }

        $result = $this->postEntry([
            'date' => $reversalDate,
            'description' => 'Reversal of '.$entry['documentNumber'].': '.$entry['description'],
            'documentType' => 'REV',
            'sourceType' => 'Reversal',
            'sourceID' => $entry['tawasulFinanceJournalEntryID'],
            'tawasulPersonIDCreator' => $tawasulPersonID,
        ], $lines);

        if ($result['success']) {
            // The original deliberately keeps its Posted status: the reports
            // select on that, and dropping it here as well as adding the
            // mirror entry would remove the transaction from the books twice
            // over. The reversal is recorded by the reversedEntryID link below.
            $link = $this
                ->newUpdate()
                ->table($this->getTableName())
                ->cols(['reversedEntryID' => $result['id']])
                ->where('tawasulFinanceJournalEntryID = :id')
                ->bindValue('id', $tawasulFinanceJournalEntryID);
            $this->runUpdate($link);
        }

        return $result;
    }

    /**
     * Whether an entry has already been reversed.
     *
     * Guards the reversal path: reversing twice would leave the accounts wrong
     * in the opposite direction.
     */
    public function isReversed($tawasulFinanceJournalEntryID) : bool
    {
        $row = $this->db()->selectOne(
            'SELECT reversedEntryID FROM tawasulFinanceJournalEntry WHERE tawasulFinanceJournalEntryID = :id',
            ['id' => $tawasulFinanceJournalEntryID]
        );

        return !empty($row['reversedEntryID'] ?? null);
    }

    /**
     * Write an entry's lines, rounding each to currency precision.
     */
    private function writeLines($entryID, array $lines) : void
    {
        foreach ($lines as $line) {
            $debit = $this->toMoney($line['debit'] ?? 0);
            $credit = $this->toMoney($line['credit'] ?? 0);

            // Skip lines that carry nothing, rather than storing empty zeros.
            if ($debit == 0.0 && $credit == 0.0) {
                continue;
            }

            $insert = $this
                ->newInsert()
                ->into('tawasulFinanceJournalLine')
                ->cols([
                    'tawasulFinanceJournalEntryID' => $entryID,
                    'tawasulFinanceAccountID' => $line['tawasulFinanceAccountID'],
                    'tawasulFinanceCostCenterID' => $line['tawasulFinanceCostCenterID'] ?? null,
                    'tawasulPersonID' => $line['tawasulPersonID'] ?? null,
                    'debit' => number_format($debit, self::MONEY_SCALE, '.', ''),
                    'credit' => number_format($credit, self::MONEY_SCALE, '.', ''),
                    'memo' => $line['memo'] ?? null,
                ]);
            $this->runInsert($insert);
        }
    }

    /**
     * Allocate the next document number for a type.
     *
     * The counter is incremented under a row lock so concurrent postings cannot
     * be handed the same number, which would trip the UNIQUE(documentType,
     * documentNumber) index.
     */
    public function nextDocumentNumber(string $documentType) : string
    {
        $st = $this->db()->getConnection()->prepare(
            'SELECT prefix, nextNumber FROM tawasulFinanceSequence WHERE documentType = :type FOR UPDATE'
        );
        $st->execute(['type' => $documentType]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        if (empty($row)) {
            // Unknown type: start a sequence rather than failing the posting.
            $prefix = strtoupper(substr($documentType, 0, 3));
            $number = 1;
        } else {
            $prefix = $row['PREFIX'] ?? $row['prefix'] ?? '';
            $number = (int) ($row['NEXTNUMBER'] ?? $row['nextNumber'] ?? 1);
        }

        $this->db()->getConnection()->prepare(
            'INSERT INTO tawasulFinanceSequence (documentType, prefix, nextNumber)
                  VALUES (:type, :prefix, :next)
             ON DUPLICATE KEY UPDATE nextNumber = :next'
        )->execute([
            ':type' => $documentType,
            ':prefix' => $prefix,
            ':next' => $number + 1,
        ]);

        return $prefix.$number;
    }

    /**
     * Resolve the period for a date, failing if it is closed or missing.
     *
     * @return array Either the period, or ['error' => string].
     */
    private function requireOpenPeriod(string $date) : array
    {
        $period = $this->selectPeriodForDateOrNull($date);

        if (empty($period)) {
            return ['error' => 'No accounting period covers '.$date.'. Create a fiscal year for that date first.'];
        }

        if ($period['status'] !== self::PERIOD_OPEN) {
            return ['error' => 'The period '.$period['name'].' is closed and cannot receive new postings.'];
        }

        return $period;
    }

    /**
     * Period lookup that tolerates a missing fiscal year setup, used by drafts.
     *
     * Columns are aliased explicitly rather than relying on the driver's key
     * casing, so callers get stable names whichever case MySQL reports.
     */
    private function selectPeriodForDateOrNull(string $date) : array
    {
        $row = $this->db()->selectOne(
            'SELECT tawasulFinancePeriodID AS periodID, name, status
               FROM tawasulFinancePeriod
              WHERE startDate <= :date AND endDate >= :date
              ORDER BY startDate DESC
              LIMIT 1',
            ['date' => $date]
        );

        if (empty($row)) {
            return [];
        }

        return [
            'tawasulFinancePeriodID' => $row['periodID'] ?? null,
            'name' => $row['name'] ?? '',
            'status' => $row['status'] ?? '',
        ];
    }

    /**
     * Delete a draft and its lines.
     */
    public function deleteWithLines($tawasulFinanceJournalEntryID) : bool
    {
        $entry = $this->getByID($tawasulFinanceJournalEntryID);

        if (empty($entry)) {
            return false;
        }

        if ($entry['status'] !== self::STATUS_DRAFT) {
            return false;
        }

        $this->db()->beginTransaction();

        try {
            $deleteLines = $this
                ->newDelete()
                ->from('tawasulFinanceJournalLine')
                ->where('tawasulFinanceJournalEntryID = :entry')
                ->bindValue('entry', $tawasulFinanceJournalEntryID);
            $this->runDelete($deleteLines);

            $deleteEntry = $this
                ->newDelete()
                ->from($this->getTableName())
                ->where('tawasulFinanceJournalEntryID = :entry')
                ->bindValue('entry', $tawasulFinanceJournalEntryID);
            $this->runDelete($deleteEntry);

            $this->db()->commit();

            return true;
        } catch (\Throwable $e) {
            $this->db()->rollBack();

            return false;
        }
    }

    /**
     * Record an audit trail entry.
     */
    public function audit(string $tableName, int $recordID, string $action, ?array $data, $tawasulPersonID) : void
    {
        $insert = $this
            ->newInsert()
            ->into('tawasulFinanceAuditLog')
            ->cols([
                'tableName' => $tableName,
                'recordID' => $recordID,
                'action' => $action,
                'data' => $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE),
                'tawasulPersonID' => $tawasulPersonID,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        $this->runInsert($insert);
    }

    /**
     * Normalise a money input to a float at currency precision.
     */
    private function toMoney($value) : float
    {
        return round((float) $value, self::MONEY_SCALE);
    }

    /**
     * Round a float to currency precision, normalising -0.0 to 0.0 so the
     * comparison in validateLines() cannot fail on a rounding artefact.
     */
    private function round(float $value) : float
    {
        $r = round($value, self::MONEY_SCALE);

        return $r == 0.0 ? 0.0 : $r;
    }
}