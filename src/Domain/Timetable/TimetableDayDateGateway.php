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
 * @version v22
 * @since   v22
 */
class TimetableDayDateGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulTTDayDate';
    private static $primaryKey = 'tawasulTTDayDateID';

    public function deleteTTDatesInRange($firstDayOld, $firstDayNew)
    {
        $data = array('firstDayOld' => $firstDayOld, 'firstDayNew' => $firstDayNew);
        $sql = "DELETE FROM tawasulTTDayDate WHERE date >= :firstDayOld AND date < :firstDayNew";

        return $this->db()->delete($sql, $data);
    }
    
    public function getTimetablePeriodByDayRowClass($tawasulTTDayRowClassID)
    {
        $data = ['tawasulTTDayRowClassID' => $tawasulTTDayRowClassID];
        $sql = "SELECT tawasulTTColumnRow.name, tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd, tawasulTTDayRowClass.tawasulCourseClassID
                FROM tawasulTTDayRowClass
                JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID)
                WHERE tawasulTTDayRowClass.tawasulTTDayRowClassID=:tawasulTTDayRowClassID";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectTimetabledPeriodsByClass($tawasulCourseClassID, $date)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $date];
        $sql = "SELECT tawasulTTDayRowClass.tawasulTTDayRowClassID, tawasulTTColumnRow.name as period,  tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd, tawasulTTDayRowClass.tawasulCourseClassID
                FROM tawasulTTDayRowClass
                JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID)
                JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
                WHERE tawasulTTDayRowClass.tawasulCourseClassID=:tawasulCourseClassID
                AND tawasulTTDayDate.date=:date";

        return $this->db()->select($sql, $data);
    }

    public function getTimetabledPeriodByClassAndTime($tawasulCourseClassID, $date, $timeStart, $timeEnd)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $date, 'timeStart' => $timeStart, 'timeEnd' => $timeEnd];
        $sql = "SELECT tawasulTTDayRowClass.tawasulTTDayRowClassID, tawasulTTColumnRow.name as period,  tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd, tawasulTTDayRowClass.tawasulCourseClassID
                FROM tawasulTTDayRowClass
                JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID)
                JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
                WHERE tawasulTTDayRowClass.tawasulCourseClassID=:tawasulCourseClassID
                AND tawasulTTDayDate.date=:date
                AND tawasulTTColumnRow.timeStart=:timeStart 
                AND tawasulTTColumnRow.timeEnd=:timeEnd";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectTimetabledPeriodsByDate($date)
    {
        $query = $this
            ->newSelect()
            ->cols(['tawasulTTColumnRow.type', 'tawasulTTColumnRow.name as period', 'tawasulTTColumnRow.timeStart', 'tawasulTTColumnRow.timeEnd'])
            ->from('tawasulTT')
            ->innerJoin('tawasulSchoolYear', 'tawasulTT.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID') 
            ->innerJoin('tawasulTTDay', 'tawasulTT.tawasulTTID=tawasulTTDay.tawasulTTID') 
            ->innerJoin('tawasulTTDayDate', 'tawasulTTDay.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID') 
            ->innerJoin('tawasulTTColumnRow', 'tawasulTTColumnRow.tawasulTTColumnID=tawasulTTDay.tawasulTTColumnID')
            ->where('tawasulSchoolYear.status="Current"')
            ->where('tawasulTT.active="Y"')
            ->where('tawasulTTDayDate.date=:date')
            ->where('(tawasulTTColumnRow.type="Lesson" OR tawasulTTColumnRow.type="Pastoral" OR tawasulTTColumnRow.type="Break")')
            ->bindValue('date', $date)
            ->groupBy(['tawasulTTColumnRow.timeStart'])
            ->orderBy(['timeStart', 'timeEnd']);

        return $this->runSelect($query);
    }

    public function selectTimetabledPeriodsByPersonAndDateRange($tawasulPersonID, $dateStart, $dateEnd)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd];

        $sql = "SELECT tawasulTTDayRowClass.tawasulTTDayID, tawasulTTDayRowClass.tawasulTTDayRowClassID, tawasulTTColumnRow.tawasulTTColumnRowID, tawasulCourseClass.tawasulCourseClassID, tawasulTTDay.tawasulTTID, tawasulTTDayDate.date, tawasulTTColumnRow.name as period, tawasulTTColumnRow.nameShort, tawasulCourse.tawasulSchoolYearID, tawasulCourse.tawasulCourseID, tawasulCourse.name as courseName, tawasulCourse.nameShort AS courseNameShort, tawasulCourseClass.nameShort AS classNameShort, tawasulCourse.tawasulYearGroupIDList, tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd, tawasulSpace.phoneInternal as phone, tawasulSpace.name AS roomName, tawasulTTSpaceChange.tawasulTTSpaceChangeID as spaceChanged, spaceChange.name as roomNameChange, spaceChange.phoneInternal as phoneChange, coverage.status as coverageStatus, coverage.tawasulStaffCoverageID as coverageID, coverage.tawasulPersonIDCoverage as coveragePerson, CONCAT(tawasulCourseClass.tawasulCourseClassID, tawasulTTDayDate.date, tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd) as lessonID
        FROM tawasulCourse 
        JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) 
        JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) 
        JOIN tawasulTTDayRowClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulTTDayRowClass.tawasulCourseClassID) 
        JOIN tawasulTTColumnRow ON (tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID) 
        JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID) 
        JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID)
        LEFT JOIN tawasulTTDayRowClassException ON (tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTDayRowClassException.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID)
        LEFT JOIN tawasulSpace ON (tawasulTTDayRowClass.tawasulSpaceID=tawasulSpace.tawasulSpaceID) 
        LEFT JOIN tawasulTTSpaceChange ON (tawasulTTSpaceChange.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTSpaceChange.date=tawasulTTDayDate.date) 
        LEFT JOIN tawasulSpace as spaceChange ON (spaceChange.tawasulSpaceID=tawasulTTSpaceChange.tawasulSpaceID) 
        LEFT JOIN (
            SELECT tawasulStaffCoverage.tawasulStaffCoverageID, tawasulStaffCoverage.status, tawasulStaffCoverage.tawasulPersonIDCoverage, tawasulStaffCoverageDate.foreignTableID, tawasulStaffCoverageDate.foreignTable, tawasulStaffCoverageDate.date FROM tawasulStaffCoverageDate 
            JOIN tawasulStaffCoverage ON (tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID)
            WHERE tawasulStaffCoverage.status <> 'Declined' AND tawasulStaffCoverage.status <> 'Cancelled'
        ) AS coverage ON (coverage.foreignTableID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND coverage.foreignTable='tawasulTTDayRowClass' AND coverage.date=tawasulTTDayDate.date)
        WHERE tawasulTTDayDate.date BETWEEN :dateStart AND :dateEnd
            AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID 
            AND NOT role LIKE '% - Left' 
            AND tawasulTTDayRowClassException.tawasulTTDayRowClassExceptionID IS NULL
        GROUP BY tawasulTTDayRowClass.tawasulTTDayRowClassID, tawasulTTDayDate.tawasulTTDayDateID
        ORDER BY timeStart, timeEnd, FIND_IN_SET(tawasulCourseClassPerson.role, 'Teacher,Assistant,Student') DESC, tawasulCourse.name, tawasulCourseClass.nameShort, coverage.status
        ";

        return $this->db()->select($sql, $data);
    }

    public function selectTimetabledPeriodsByFacilityAndDateRange($tawasulSpaceID, $dateStart, $dateEnd)
    {
        $data = ['tawasulSpaceID' => $tawasulSpaceID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd];

        $sql = "(
            SELECT tawasulTTDayRowClass.tawasulTTDayID, tawasulTTDayRowClass.tawasulTTDayRowClassID, tawasulTTDay.tawasulTTID, tawasulTTColumnRow.tawasulTTColumnRowID, tawasulCourseClass.tawasulCourseClassID, tawasulTTDayDate.date, tawasulTTColumnRow.name as period, tawasulTTColumnRow.nameShort, tawasulCourse.tawasulSchoolYearID, tawasulCourse.tawasulCourseID, tawasulCourse.name as courseName, tawasulCourse.nameShort AS courseNameShort, tawasulCourseClass.nameShort AS classNameShort, tawasulCourse.tawasulYearGroupIDList, tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd, tawasulSpace.phoneInternal as phone, tawasulSpace.name AS roomName, null as spaceChanged
            FROM tawasulSpace
            JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulSpaceID=tawasulSpace.tawasulSpaceID)
            JOIN tawasulTTColumnRow ON (tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID) 
            JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID) 
            JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID)
            JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulTTDayRowClass.tawasulCourseClassID) 
            JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) 
            LEFT JOIN tawasulTTSpaceChange ON (tawasulTTSpaceChange.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTSpaceChange.date=tawasulTTDayDate.date) 
            WHERE tawasulTTDayDate.date BETWEEN :dateStart AND :dateEnd
                AND tawasulSpace.tawasulSpaceID=:tawasulSpaceID 
                AND tawasulTTSpaceChange.tawasulTTSpaceChangeID IS NULL
            GROUP BY tawasulTTDayRowClass.tawasulTTDayRowClassID 
            ORDER BY timeStart, timeEnd DESC
        ) UNION ALL (
            SELECT tawasulTTDayRowClass.tawasulTTDayID, tawasulTTDayRowClass.tawasulTTDayRowClassID, tawasulTTDay.tawasulTTID, tawasulTTColumnRow.tawasulTTColumnRowID, tawasulCourseClass.tawasulCourseClassID, tawasulTTDayDate.date, tawasulTTColumnRow.name as period, tawasulTTColumnRow.nameShort, tawasulCourse.tawasulSchoolYearID, tawasulCourse.tawasulCourseID, tawasulCourse.name as courseName, tawasulCourse.nameShort AS courseNameShort, tawasulCourseClass.nameShort AS classNameShort, tawasulCourse.tawasulYearGroupIDList, tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd, tawasulSpace.phoneInternal as phone, tawasulSpace.name AS roomName, tawasulTTSpaceChange.tawasulTTSpaceChangeID as spaceChanged
            FROM tawasulTTSpaceChange
            JOIN tawasulSpace ON (tawasulSpace.tawasulSpaceID=tawasulTTSpaceChange.tawasulSpaceID)
            JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulTTDayRowClassID=tawasulTTSpaceChange.tawasulTTDayRowClassID)
            JOIN tawasulTTColumnRow ON (tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID) 
            JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID) 
            JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID)
            JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulTTDayRowClass.tawasulCourseClassID) 
            JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) 
            WHERE tawasulTTDayDate.date BETWEEN :dateStart AND :dateEnd
                AND tawasulTTSpaceChange.tawasulSpaceID=:tawasulSpaceID 
                AND tawasulTTSpaceChange.date=tawasulTTDayDate.date

            GROUP BY tawasulTTDayRowClass.tawasulTTDayRowClassID 
            ORDER BY timeStart, timeEnd DESC
        )
        ";

        return $this->db()->select($sql, $data);
    }
    
    /**
     * Get all timetable periods for a given student on a specific date.
     *
     * @param string $tawasulSchoolYearID
     * @param string $tawasulPersonID
     * @param string $date  Y-m-d
     * @return \TawasulOS\Contracts\Database\Result
     */	
    public function selectTimetablePeriodsByPersonAndDate($tawasulSchoolYearID, $tawasulPersonID, $dateStart, $dateEnd, $timeStart = null, $timeEnd = null, $includeTeachers = false)
    {
        $query = $this
            ->newSelect()
            ->from('tawasulTTDayRowClass')
            ->cols([
                'tawasulTTDayRowClass.tawasulTTDayRowClassID',
                'tawasulTTColumnRow.name AS periodName',
                'tawasulTTColumnRow.nameShort AS periodNameShort',
                'tawasulTTColumnRow.timeStart',
                'tawasulTTColumnRow.timeEnd',
                'tawasulCourseClass.nameShort AS className',
                'tawasulCourse.nameShort AS courseName',
                'tawasulCourseClass.attendance',
            ])
            ->innerJoin('tawasulTTDay', 'tawasulTTDay.tawasulTTDayID = tawasulTTDayRowClass.tawasulTTDayID')
            ->innerJoin('tawasulTTDayDate', 'tawasulTTDayDate.tawasulTTDayID = tawasulTTDay.tawasulTTDayID')
            ->innerJoin('tawasulTTColumnRow', 'tawasulTTColumnRow.tawasulTTColumnRowID = tawasulTTDayRowClass.tawasulTTColumnRowID')
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseClassID = tawasulTTDayRowClass.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID = tawasulCourseClass.tawasulCourseID')
            ->innerJoin('tawasulCourseClassPerson','tawasulCourseClassPerson.tawasulCourseClassID = tawasulCourseClass.tawasulCourseClassID')
            ->where('tawasulCourse.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulCourseClassPerson.tawasulPersonID = :tawasulPersonID')
            ->where('tawasulCourseClassPerson.role = "Student"')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulTTDayDate.date BETWEEN :dateStart AND :dateEnd')
            ->bindValue('dateStart', $dateStart)
            ->bindValue('dateEnd', $dateEnd)
            ->groupBy(['tawasulTTDayRowClass.tawasulTTDayRowClassID'])
            ->orderBy(['tawasulTTColumnRow.timeStart ASC']);

        if (!empty($timeStart) && !empty($timeEnd)) {
            $query->where(
                '((tawasulTTColumnRow.timeStart >= :timeStart AND tawasulTTColumnRow.timeStart < :timeEnd)
                OR (:timeStart >= tawasulTTColumnRow.timeStart AND :timeStart < tawasulTTColumnRow.timeEnd)
                OR (:timeStart = tawasulTTColumnRow.timeStart AND :timeEnd = tawasulTTColumnRow.timeEnd))'
            )
            ->bindValue('timeStart', $timeStart)
            ->bindValue('timeEnd', $timeEnd);
        }

        if ($includeTeachers) {
            $query
                ->cols(['GROUP_CONCAT(DISTINCT teacher.tawasulPersonID) as teacherIDs'])
                ->leftJoin('tawasulCourseClassPerson as teacher','teacher.tawasulCourseClassID = tawasulCourseClass.tawasulCourseClassID AND (teacher.role="Teacher" OR teacher.role="Assistant")');
        }

        return $this->runSelect($query);
    }

}
