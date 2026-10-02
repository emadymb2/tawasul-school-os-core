<?php
namespace Tos\Module\TawasulCore\Controller;

use PDO;
use Tos\Module\TawasulCore\Auth\Credential;
use Tos\Module\TawasulCore\Auth\Permissions;
use Tos\Module\TawasulCore\Http\ApiException;
use Tos\Module\TawasulCore\Http\Request;
use Tos\Module\TawasulCore\Resource\Registry;

/**
 * Writes to the general ledger.
 *
 * The generic resource endpoints deliberately expose /journal-entries and
 * /journal-lines as read-only. A posting engine exists precisely because
 * double entry cannot be enforced by the schema: an entry has to balance to the
 * currency, land in a period that is still open, hit posting accounts only, and
 * take the next number from a locked sequence. A generic POST /journal-lines
 * would write one unbalanced line and quietly corrupt the books, so those two
 * resources are GET-only and every write arrives here instead.
 *
 * The controller adds no accounting rules of its own. It authorises, translates
 * the request into the shape TawasulFinance's gateways expect, and surfaces
 * their validation errors as a 422 so a caller gets every reason at once rather
 * than one per round trip.
 */
class AccountingController
{
    protected $pdo;
    protected $permissions;
    protected $credential;

    /** Wires this module's classes into the request when they are first needed. */
    protected static $financeLoaderRegistered = false;

    /**
     * The reports this controller can produce. The Router reads the same list so
     * an unknown report is refused before any database work, and OpenApi can
     * enumerate them without duplicating the names.
     */
    const REPORTS = ['trial-balance', 'general-ledger', 'account-types', 'health'];

    public function __construct(PDO $pdo, Permissions $permissions, Credential $credential)
    {
        $this->pdo = $pdo;
        $this->permissions = $permissions;
        $this->credential = $credential;

        // Registered here rather than on first use. PHP resolves the class name
        // of "new LedgerReportGateway($this->financeConnection())" before it
        // evaluates the argument, so deferring the loader to the connection
        // helper would leave the class unfindable at that moment.
        self::registerFinanceLoader();
    }

