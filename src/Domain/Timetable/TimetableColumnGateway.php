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

namespace TawasulOS\Domain\Timetable;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v25
 * @since   v16
 */
class TimetableColumnGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulTTColumn';
    private static $primaryKey = 'tawasulTTColumnID';

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryTTColumns(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulTTColumn.tawasulTTColumnID', 'tawasulTTColumn.name', 'tawasulTTColumn.nameShort', 'COUNT(tawasulTTColumnRowID) as rowCount',
            ])
            ->leftJoin('tawasulTTColumnRow', 'tawasulTTColumnRow.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID')
            ->groupBy(['tawasulTTColumn.tawasulTTColumnID']);

        return $this->runQuery($query, $criteria);
    }

    public function selectTTColumns()
    {
        $sql = "SELECT tawasulTTColumn.tawasulTTColumnID, tawasulTTColumn.name, tawasulTTColumn.nameShort, COUNT(tawasulTTColumnRowID) as rowCount
                FROM tawasulTTColumn
                LEFT JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID)
                GROUP BY tawasulTTColumn.tawasulTTColumnID
                ORDER BY tawasulTTColumn.name";

        return $this->db()->select($sql);
    }

    public function selectTTColumnsByTimetable($tawasulTTID)
    {
        $data = array('tawasulTTID' => $tawasulTTID);
        $sql = "SELECT CONCAT(tawasulTTColumnRow.tawasulTTColumnRowID, '-', tawasulTTDay.tawasulTTDayID) as value, tawasulTTColumnRow.name, tawasulTTDay.tawasulTTDayID
                FROM tawasulTTDay
                JOIN tawasulTTColumnRow ON (tawasulTTDay.tawasulTTColumnID=tawasulTTColumnRow.tawasulTTColumnID)
                WHERE tawasulTTDay.tawasulTTID=:tawasulTTID
                GROUP BY value
                ORDER BY tawasulTTColumnRow.timeStart
        ";

        return $this->db()->select($sql, $data);
    }

    public function selectTTColumnsByDateRange($tawasulTTID, $dateStart, $dateEnd)
    {
        $data = ['tawasulTTID' => $tawasulTTID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT tawasulTTColumnRow.name as title, tawasulTTColumnRow.nameShort as subtitle, tawasulTTColumnRow.type, tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd, tawasulTTDayDate.date
                FROM tawasulTTDay 
                JOIN tawasulTTDayDate ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID) 
                JOIN tawasulTTColumn ON (tawasulTTDay.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID) 
                JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID) 
                WHERE tawasulTTDayDate.date >= :dateStart 
                AND tawasulTTDayDate.date <= :dateEnd
                AND tawasulTTDay.tawasulTTID=:tawasulTTID
                ORDER BY tawasulTTDayDate.date, tawasulTTColumnRow.timeStart";

        return $this->db()->select($sql, $data);
    }

    public function getTTColumnByID($tawasulTTColumnID)
    {
        $data = array('tawasulTTColumnID' => $tawasulTTColumnID);
        $sql = "SELECT tawasulTTColumnID, name, nameShort FROM tawasulTTColumn WHERE tawasulTTColumnID=:tawasulTTColumnID";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectTTColumnRowsByID($tawasulTTColumnID)
    {
        $data = array('tawasulTTColumnID' => $tawasulTTColumnID);
        $sql = "SELECT tawasulTTColumnRowID, name, nameShort, timeStart, timeEnd, type
                FROM tawasulTTColumnRow
                WHERE tawasulTTColumnID=:tawasulTTColumnID
                ORDER BY timeStart, name";

        return $this->db()->select($sql, $data);
    }

    public function insertColumnRow(array $data)
    {
        $sql = "INSERT INTO tawasulTTColumnRow SET tawasulTTColumnID=:tawasulTTColumnID, name=:name, nameShort=:nameShort, timeStart=:timeStart, timeEnd=:timeEnd, type=:type ON DUPLICATE KEY UPDATE tawasulTTColumnID=:tawasulTTColumnID";

        return $this->db()->insert($sql, $data);
    }
}
