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

TawasulOS — Fiscal year and accounting period gateway.
*/

namespace Tos\Module\TawasulFinance\Domain;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;

/**
 * FiscalYear Gateway
 *
 * A fiscal year spans a date range and holds the accounting periods (typically
 * months) that transactions are posted into. Posting is only permitted into a
 * period whose status is Open, which is what stops a closed month being edited
 * after the fact.
 */
class FiscalYearGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulFinanceFiscalYear';
    private static $primaryKey = 'tawasulFinanceFiscalYearID';

    private static $searchableColumns = ['name'];

    public const STATUS_OPEN = 'Open';
    public const STATUS_CLOSED = 'Closed';
    public const STATUS_LOCKED = 'Locked';

    public const PERIOD_OPEN = 'Open';
    public const PERIOD_CLOSED = 'Closed';

    /**
     * Paginated fiscal year listing, newest first.
     */
    public function queryFiscalYears(QueryCriteria $criteria) : \TawasulOS\Domain\DataSet
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulFinanceFiscalYearID',
                'name',
                'firstDay',
                'lastDay',
                'status',
            ])
            ->orderBy(['firstDay DESC']);

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('status = :status')
                    ->bindValue('status', $status);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * All fiscal years ordered for a select box.
     */
    public function selectAll() : array
    {
        return $this->db()->select(
            'SELECT tawasulFinanceFiscalYearID, name, firstDay, lastDay, status
               FROM tawasulFinanceFiscalYear
              ORDER BY firstDay DESC'
        )->fetchAll();
    }

    public function getYear($tawasulFinanceFiscalYearID) : array
    {
        return $this->getByID($tawasulFinanceFiscalYearID) ?: [];
    }

    /**
     * The fiscal year containing a date, if one has been set up.
     */
    public function selectYearForDate($date) : array
    {
        return $this->db()->selectOne(
            'SELECT tawasulFinanceFiscalYearID, name, firstDay, lastDay, status
               FROM tawasulFinanceFiscalYear
              WHERE firstDay <= :date AND lastDay >= :date
              ORDER BY firstDay DESC
              LIMIT 1',
            ['date' => $date]
        ) ?: [];
    }

    /**
     * Create a fiscal year along with its periods.
     *
     * Periods are generated as whole months clipped to the year boundaries, so a
     * year that does not start on the 1st still produces a correct first and
     * last period.
     *
     * @param string $firstDay YYYY-MM-DD
     * @param string $lastDay  YYYY-MM-DD
     * @return int The new fiscal year ID.
     */
    public function createYearWithPeriods(string $name, string $firstDay, string $lastDay) : int
    {
        $this->db()->beginTransaction();

        try {
            $query = $this
                ->newInsert()
                ->into($this->getTableName())
                ->cols([
                    'name' => $name,
                    'firstDay' => $firstDay,
                    'lastDay' => $lastDay,
                    'status' => self::STATUS_OPEN,
                ]);
            $this->runInsert($query);
            $yearID = (int) $this->db()->getConnection()->lastInsertId();

            foreach ($this->buildMonths($firstDay, $lastDay) as $m) {
                // A period with no length would make date resolution ambiguous.
                if ($m['startDate'] > $m['endDate']) {
                    throw new \RuntimeException('Period '.$m['name'].' has an invalid date range.');
                }

                $periodQuery = $this
                    ->newInsert()
                    ->into('tawasulFinancePeriod')
                    ->cols([
                        'tawasulFinanceFiscalYearID' => $yearID,
                        'name' => $m['name'],
                        'startDate' => $m['startDate'],
                        'endDate' => $m['endDate'],
                        'status' => self::PERIOD_OPEN,
                    ]);
                $this->runInsert($periodQuery);
            }

            $this->db()->commit();
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            throw $e;
        }

        return $yearID;
    }

    /**
     * Split a date range into month-long periods, clipped to the range ends.
     *
     * @return array{name:string,startDate:string,endDate:string}[]
     */
    private function buildMonths(string $firstDay, string $lastDay) : array
    {
        $months = [];
        $cursor = new \DateTimeImmutable($firstDay);
        $end = new \DateTimeImmutable($lastDay);

        while ($cursor <= $end) {
            $monthStart = $cursor->modify('first day of this month');
            $monthEnd = $cursor->modify('last day of this month');

            // Clip to the year boundaries.
            if ($monthStart < new \DateTimeImmutable($firstDay)) {
                $monthStart = new \DateTimeImmutable($firstDay);
            }
            if ($monthEnd > $end) {
                $monthEnd = $end;
            }

            $months[] = [
                'name' => $cursor->format('F Y'),
                'startDate' => $monthStart->format('Y-m-d'),
                'endDate' => $monthEnd->format('Y-m-d'),
            ];

            $cursor = $cursor->modify('+1 month');
        }

        return $months;
    }

    /**
     * Periods for a fiscal year, ordered by start date.
     */
    public function selectPeriods($tawasulFinanceFiscalYearID) : array
    {
        return $this->db()->select(
            'SELECT tawasulFinancePeriodID, name, startDate, endDate, status
               FROM tawasulFinancePeriod
              WHERE tawasulFinanceFiscalYearID = :year
              ORDER BY startDate',
            ['year' => $tawasulFinanceFiscalYearID]
        )->fetchAll();
    }

    public function getPeriod($tawasulFinancePeriodID) : array
    {
        return $this->db()->selectOne(
            'SELECT tawasulFinancePeriodID, tawasulFinanceFiscalYearID, name, startDate, endDate, status
               FROM tawasulFinancePeriod
              WHERE tawasulFinancePeriodID = :id',
            ['id' => $tawasulFinancePeriodID]
        ) ?: [];
    }

    /**
     * Resolve the period that a given date falls into.
     *
     * Returns an empty array when no period covers the date, which the posting
     * engine treats as an error rather than defaulting to an open period: a
     * transaction must never silently land in the wrong month.
     */
    public function selectPeriodForDate($date) : array
    {
        return $this->db()->selectOne(
            'SELECT p.tawasulFinancePeriodID, p.tawasulFinanceFiscalYearID, p.name, p.startDate, p.endDate, p.status,
                    f.name as fiscalYearName
               FROM tawasulFinancePeriod p
          LEFT JOIN tawasulFinanceFiscalYear f
                 ON f.tawasulFinanceFiscalYearID = p.tawasulFinanceFiscalYearID
              WHERE p.startDate <= :date AND p.endDate >= :date
              ORDER BY p.startDate DESC
              LIMIT 1',
            ['date' => $date]
        ) ?: [];
    }

    /**
     * Open a period or close it again.
     */
    public function setPeriodStatus($tawasulFinancePeriodID, string $status) : bool
    {
        $query = $this
            ->newUpdate()
            ->table($this->getTableName())
            ->cols(['status' => $status])
            ->where('tawasulFinancePeriodID = :id')
            ->bindValue('id', $tawasulFinancePeriodID);

        return $this->runUpdate($query);
    }

    /**
     * Close every period in a fiscal year, used when the year itself is closed.
     */
    public function closeAllPeriods($tawasulFinanceFiscalYearID) : bool
    {
        $query = $this
            ->newUpdate()
            ->table('tawasulFinancePeriod')
            ->cols(['status' => self::PERIOD_CLOSED])
            ->where('tawasulFinanceFiscalYearID = :year')
            ->where('status = :open')
            ->bindValue('year', $tawasulFinanceFiscalYearID)
            ->bindValue('open', self::PERIOD_OPEN);

        return $this->runUpdate($query);
    }

    /**
     * Whether a period still has posted entries. A period with postings cannot
     * be reopened casually, because doing so would let a closed month change.
     */
    public function countPostedEntries($tawasulFinancePeriodID) : int
    {
        $row = $this->db()->selectOne(
            "SELECT COUNT(*) AS c FROM tawasulFinanceJournalEntry
              WHERE tawasulFinancePeriodID = :period AND status = 'Posted'",
            ['period' => $tawasulFinancePeriodID]
        );

        return (int) ($row['c'] ?? 0);
    }
}