    /**
     * POST /v2/accounting/journal
     *
     * Body:
     *   date         YYYY-MM-DD, required.
     *   description  free text, optional but recommended.
     *   documentType optional, defaults to JV.
     *   draft        true to save an unbalanced draft instead of posting.
     *   lines[]      each needs tawasulFinanceAccountID and one of debit/credit;
     *                tawasulFinanceCostCenterID, tawasulPersonID and memo optional.
     */
    public function postJournal(Request $request): array
    {
        $this->permissions->authorise($this->credential, Registry::get('journal-entries'), 'POST');

        $body = $request->getBody();
        $lines = $this->readLines($body);

        // Checked before the gateway so an obviously malformed request is a 400
        // rather than a validation failure dressed up as a 422.
        $this->requireDate($body);

        $entry = [
            'date' => $body['date'],
            'description' => (string) ($body['description'] ?? ''),
            'documentType' => $this->documentType($body),
            'sourceType' => $body['sourceType'] ?? null,
            'sourceID' => isset($body['sourceID']) ? (int) $body['sourceID'] : null,
            'isRecurring' => ($body['isRecurring'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'recurringFrequency' => $body['recurringFrequency'] ?? null,
            'tawasulPersonIDCreator' => $this->credential->getPersonID(),
        ];

        $journal = $this->journalGateway();

        $isDraft = !empty($body['draft']);
        $result = $isDraft
            ? $journal->saveDraft($entry, $lines)
            : $journal->postEntry($entry, $lines);

        if (!$result['success']) {
            throw ApiException::unprocessable(
                $isDraft ? 'The draft could not be saved.' : 'The entry could not be posted.',
                ['errors' => $result['errors'], 'isDraft' => $isDraft]
            );
        }

        $this->audit($result['id'], $isDraft ? 'draft' : 'post', $entry);

        return [
            'data' => $this->entryPayload($result['id']) + ['isDraft' => $isDraft],
            'meta' => ['created' => true, 'balance' => $this->totals($result['id'])],
        ];
    }

    /**
     * POST /v2/accounting/journal/{id}/reverse
     *
     * Body: date, optional, defaults to today. Reversing twice is refused by
     * the gateway, so a retried request cannot invert the accounts again.
     */
    public function reverseJournal(string $id, Request $request): array
    {
        $this->permissions->authorise($this->credential, Registry::get('journal-entries'), 'POST');

        $date = $request->input('date') ?: date('Y-m-d');

        $result = $this->journalGateway()->reverseEntry($id, $date, $this->credential->getPersonID());

        if (!$result['success']) {
            throw ApiException::unprocessable('The entry could not be reversed.', ['errors' => $result['errors']]);
        }

        $this->audit($result['id'], 'reverse', ['originalID' => $id, 'date' => $date]);

        return [
            'data' => [
                'original' => $this->entryPayload($id),
                'reversal' => $this->entryPayload($result['id']),
            ],
            'meta' => ['created' => true],
        ];
    }

    /**
     * POST /v2/accounting/journal/validate
     *
     * Runs every posting rule and writes nothing, so a client can show the same
     * errors the ledger would raise while the user is still typing.
     */
    public function validateJournal(Request $request): array
    {
        $this->permissions->authorise($this->credential, Registry::get('journal-entries'), 'POST');

        $body = $request->getBody();
        $lines = $this->readLines($body);
        $errors = $this->journalGateway()->validateLines($lines);

        $date = $body['date'] ?? null;
        if (!empty($date)) {
            $period = $this->pdo->prepare(
                'SELECT tawasulFinancePeriodID, name, status FROM tawasulFinancePeriod
                  WHERE startDate <= :date AND endDate >= :date ORDER BY startDate LIMIT 1'
            );
            $period->execute(['date' => $date]);
            $row = $period->fetch(PDO::FETCH_ASSOC);

            if (empty($row)) {
                $errors[] = 'No fiscal period covers '.$date.'.';
            } elseif ($row['status'] !== 'Open') {
                $errors[] = 'Period "'.$row['name'].'" is closed, so nothing can be posted to it.';
            } else {
                $periodName = $row['name'];
            }
        }

        return [
            'data' => [
                'isValid' => empty($errors),
                'errors' => $errors,
                'totals' => $this->lineTotals($lines),
                'period' => $periodName ?? null,
            ],
        ];
    }

    /**
     * GET /v2/accounting/reports/{report}
     *
     * report is one of trial-balance, general-ledger, account-types or health.
     */
    public function report(string $report, Request $request): array
    {
        $this->permissions->authorise($this->credential, Registry::get('journal-entries'), 'GET');

        $asAt = $request->query('asAt') ?: null;
        $from = $request->query('from') ?: null;

        if ($asAt !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $asAt)) {
            throw ApiException::badRequest('asAt must be a date in YYYY-MM-DD form.', ['received' => $asAt]);
        }
        if ($from !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            throw ApiException::badRequest('from must be a date in YYYY-MM-DD form.', ['received' => $from]);
        }

        $gateway = $this->ledgerGateway();

        switch ($report) {
            case 'trial-balance':
                return ['data' => $gateway->trialBalance($asAt, $from)];

            case 'general-ledger':
                $accountID = $request->queryInt('accountID', 0);
                return ['data' => $gateway->generalLedger($asAt, $from, $accountID > 0 ? $accountID : null)];

            case 'account-types':
                return ['data' => $gateway->accountTypeSummary($asAt)];

            case 'health':
                return ['data' => [
                    'isBalanced' => $gateway->ledgerIsBalanced($asAt),
                    'openPeriods' => (int) $this->pdo
                        ->query("SELECT COUNT(*) FROM tawasulFinancePeriod WHERE status='Open'")
                        ->fetchColumn(),
                    'postedEntries' => (int) $this->pdo
                        ->query("SELECT COUNT(*) FROM tawasulFinanceJournalEntry WHERE status='Posted'")
                        ->fetchColumn(),
                    'asAt' => $asAt,
                ]];

            default:
                throw ApiException::notFound(
                    'There is no "' . $report . '" accounting report. Use ' . implode(', ', self::REPORTS) . '.'
                );
        }
    }

    /**
     * Journal lines arrive either as lines[] or as {lines: {...}} keyed by index,
     * which is what a form-encoded body produces.
     */
    protected function readLines(array $body): array
    {
        $lines = $body['lines'] ?? null;

        if (!is_array($lines)) {
            throw ApiException::badRequest('Send the entry lines as "lines": [ ... ].', ['expected' => 'array']);
        }

        if (array_keys($lines) !== range(0, count($lines) - 1)) {
            // A keyed object rather than a list: keep the order it arrived in.
            $lines = array_values($lines);
        }

        if (count($lines) < 2) {
            throw ApiException::badRequest('A journal entry needs at least two lines.', ['received' => count($lines)]);
        }

        foreach ($lines as $i => $line) {
            if (!is_array($line)) {
                throw ApiException::badRequest('Line '.($i + 1).' is not an object.');
            }

            // A caller may send amount with a direction, which is friendlier than
            // making it pick the column. Exactly one of the two is set.
            if (!isset($line['debit']) && !isset($line['credit']) && isset($line['amount'])) {
                $direction = strtoupper((string) ($line['direction'] ?? 'debit'));
                if ($direction === 'CREDIT') {
                    $line['credit'] = $line['amount'];
                } elseif ($direction === 'DEBIT') {
                    $line['debit'] = $line['amount'];
                } else {
                    throw ApiException::badRequest(
                        'Line '.($i + 1).': direction must be DEBIT or CREDIT when an amount is sent.',
                        ['received' => $direction]
                    );
                }
            }

            $lines[$i] = [
                'tawasulFinanceAccountID' => $line['tawasulFinanceAccountID'] ?? $line['accountID'] ?? null,
                'tawasulFinanceCostCenterID' => $line['tawasulFinanceCostCenterID'] ?? null,
                'tawasulPersonID' => isset($line['tawasulPersonID']) ? (int) $line['tawasulPersonID'] : null,
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'memo' => $line['memo'] ?? null,
            ];
        }

        return $lines;
    }

    protected function requireDate(array $body): void
    {
        $date = (string) ($body['date'] ?? '');

        if ($date === '') {
            throw ApiException::badRequest('An entry needs a date, sent as "date" in YYYY-MM-DD form.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw ApiException::badRequest('The date must be in YYYY-MM-DD form.', ['received' => $date]);
        }
    }

    /**
     * Anything outside the sequences the gateway seeds is rejected here, so the
     * document number cannot be used to inject an unexpected label.
     */
    protected function documentType(array $body): string
    {
        $type = strtoupper((string) ($body['documentType'] ?? 'JV'));

        if (!preg_match('/^[A-Z]{2,6}$/', $type)) {
            throw ApiException::badRequest('documentType must be 2 to 6 letters.', ['received' => $type]);
        }

        return $type;
    }

    /**
     * The entry and its lines, shaped the way /journal-entries/{id} returns them,
     * so a caller can chain straight into a read without reshaping anything.
     */
    protected function entryPayload($id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tawasulFinanceJournalEntry.tawasulFinanceJournalEntryID, tawasulFinanceJournalEntry.documentNumber,
                    tawasulFinanceJournalEntry.documentType, tawasulFinanceJournalEntry.date,
                    tawasulFinanceJournalEntry.tawasulFinancePeriodID, tawasulFinanceJournalEntry.description,
                    tawasulFinanceJournalEntry.sourceType, tawasulFinanceJournalEntry.sourceID,
                    tawasulFinanceJournalEntry.status, tawasulFinanceJournalEntry.reversedEntryID,
                    tawasulFinanceJournalEntry.tawasulPersonIDCreator, tawasulFinanceJournalEntry.timestampCreator,
                    tawasulFinanceJournalEntry.tawasulPersonIDPoster, tawasulFinanceJournalEntry.timestampPoster,
                    tawasulFinancePeriod.name AS periodName
               FROM tawasulFinanceJournalEntry
               LEFT JOIN tawasulFinancePeriod
                 ON (tawasulFinanceJournalEntry.tawasulFinancePeriodID = tawasulFinancePeriod.tawasulFinancePeriodID)
              WHERE tawasulFinanceJournalEntry.tawasulFinanceJournalEntryID = :id'
        );
        $stmt->execute(['id' => $id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($entry)) {
            throw ApiException::notFound('No journal entry with id '.$id.'.');
        }

        $lines = $this->pdo->prepare(
            'SELECT l.tawasulFinanceJournalLineID, l.tawasulFinanceAccountID, l.tawasulFinanceCostCenterID,
                    l.tawasulPersonID, l.debit, l.credit, l.memo,
                    a.code AS accountCode, a.name AS accountName, a.type AS accountType,
                    c.code AS costCenterCode, c.name AS costCenterName
               FROM tawasulFinanceJournalLine l
               LEFT JOIN tawasulFinanceAccount a ON (l.tawasulFinanceAccountID = a.tawasulFinanceAccountID)
               LEFT JOIN tawasulFinanceCostCenter c ON (l.tawasulFinanceCostCenterID = c.tawasulFinanceCostCenterID)
              WHERE l.tawasulFinanceJournalEntryID = :id
              ORDER BY l.tawasulFinanceJournalLineID'
        );
        $lines->execute(['id' => $id]);
        $entry['lines'] = $lines->fetchAll(PDO::FETCH_ASSOC);

        return $entry;
    }

    protected function totals($id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(debit), 0) AS debit, COALESCE(SUM(credit), 0) AS credit
               FROM tawasulFinanceJournalLine WHERE tawasulFinanceJournalEntryID = :id'
        );
        $stmt->execute(['id' => $id]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'debit' => number_format((float) $totals['debit'], 2, '.', ''),
            'credit' => number_format((float) $totals['credit'], 2, '.', ''),
            'isBalanced' => round((float) $totals['debit'], 2) === round((float) $totals['credit'], 2),
        ];
    }

