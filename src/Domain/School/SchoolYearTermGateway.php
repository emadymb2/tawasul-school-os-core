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

namespace TawasulOS\Domain\School;

use TawasulOS\Contracts\Database\Result;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * School Year Term Gateway
 *
 * @version v25
 * @since   v17
 */
class SchoolYearTermGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulSchoolYearTerm';
    private static $primaryKey = 'tawasulSchoolYearTermID';

    public function querySchoolYearTerms(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulSchoolYearTerm.tawasulSchoolYearTermID',
                'tawasulSchoolYear.tawasulSchoolYearID',
                'tawasulSchoolYearTerm.name',
                'tawasulSchoolYearTerm.nameShort',
                'tawasulSchoolYearTerm.sequenceNumber',
                'tawasulSchoolYear.sequenceNumber AS schoolYearSequence',
                'tawasulSchoolYearTerm.firstDay',
                'tawasulSchoolYearTerm.lastDay',
                'tawasulSchoolYear.name AS schoolYearName',
                "(CASE WHEN NOW() BETWEEN tawasulSchoolYearTerm.firstDay AND tawasulSchoolYearTerm.lastDay THEN 'Current' ELSE '' END) as status"
            ])
            ->innerJoin('tawasulSchoolYear', 'tawasulSchoolYear.tawasulSchoolYearID=tawasulSchoolYearTerm.tawasulSchoolYearID');

        $criteria->addFilterRules([
            'schoolYear' => function ($query, $tawasulSchoolYearID) {
                return $query
                    ->where('tawasulSchoolYearTerm.tawasulSchoolYearID=:tawasulSchoolYearID')
                    ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);
            },
            'firstDay' => function ($query, $firstDay) {
                return $query
                    ->where('tawasulSchoolYearTerm.firstDay <= :firstDay')
                    ->bindValue('firstDay', $firstDay);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectSchoolClosuresByTerm($tawasulSchoolYearTermID, $grouped = false)
    {
        $tawasulSchoolYearTermIDList = !is_array($tawasulSchoolYearTermID) ? $tawasulSchoolYearTermID : implode(',', $tawasulSchoolYearTermID);
        $data = array('tawasulSchoolYearTermIDList' => $tawasulSchoolYearTermIDList);
        if ($grouped) {
            $sql = "SELECT MIN(date) as groupBy, name, type, MIN(date) as firstDay, MAX(date) as lastDay
                FROM tawasulSchoolYearSpecialDay
                WHERE FIND_IN_SET(tawasulSchoolYearTermID, :tawasulSchoolYearTermIDList)
                AND type='School Closure'
                GROUP BY name
                ORDER BY date";
        } else {
            $sql = "SELECT date, name
                FROM tawasulSchoolYearSpecialDay
                WHERE FIND_IN_SET(tawasulSchoolYearTermID, :tawasulSchoolYearTermIDList)
                AND type='School Closure'
                ORDER BY date";
        }
        
        return $this->db()->select($sql, $data);
    }

     public function selectOffTimetablesByTerm($tawasulSchoolYearTermID, $grouped = false)
    {
        $tawasulSchoolYearTermIDList = !is_array($tawasulSchoolYearTermID) ? $tawasulSchoolYearTermID : implode(',', $tawasulSchoolYearTermID);
        $data = array('tawasulSchoolYearTermIDList' => $tawasulSchoolYearTermIDList);
        if ($grouped) {
            $sql = "SELECT MIN(date) as groupBy, name, type, MIN(date) as firstDay, MAX(date) as lastDay
                FROM tawasulSchoolYearSpecialDay
                WHERE FIND_IN_SET(tawasulSchoolYearTermID, :tawasulSchoolYearTermIDList)
                AND type='Off Timetable'
                GROUP BY name
                ORDER BY date";
        } else {
            $sql = "SELECT date, name, type
                FROM tawasulSchoolYearSpecialDay
                WHERE FIND_IN_SET(tawasulSchoolYearTermID, :tawasulSchoolYearTermIDList)
                AND type='Off Timetable'
                ORDER BY date";
        }
        
        return $this->db()->select($sql, $data);
    }

    public function selectOffTimetableDaysByTermAndPerson($tawasulSchoolYearTermID, $tawasulPersonID)
    {
        $data = ['tawasulSchoolYearTermID' => $tawasulSchoolYearTermID, 'tawasulPersonID' => $tawasulPersonID];

        $sql = "SELECT tawasulSchoolYearSpecialDay.date, tawasulSchoolYearSpecialDay.name
            FROM tawasulSchoolYearSpecialDay
            JOIN tawasulSchoolYearTerm ON (tawasulSchoolYearTerm.tawasulSchoolYearTermID=tawasulSchoolYearSpecialDay.tawasulSchoolYearTermID)
            JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulSchoolYearTerm.tawasulSchoolYearID)
            WHERE tawasulSchoolYearSpecialDay.tawasulSchoolYearTermID=:tawasulSchoolYearTermID
            AND tawasulSchoolYearSpecialDay.type='Off Timetable'
            AND tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID
            AND (FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, tawasulSchoolYearSpecialDay.tawasulYearGroupIDList) OR FIND_IN_SET(tawasulStudentEnrolment.tawasulFormGroupID, tawasulSchoolYearSpecialDay.tawasulFormGroupIDList))
            ORDER BY tawasulSchoolYearSpecialDay.date";

        return $this->db()->select($sql, $data);
    }

    public function getTermsDatesByDateRange($dateStart, $dateEnd)
    {
        $data = ['dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT  MIN(firstDay) as firstDay, MAX(lastDay) as lastDay
                FROM tawasulSchoolYearTerm
                WHERE (:dateStart BETWEEN firstDay AND lastDay) OR (:dateEnd BETWEEN firstDay AND lastDay)
                GROUP BY tawasulSchoolYearID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getCurrentTermByDate($date)
    {
        $data = array('date' => $date);
        $sql = "SELECT tawasulSchoolYearTermID, tawasulSchoolYearID, name, sequenceNumber, firstDay, lastDay
                FROM tawasulSchoolYearTerm
                WHERE firstDay<=:date AND lastDay>=:date
                LIMIT 0, 1";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Select a list of school year term ID and names in the specified school year.
     *
     * @param integer $tawasulSchoolYearID  The ID of the school year.
     *
     * @return Result
     */
    public function selectTermsBySchoolYear(int $tawasulSchoolYearID): Result
    {
        $sql = 'SELECT tawasulSchoolYearTermID, name FROM tawasulSchoolYearTerm WHERE tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY sequenceNumber';
        return $this->db()->select($sql, ['tawasulSchoolYearID' => $tawasulSchoolYearID]);
    }

    /**
     * Select a full list of school year term fields in the specified school year.
     *
     * @param integer $tawasulSchoolYearID  The ID of the school year.
     *
     * @return Result
     */
    public function selectTermDetailsBySchoolYear(int $tawasulSchoolYearID): Result
    {
        $sql = 'SELECT tawasulSchoolYearTermID as groupBy, tawasulSchoolYearTerm.* FROM tawasulSchoolYearTerm WHERE tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY sequenceNumber';
        return $this->db()->select($sql, ['tawasulSchoolYearID' => $tawasulSchoolYearID]);
    }

    /**
     * Get a list of school year term names based on an ID or list of IDs.
     *
     * @param string|array $tawasulSchoolYearTermID  The IDs of the school year terms.
     *
     * @return array
     */
    public function getTermNamesByID($tawasulSchoolYearTermID): array
    {
        $sql = 'SELECT name FROM tawasulSchoolYearTerm WHERE FIND_IN_SET(tawasulSchoolYearTermID, :tawasulSchoolYearTermIDList) ORDER BY sequenceNumber';
        return $this->db()->select($sql, [
            'tawasulSchoolYearTermIDList' => is_array($tawasulSchoolYearTermID)? implode(',', $tawasulSchoolYearTermID) : $tawasulSchoolYearTermID,
        ])->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

}
