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
*/

namespace TawasulOS\Domain\Staff;

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\ScrubbableGateway;
use TawasulOS\Domain\Traits\Scrubbable;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\Traits\ScrubByPerson;

/**
 * Staff Absence Gateway
 *
 * @version v18
 * @since   v18
 */
class StaffAbsenceGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByPerson;
    
    private static $tableName = 'tawasulStaffAbsence';
    private static $primaryKey = 'tawasulStaffAbsenceID';

    private static $searchableColumns = ['tawasulStaffAbsence.reason', 'tawasulStaffAbsence.comment', 'tawasulStaffAbsence.status', 'tawasulStaffAbsenceType.name', 'tawasulPerson.preferredName', 'tawasulPerson.surname'];

    private static $scrubbableKey = 'tawasulPersonID';
    private static $scrubbableColumns = ['commentConfidential' => ''];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAbsencesBySchoolYear($criteria, $tawasulSchoolYearID, $grouped = true)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulStaffAbsence.*', 'tawasulStaffAbsenceDate.*', 'tawasulStaffAbsenceType.name as type', 'tawasulPerson.tawasulPersonID', 'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'creator.preferredName AS preferredNameCreator', 'creator.surname AS surnameCreator'
            ])
            ->innerJoin('tawasulStaffAbsenceType', 'tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID')
            ->innerJoin('tawasulStaffAbsenceDate', 'tawasulStaffAbsenceDate.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID')
            ->innerJoin('tawasulSchoolYear', '((tawasulStaffAbsenceDate.date BETWEEN firstDay AND lastDay) OR (tawasulStaffAbsence.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID))')
            // ->leftJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverageDate.tawasulStaffAbsenceDateID=tawasulStaffAbsenceDate.tawasulStaffAbsenceDateID')
            // ->leftJoin('tawasulStaffCoverage', 'tawasulStaffCoverage.tawasulStaffCoverageID=tawasulStaffCoverageDate.tawasulStaffCoverageID')
            ->leftJoin('tawasulPerson', 'tawasulStaffAbsence.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulPerson AS creator', 'tawasulStaffAbsence.tawasulPersonIDCreator=creator.tawasulPersonID')
            ->where('tawasulSchoolYear.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        if ($grouped) {
            $query->cols(['COUNT(DISTINCT tawasulStaffAbsenceDate.date) as days', 'MIN(tawasulStaffAbsenceDate.date) as dateStart', 'MAX(tawasulStaffAbsenceDate.date) as dateEnd', 'SUM(tawasulStaffAbsenceDate.value) as value'])
                ->groupBy(['tawasulStaffAbsence.tawasulStaffAbsenceID']);
        } else {
            $query->cols(['1 as days', 'tawasulStaffAbsenceDate.date as dateStart', 'tawasulStaffAbsenceDate.date as dateEnd', 'tawasulStaffAbsenceDate.value as value'])
                ->groupBy(['tawasulStaffAbsenceDate.tawasulStaffAbsenceDateID']);
        }

        $criteria->addFilterRules($this->getSharedFilterRules());

        return $this->runQuery($query, $criteria);
    }

    public function queryAbsencesByPerson(QueryCriteria $criteria, $tawasulPersonID, $grouped = true)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulStaffAbsence.tawasulStaffAbsenceID', 'tawasulStaffAbsence.tawasulPersonID', 'tawasulStaffAbsenceType.name as type', 'tawasulStaffAbsence.reason', 'comment', 'tawasulStaffAbsenceDate.date', 'tawasulStaffAbsenceDate.allDay', 'tawasulStaffAbsenceDate.timeStart', 'tawasulStaffAbsenceDate.timeEnd', 'timestampCreator', 'tawasulStaffAbsence.coverageRequired', 'tawasulStaffCoverage.status as coverage', 'tawasulStaffAbsence.status',
                'creator.title as titleCreator', 'creator.preferredName AS preferredNameCreator', 'creator.surname AS surnameCreator', 'tawasulStaffAbsence.tawasulPersonIDCreator',
                'coverage.title as titleCoverage', 'coverage.preferredName as preferredNameCoverage', 'coverage.surname as surnameCoverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage', 'tawasulStaffCoverage.tawasulStaffCoverageID', 'tawasulStaffCoverageDate.foreignTableID AS tawasulTTDayRowClassID'
            ])
            ->innerJoin('tawasulStaffAbsenceType', 'tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID')
            ->innerJoin('tawasulStaffAbsenceDate', 'tawasulStaffAbsenceDate.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID')
            ->leftJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverageDate.tawasulStaffAbsenceDateID=tawasulStaffAbsenceDate.tawasulStaffAbsenceDateID')
            ->leftJoin('tawasulStaffCoverage', 'tawasulStaffCoverage.tawasulStaffCoverageID=tawasulStaffCoverageDate.tawasulStaffCoverageID')
            ->leftJoin('tawasulPerson AS creator', 'tawasulStaffAbsence.tawasulPersonIDCreator=creator.tawasulPersonID')
            ->leftJoin('tawasulPerson AS coverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID')
            ->where('tawasulStaffAbsence.tawasulPersonID = :tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID);

        if ($grouped === true) {
            $query->cols(['COUNT(DISTINCT tawasulStaffAbsenceDate.date) as days', 'MIN(tawasulStaffAbsenceDate.date) as dateStart', 'MAX(tawasulStaffAbsenceDate.date) as dateEnd', 'SUM(tawasulStaffAbsenceDate.value) as value'])
                ->groupBy(['tawasulStaffAbsence.tawasulStaffAbsenceID']);
        } elseif ($grouped === 'coverage') {
            $query->cols(['COUNT(DISTINCT tawasulStaffAbsenceDate.date) as days', 'MIN(tawasulStaffAbsenceDate.date) as dateStart', 'MAX(tawasulStaffAbsenceDate.date) as dateEnd', 'SUM(tawasulStaffAbsenceDate.value) as value'])
                ->groupBy(['tawasulStaffAbsence.tawasulStaffAbsenceID', 'tawasulStaffAbsenceDate.tawasulStaffAbsenceDateID']);
        } else {
            $query->cols(['1 as days', 'tawasulStaffAbsenceDate.date as dateStart', 'tawasulStaffAbsenceDate.date as dateEnd', 'tawasulStaffAbsenceDate.value as value'])
                ->groupBy(['tawasulStaffAbsenceDate.tawasulStaffAbsenceDateID']);
        }

        $criteria->addFilterRules($this->getSharedFilterRules());

        $criteria->addFilterRules([
            'schoolYear' => function ($query, $tawasulSchoolYearID) {
                return $query
                    ->where('tawasulStaffAbsence.tawasulSchoolYearID = :tawasulSchoolYearID')
                    ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);
            }
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryAbsencesByApprover(QueryCriteria $criteria, $tawasulPersonIDApproval)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulStaffAbsence.tawasulStaffAbsenceID', 'tawasulStaffAbsenceType.name as type', 'tawasulStaffAbsence.reason', 'comment', 'tawasulStaffAbsenceDate.date', 'COUNT(DISTINCT tawasulStaffAbsenceDate.date) as days', 'MIN(tawasulStaffAbsenceDate.date) as dateStart', 'MAX(tawasulStaffAbsenceDate.date) as dateEnd', 'tawasulStaffAbsenceDate.allDay', 'tawasulStaffAbsenceDate.timeStart', 'tawasulStaffAbsenceDate.timeEnd', 'SUM(tawasulStaffAbsenceDate.value) as value', 'timestampCreator', 'tawasulPerson.tawasulPersonID', 'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulStaffAbsence.tawasulPersonIDCreator', 'creator.preferredName AS preferredNameCreator', 'creator.surname AS surnameCreator', 'tawasulStaffCoverage.status as coverage', 'tawasulStaffAbsence.status', 'tawasulStaffAbsence.coverageRequired', 'tawasulStaffCoverageDate.foreignTable', 'tawasulStaffCoverageDate.foreignTableID'
            ])
            ->innerJoin('tawasulStaffAbsenceType', 'tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID')
            ->innerJoin('tawasulStaffAbsenceDate', 'tawasulStaffAbsenceDate.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID')
            ->leftJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverageDate.tawasulStaffAbsenceDateID=tawasulStaffAbsenceDate.tawasulStaffAbsenceDateID')
            ->leftJoin('tawasulStaffCoverage', 'tawasulStaffCoverage.tawasulStaffCoverageID=tawasulStaffCoverageDate.tawasulStaffCoverageID')
            ->leftJoin('tawasulPerson', 'tawasulStaffAbsence.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulPerson AS creator', 'tawasulStaffAbsence.tawasulPersonIDCreator=creator.tawasulPersonID')
            ->where('tawasulStaffAbsence.tawasulPersonIDApproval = :tawasulPersonIDApproval')
            ->bindValue('tawasulPersonIDApproval', $tawasulPersonIDApproval)
            ->groupBy(['tawasulStaffAbsence.tawasulStaffAbsenceID']);

        $criteria->addFilterRules($this->getSharedFilterRules());

        return $this->runQuery($query, $criteria);
    }

    public function queryApprovedAbsencesByDateRange(QueryCriteria $criteria, $dateStart, $dateEnd = null, $grouped = true)
    {
        if (empty($dateEnd)) $dateEnd = $dateStart;
        
        $query = $this
            ->newQuery()
            ->from('tawasulStaffAbsenceDate')
            ->cols([
                'tawasulStaffAbsence.tawasulStaffAbsenceID', 'tawasulStaffAbsence.tawasulPersonID', 'tawasulStaffAbsenceType.name as type', 'tawasulStaffAbsence.reason', 'comment', 'tawasulStaffAbsenceDate.date',  'tawasulStaffAbsenceDate.allDay', 'tawasulStaffAbsenceDate.timeStart', 'tawasulStaffAbsenceDate.timeEnd', 'tawasulStaffAbsenceDate.value', 'timestampCreator',  'MIN(tawasulStaffCoverage.status) as coverage',
                'tawasulStaffAbsence.status',
                'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 
                'creator.title AS titleCreator', 'creator.preferredName AS preferredNameCreator', 'creator.surname AS surnameCreator', 'tawasulStaffAbsence.tawasulPersonIDCreator',
                'coverage.title as titleCoverage', 'coverage.preferredName as preferredNameCoverage', 'coverage.surname as surnameCoverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage',
            ])
            ->innerJoin('tawasulStaffAbsence', 'tawasulStaffAbsence.tawasulStaffAbsenceID=tawasulStaffAbsenceDate.tawasulStaffAbsenceID')
            ->innerJoin('tawasulStaffAbsenceType', 'tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID')
            ->leftJoin('tawasulStaffAbsenceDate AS dates', 'dates.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID')
            ->leftJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverageDate.tawasulStaffAbsenceDateID=tawasulStaffAbsenceDate.tawasulStaffAbsenceDateID')
            ->leftJoin('tawasulStaffCoverage', 'tawasulStaffCoverage.tawasulStaffCoverageID=tawasulStaffCoverageDate.tawasulStaffCoverageID')
            ->leftJoin('tawasulPerson', 'tawasulStaffAbsence.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulPerson AS creator', 'tawasulStaffAbsence.tawasulPersonIDCreator=creator.tawasulPersonID')
            ->leftJoin('tawasulPerson AS coverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID')
            ->where('tawasulStaffAbsenceDate.date BETWEEN :dateStart AND :dateEnd')
            ->where("tawasulStaffAbsence.status = 'Approved'")
            ->bindValue('dateStart', $dateStart)
            ->bindValue('dateEnd', $dateEnd)
            ->groupBy(['tawasulStaffAbsence.tawasulStaffAbsenceID', 'tawasulStaffCoverageDate.tawasulStaffCoverageDateID']);

        if ($grouped) {
            $query->cols(['COUNT(DISTINCT dates.tawasulStaffAbsenceDateID) as days', 'MIN(dates.date) as dateStart', 'MAX(dates.date) as dateEnd', 'SUM(value) as value'])
                ->groupBy(['tawasulStaffAbsence.tawasulStaffAbsenceID']);
        } else {
            $query->cols(['1 as days', 'tawasulStaffAbsenceDate.date as dateStart', 'tawasulStaffAbsenceDate.date as dateEnd', 'tawasulStaffAbsenceDate.value as value'])
                ->groupBy(['tawasulStaffAbsenceDate.tawasulStaffAbsenceDateID']);
        }

        if (!$criteria->hasFilter('all')) {
            $query->where('tawasulPerson.status = "Full"');
        }

        $criteria->addFilterRules($this->getSharedFilterRules());

        return $this->runQuery($query, $criteria);
    }

    public function getAbsenceDetailsByID($tawasulStaffAbsenceID)
    {
        $data = ['tawasulStaffAbsenceID' => $tawasulStaffAbsenceID];
        $sql = "SELECT tawasulStaffAbsence.tawasulStaffAbsenceID, tawasulStaffAbsence.tawasulStaffAbsenceID, tawasulStaffAbsenceType.name as type, tawasulStaffAbsenceType.sequenceNumber, tawasulStaffAbsence.tawasulStaffAbsenceTypeID, tawasulStaffAbsence.reason, tawasulStaffAbsence.comment, tawasulStaffAbsence.commentConfidential, tawasulStaffAbsence.coverageRequired,
                MIN(tawasulStaffAbsenceDate.date) as date, COUNT(DISTINCT tawasulStaffAbsenceDateID) as days, MIN(tawasulStaffAbsenceDate.date) as dateStart, MAX(tawasulStaffAbsenceDate.date) as dateEnd, MAX(tawasulStaffAbsenceDate.allDay) as allDay, MIN(tawasulStaffAbsenceDate.timeStart) as timeStart, MAX(tawasulStaffAbsenceDate.timeEnd) as timeEnd, 0 as urgent, tawasulStaffAbsenceDate.value as value,
                tawasulStaffAbsence.status, tawasulStaffAbsence.timestampApproval, tawasulStaffAbsence.notesApproval,
                tawasulPersonIDCreator, timestampCreator, timestampStatus, timestampCoverage, tawasulStaffAbsence.notificationList, tawasulStaffAbsence.notificationSent, tawasulStaffAbsence.tawasulGroupID, tawasulStaffAbsence.googleCalendarEventID,
                tawasulStaffCoverage.status as coverage, tawasulStaffCoverage.notesCoverage, tawasulStaffCoverage.notesStatus, 
                tawasulStaffAbsence.tawasulPersonID, absence.title AS titleAbsence, absence.preferredName AS preferredNameAbsence, absence.surname AS surnameAbsence, 
                tawasulStaffAbsence.tawasulPersonIDApproval, approval.title as titleApproval, approval.preferredName as preferredNameApproval, approval.surname as surnameApproval,
                tawasulStaffCoverage.tawasulPersonIDCoverage, coverage.title as titleCoverage, coverage.preferredName as preferredNameCoverage, coverage.surname as surnameCoverage
            FROM tawasulStaffAbsence 
            JOIN tawasulStaffAbsenceType ON (tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID)
            LEFT JOIN tawasulStaffAbsenceDate ON (tawasulStaffAbsenceDate.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID)
            LEFT JOIN tawasulStaffCoverage ON (tawasulStaffCoverage.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID)
            LEFT JOIN tawasulPerson AS absence ON (tawasulStaffAbsence.tawasulPersonID=absence.tawasulPersonID)
            LEFT JOIN tawasulPerson AS coverage ON (tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID)
            LEFT JOIN tawasulPerson AS approval ON (tawasulStaffAbsence.tawasulPersonIDApproval=approval.tawasulPersonID)
            WHERE tawasulStaffAbsence.tawasulStaffAbsenceID=:tawasulStaffAbsenceID
            GROUP BY tawasulStaffAbsence.tawasulStaffAbsenceID
            ";

        return $this->db()->selectOne($sql, $data);
    }

    public function getMostRecentAbsenceByPerson($tawasulPersonID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT * 
                FROM tawasulStaffAbsence 
                WHERE tawasulStaffAbsence.tawasulPersonID=:tawasulPersonID
                ORDER BY timestampCreator DESC
                LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }

    public function getMostRecentApproverByPerson($tawasulPersonID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulPersonIDApproval 
                FROM tawasulStaffAbsence 
                WHERE tawasulStaffAbsence.tawasulPersonID=:tawasulPersonID
                AND tawasulPersonIDApproval IS NOT NULL
                ORDER BY timestampCreator DESC
                LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }

    protected function getSharedFilterRules()
    {
        return [
            'type' => function ($query, $type) {
                return $query
                    ->where('tawasulStaffAbsence.tawasulStaffAbsenceTypeID = :type')
                    ->bindValue('type', $type);
            },
            'status' => function ($query, $status) {
                return $query->where('tawasulStaffAbsence.status = :status')
                             ->bindValue('status', ucwords($status));
            },
            'coverage' => function ($query, $coverage) {
                return $query->where('tawasulStaffCoverage.status = :coverage')
                             ->bindValue('coverage', $coverage);
            },
            'dateStart' => function ($query, $dateStart) {
                return $query->where("tawasulStaffAbsenceDate.date >= :dateStart")
                             ->bindValue('dateStart', $dateStart);
            },
            'dateEnd' => function ($query, $dateEnd) {
                return $query->where("tawasulStaffAbsenceDate.date <= :dateEnd")
                             ->bindValue('dateEnd', $dateEnd);
            },
            'date' => function ($query, $date) {
                switch (ucfirst($date)) {
                    case 'Upcoming': return $query->where("tawasulStaffAbsenceDate.date >= CURRENT_DATE()");
                    case 'Today'   : return $query->where("tawasulStaffAbsenceDate.date = CURRENT_DATE()");
                    case 'Past'    : return $query->where("tawasulStaffAbsenceDate.date < CURRENT_DATE()");
                }
            },
        ];
    }
}