    protected function lineTotals(array $lines): array
    {
        $debit = $credit = 0.0;
        foreach ($lines as $line) {
            $debit += (float) ($line['debit'] ?? 0);
            $credit += (float) ($line['credit'] ?? 0);
        }

        return [
            'debit' => number_format($debit, 2, '.', ''),
            'credit' => number_format($credit, 2, '.', ''),
            'isBalanced' => round($debit, 2) === round($credit, 2),
        ];
    }

    protected function audit($id, string $action, array $data): void
    {
        $this->pdo->prepare(
            'INSERT INTO tawasulFinanceAuditLog
                (tableName, recordID, action, data, tawasulPersonID, timestamp)
             VALUES (:tableName, :recordID, :action, :data, :personID, NOW())'
        )->execute([
            'tableName' => 'tawasulFinanceJournalEntry',
            'recordID' => (int) $id,
            'action' => $action,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'personID' => $this->credential->getPersonID(),
        ]);
    }

    /**
     * TawasulFinance keeps its classes in its own namespace, and api.php only
     * registers the TawasulCore loader. Registering here keeps the knowledge of
     * where the accounting engine lives in one place instead of scattering
     * require statements through the API.
     */
    protected function financeConnection()
    {
        $module = self::financeModulePath();

        if ($module === null) {
            throw ApiException::unavailable('The TawasulFinance module is not installed, so accounting writes are unavailable.');
        }

        require_once $module.'/bootstrap.php';

        return new \Tos\Module\TawasulFinance\Support\ConnectionAdapter($this->pdo);
    }

    protected static function registerFinanceLoader(): void
    {
        if (self::$financeLoaderRegistered) {
            return;
        }

        $module = self::financeModulePath();

        if ($module === null) {
            return;
        }

        require_once $module.'/bootstrap.php';
        self::$financeLoaderRegistered = true;
    }

    /**
     * Where TawasulFinance keeps its classes, or null when it is not installed.
     */
    protected static function financeModulePath(): ?string
    {
        $module = dirname(__DIR__, 3).'/TawasulFinance/src';

        return is_readable($module.'/bootstrap.php') ? $module : null;
    }

    protected function journalGateway()
    {
        $connection = $this->financeConnection();

        $accounts = new \Tos\Module\TawasulFinance\Domain\AccountGateway($connection);
        $fiscalYears = new \Tos\Module\TawasulFinance\Domain\FiscalYearGateway($connection);

        return (new \Tos\Module\TawasulFinance\Domain\JournalGateway($connection))
            ->setDependencies($fiscalYears, $accounts);
    }

    protected function ledgerGateway()
    {
        $connection = $this->financeConnection();

        return new \Tos\Module\TawasulFinance\Domain\LedgerReportGateway($connection);
    }
}
