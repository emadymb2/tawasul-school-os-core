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
class TimetableDayGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulTTDay';
    private static $primaryKey = 'tawasulTTDayID';

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryTTDays(QueryCriteria $criteria, $tawasulTTID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulTTDay.*','tawasulTTColumn.name AS columnName'
            ])
            ->innerJoin('tawasulTTColumn', 'tawasulTTDay.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID')
            ->where('tawasulTTID = :tawasulTTID')
            ->bindValue('tawasulTTID', $tawasulTTID);

        return $this->runQuery($query, $criteria);
    }

    public function selectTTDaysByID($tawasulTTID)
    {
        $data = array('tawasulTTID' => $tawasulTTID);
        $sql = "SELECT tawasulTTDay.*, tawasulTTColumn.name AS columnName
                FROM tawasulTTDay
                JOIN tawasulTTColumn ON (tawasulTTDay.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID)
                WHERE tawasulTTDay.tawasulTTID=:tawasulTTID
                ORDER BY (tawasulTTDay.name LIKE '%Mon%') DESC, (tawasulTTDay.name LIKE '%Tue%') DESC, (tawasulTTDay.name LIKE '%Wed%') DESC, (tawasulTTDay.name LIKE '%Thu%') DESC, (tawasulTTDay.name LIKE '%Fri%') DESC, (tawasulTTDay.name LIKE '%Sat%') DESC";

        return $this->db()->select($sql, $data);
    }

    public function selectTTDaysByTimetable($tawasulTTID)
    {
        $data = array('tawasulTTID' => $tawasulTTID);
        $sql = "SELECT tawasulTTDayID as value, name
                FROM tawasulTTDay
                WHERE tawasulTTDay.tawasulTTID=:tawasulTTID
                ORDER BY tawasulTTDay.name
        ";

        return $this->db()->select($sql, $data);
    }

    public function selectTTDaysByDateRange($tawasulTTID, $dateStart, $dateEnd)
    {
        $data = ['tawasulTTID' => $tawasulTTID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT tawasulTTDayDate.date as groupBy, tawasulTTDayDate.date, tawasulTTDay.tawasulTTColumnID, tawasulTTDay.tawasulTTDayID, tawasulTTDay.name, tawasulTTDay.nameShort, tawasulTTDay.color, tawasulTTDay.fontColor, tawasulTT.nameShortDisplay
                FROM tawasulTT
                JOIN tawasulTTDay ON (tawasulTT.tawasulTTID=tawasulTTDay.tawasulTTID)
                JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID)
                WHERE tawasulTT.tawasulTTID=:tawasulTTID
                AND tawasulTTDayDate.date BETWEEN :dateStart AND :dateEnd
                ORDER BY tawasulTTDay.name
        ";

        return $this->db()->select($sql, $data);
    }

    public function selectTTDayRowsByID($tawasulTTDayID)
    {
        $data = array('tawasulTTDayID' => $tawasulTTDayID);
        $sql = "SELECT tawasulTTColumnRow.*, COUNT(DISTINCT tawasulTTDayRowClassID) AS classCount
                FROM tawasulTTDay
                JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnID=tawasulTTDay.tawasulTTColumnID)
                LEFT JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID AND tawasulTTDayRowClass.tawasulTTDayID=tawasulTTDay.tawasulTTDayID)
                WHERE tawasulTTDay.tawasulTTDayID=:tawasulTTDayID
                GROUP BY tawasulTTColumnRow.tawasulTTColumnRowID
                ORDER BY tawasulTTColumnRow.timeStart, tawasulTTColumnRow.name";

        return $this->db()->select($sql, $data);
    }

    public function selectTTDayRowClassesByID($tawasulTTDayID, $tawasulTTColumnRowID = null) {
        $data = array('tawasulTTDayID' => $tawasulTTDayID);
        $sql = "SELECT tawasulTTDayRowClassID, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS courseName, tawasulCourseClass.nameShort AS className, tawasulSpace.tawasulSpaceID, tawasulSpace.name as location, tawasulTTColumnRowID
                FROM tawasulTTDayRowClass
                JOIN tawasulCourseClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                LEFT JOIN tawasulSpace ON (tawasulTTDayRowClass.tawasulSpaceID=tawasulSpace.tawasulSpaceID)
                WHERE tawasulTTDayID=:tawasulTTDayID";
                if (!empty($tawasulTTColumnRowID)) {
                    $data['tawasulTTColumnRowID'] = $tawasulTTColumnRowID;
                    $sql .= " AND tawasulTTColumnRowID=:tawasulTTColumnRowID";
                }
                $sql .= " ORDER BY courseName, className";

        return $this->db()->select($sql, $data);
    }

    public function selectTTDayRowClassesByClass($tawasulTTID, $tawasulCourseClassID) {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulTTID' => $tawasulTTID];
        $sql = "SELECT tawasulTTDayRowClassID, tawasulTTDayRowClass.tawasulTTDayID, tawasulTTDayRowClass.tawasulTTColumnRowID, tawasulTTDayRowClass.tawasulSpaceID, tawasulTTDay.name as dayName, tawasulTTColumnRow.name as periodName
                FROM tawasulTTDayRowClass
                JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID)
                JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
                JOIN tawasulTT ON (tawasulTT.tawasulTTID=tawasulTTDay.tawasulTTID)
                LEFT JOIN tawasulSpace ON (tawasulSpace.tawasulSpaceID=tawasulTTDayRowClass.tawasulSpaceID)
                WHERE tawasulTT.tawasulTTID=:tawasulTTID
                AND tawasulTTDayRowClass.tawasulCourseClassID=:tawasulCourseClassID
                ORDER BY tawasulTTDay.name, tawasulTTColumnRow.name";
                
        return $this->db()->select($sql, $data);
    }

    public function selectTTDayRowClassTeachersByID($tawasulTTDayRowClassID) {
        $tawasulTTDayRowClassID = is_array($tawasulTTDayRowClassID)? implode(',', $tawasulTTDayRowClassID) : $tawasulTTDayRowClassID;

        $data = array('tawasulTTDayRowClassID' => $tawasulTTDayRowClassID);
        $sql = "SELECT DISTINCT tawasulTTDayRowClass.tawasulTTDayRowClassID as groupBy, title, surname, preferredName, tawasulTTDayRowClassException.tawasulPersonID AS exception
                FROM tawasulPerson
                JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                LEFT JOIN tawasulTTDayRowClassException ON (tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID
                    AND tawasulTTDayRowClassException.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE tawasulCourseClassPerson.role='Teacher'
                AND tawasulCourseClassPerson.reportable='Y'
                AND tawasulPerson.status='Full'
                AND FIND_IN_SET(tawasulTTDayRowClass.tawasulTTDayRowClassID, :tawasulTTDayRowClassID)
                AND tawasulTTDayRowClassException.tawasulTTDayRowClassExceptionID IS NULL
                ORDER BY surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectTTDayRowClassExceptionsByID($tawasulTTDayRowClassID) {
        $data = array('tawasulTTDayRowClassID' => $tawasulTTDayRowClassID);
        $sql = "SELECT tawasulTTDayRowClassExceptionID, tawasulPerson.tawasulPersonID, surname, preferredName
                FROM tawasulTTDayRowClassException
                JOIN tawasulPerson ON (tawasulTTDayRowClassException.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE tawasulTTDayRowClassID=:tawasulTTDayRowClassID
                ORDER BY surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectTTDayRowClassExceptionsByPersonAndRange($tawasulPersonID, $dateStart, $dateEnd) {
        $data =['tawasulPersonID' => $tawasulPersonID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT tawasulTTDayDate.date, tawasulTTDayRowClassExceptionID, tawasulPerson.tawasulPersonID, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS courseName, tawasulCourseClass.nameShort AS className, tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd, tawasulTTColumnRow.name as period, tawasulTTDay.tawasulTTID, tawasulTTDay.tawasulTTDayID, tawasulTTDayRowClass.tawasulTTDayRowClassID, tawasulTTDayRowClass.tawasulTTColumnRowID
                FROM tawasulTTDayRowClassException
                JOIN tawasulPerson ON (tawasulTTDayRowClassException.tawasulPersonID=tawasulPerson.tawasulPersonID)
                JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulTTDayRowClassID=tawasulTTDayRowClassException.tawasulTTDayRowClassID)
                JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulTTDayRowClass.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
                JOIN tawasulTTDayDate ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID)
                JOIN tawasulTTColumnRow ON (tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID)
                WHERE tawasulTTDayRowClassException.tawasulPersonID=:tawasulPersonID
                AND tawasulTTDayDate.date BETWEEN :dateStart AND :dateEnd
                ORDER BY surname, preferredName";

        return $this->db()->select($sql, $data);
    }


    public function getTTDayByID($tawasulTTDayID)
    {
        $data = array('tawasulTTDayID' => $tawasulTTDayID);
        $sql = "SELECT tawasulTT.tawasulTTID, tawasulSchoolYear.name AS schoolYear, tawasulTT.name AS ttName, tawasulTTDay.name, tawasulTTDay.nameShort, tawasulTTDay.color, tawasulTTDay.fontColor, tawasulTTColumn.tawasulTTColumnID, tawasulTTColumn.name AS columnName
            FROM tawasulTTDay
            JOIN tawasulTT ON (tawasulTTDay.tawasulTTID=tawasulTT.tawasulTTID)
            JOIN tawasulSchoolYear ON (tawasulTT.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
            JOIN tawasulTTColumn ON (tawasulTTDay.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID)
            WHERE tawasulTTDay.tawasulTTDayID=:tawasulTTDayID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getTTDayRowByID($tawasulTTDayID, $tawasulTTColumnRowID)
    {
        $data = array('tawasulTTDayID' => $tawasulTTDayID, 'tawasulTTColumnRowID' => $tawasulTTColumnRowID);
        $sql = "SELECT tawasulTT.name AS ttName, tawasulTTDay.name AS dayName, tawasulTTColumnRow.name AS rowName
                FROM tawasulTTDay
                JOIN tawasulTT ON (tawasulTT.tawasulTTID=tawasulTTDay.tawasulTTID)
                JOIN tawasulTTColumn ON (tawasulTTDay.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID)
                JOIN tawasulTTColumnRow ON (tawasulTTColumn.tawasulTTColumnID=tawasulTTColumnRow.tawasulTTColumnID)
                WHERE tawasulTTDay.tawasulTTDayID=:tawasulTTDayID
                AND tawasulTTColumnRowID=:tawasulTTColumnRowID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getTTDayRowClassByID($tawasulTTDayID, $tawasulTTColumnRowID, $tawasulCourseClassID)
    {
        $data = array('tawasulTTDayID' => $tawasulTTDayID, 'tawasulTTColumnRowID' => $tawasulTTColumnRowID, 'tawasulCourseClassID' => $tawasulCourseClassID);
        $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulTTDayRowClass.tawasulTTDayRowClassID
                FROM tawasulTTDayRowClass
                JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
                JOIN tawasulTTColumn ON (tawasulTTDay.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID)
                JOIN tawasulCourseClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                WHERE tawasulTTDayRowClass.tawasulTTDayID=:tawasulTTDayID
                AND tawasulTTDayRowClass.tawasulTTColumnRowID=:tawasulTTColumnRowID
                AND tawasulTTDayRowClass.tawasulCourseClassID=:tawasulCourseClassID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getTTDayRowClassExceptionByID($tawasulTTDayRowClassExceptionID)
    {
        $data = array('tawasulTTDayRowClassExceptionID' => $tawasulTTDayRowClassExceptionID);
        $sql = "SELECT * FROM tawasulTTDayRowClassException WHERE tawasulTTDayRowClassExceptionID=:tawasulTTDayRowClassExceptionID";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectDaysByDate($date, $tawasulTTID = null) {
        $data = array('date' => $date);
        $sql = "SELECT tawasulTTDayDate.tawasulTTDayID
                FROM tawasulTTDayDate
                JOIN tawasulTTDay ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID)
                WHERE date=:date";

        if (!is_null($tawasulTTID)) {
            $data["tawasulTTID"] = $tawasulTTID;
            $sql .= " AND tawasulTTID=:tawasulTTID";
        }

        return $this->db()->select($sql, $data);
    }

    public function insertDayRowClass(array $data)
    {
        $sql = "INSERT INTO tawasulTTDayRowClass SET tawasulTTDayID=:tawasulTTDayID, tawasulTTColumnRowID=:tawasulTTColumnRowID, tawasulCourseClassID=:tawasulCourseClassID, tawasulSpaceID=:tawasulSpaceID ON DUPLICATE KEY UPDATE tawasulTTDayID=:tawasulTTDayID";

        return $this->db()->insert($sql, $data);
    }

    public function updateDayRowClass(string $tawasulTTDayRowClassID, array $data)
    {
        $data['tawasulTTDayRowClassID'] = $tawasulTTDayRowClassID;

        $sql = "UPDATE tawasulTTDayRowClass SET tawasulTTDayID=:tawasulTTDayID, tawasulTTColumnRowID=:tawasulTTColumnRowID, tawasulCourseClassID=:tawasulCourseClassID, tawasulSpaceID=:tawasulSpaceID WHERE tawasulTTDayRowClassID=:tawasulTTDayRowClassID";

        return $this->db()->update($sql, $data);
    }

    public function insertDayRowClassException(array $data)
    {
        $sql = "INSERT INTO tawasulTTDayRowClassException SET tawasulTTDayRowClassID=:tawasulTTDayRowClassID, tawasulPersonID=:tawasulPersonID ON DUPLICATE KEY UPDATE tawasulTTDayRowClassID=:tawasulTTDayRowClassID";

        return $this->db()->insert($sql, $data);
    }

    public function deleteTTDayRowClasses($tawasulTTID, $tawasulCourseClassID)
    {
        $data = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulTTID' => $tawasulTTID);
        $sql = "DELETE tawasulTTDayRowClass
                FROM tawasulTTDayRowClass
                INNER JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
                INNER JOIN tawasulTT ON (tawasulTT.tawasulTTID=tawasulTTDay.tawasulTTID)
                WHERE tawasulTT.tawasulTTID=:tawasulTTID
                AND tawasulTTDayRowClass.tawasulCourseClassID=:tawasulCourseClassID";

        return $this->db()->delete($sql, $data);
    }

    public function deleteTTDayRowClassesNotInSet($tawasulTTID, $tawasulCourseClassID, $tawasulTTDayRowClassIDList)
    {
        $tawasulTTDayRowClassIDList = is_array($tawasulTTDayRowClassIDList)? implode(',', $tawasulTTDayRowClassIDList) : $tawasulTTDayRowClassIDList;

        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulTTID' => $tawasulTTID, 'tawasulTTDayRowClassIDList' => $tawasulTTDayRowClassIDList];
        $sql = "DELETE tawasulTTDayRowClass, tawasulTTDayRowClassException
                FROM tawasulTTDayRowClass
                INNER JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
                INNER JOIN tawasulTT ON (tawasulTT.tawasulTTID=tawasulTTDay.tawasulTTID)
                LEFT JOIN tawasulTTDayRowClassException ON (tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID)
                WHERE tawasulTT.tawasulTTID=:tawasulTTID
                AND tawasulTTDayRowClass.tawasulCourseClassID=:tawasulCourseClassID
                AND NOT FIND_IN_SET(tawasulTTDayRowClass.tawasulTTDayRowClassID, :tawasulTTDayRowClassIDList)";

        return $this->db()->delete($sql, $data);
    }
}
