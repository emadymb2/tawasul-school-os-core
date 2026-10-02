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
use TawasulOS\Domain\Traits\TableAware;

/**
 * Staff Coverage Date Gateway
 *
 * @version v18
 * @since   v18
 */
class StaffCoverageDateGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulStaffCoverageDate';
    private static $primaryKey = 'tawasulStaffCoverageDateID';

    private static $searchableColumns = [''];

    public function selectDatesByCoverage($tawasulStaffCoverageID)
    {
        $tawasulStaffCoverageIDList = is_array($tawasulStaffCoverageID)? $tawasulStaffCoverageID : [$tawasulStaffCoverageID];
        $data = ['tawasulStaffCoverageIDList' => implode(',', $tawasulStaffCoverageIDList) ];
        $sql = "SELECT tawasulStaffCoverageDate.tawasulStaffCoverageID as groupBy,  tawasulStaffCoverageDate.*, tawasulStaffCoverage.tawasulStaffCoverageID, tawasulStaffAbsence.status as absenceStatus, tawasulStaffCoverage.status as coverage, tawasulStaffCoverage.requestType, coverage.title as titleCoverage, coverage.preferredName as preferredNameCoverage, coverage.surname as surnameCoverage, coverage.tawasulPersonID as tawasulPersonIDCoverage, tawasulStaffCoverageDate.reason as notes
                FROM tawasulStaffCoverageDate
                LEFT JOIN tawasulStaffCoverage ON (tawasulStaffCoverage.tawasulStaffCoverageID=tawasulStaffCoverageDate.tawasulStaffCoverageID)
                LEFT JOIN tawasulStaffAbsence ON (tawasulStaffAbsence.tawasulStaffAbsenceID=tawasulStaffCoverage.tawasulStaffAbsenceID)
                LEFT JOIN tawasulPerson AS coverage ON (tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID)
                WHERE FIND_IN_SET(tawasulStaffCoverageDate.tawasulStaffCoverageID, :tawasulStaffCoverageIDList)
                ORDER BY tawasulStaffCoverageDate.date, tawasulStaffCoverageDate.timeStart";

        return $this->db()->select($sql, $data);
    }

    public function getCoverageDateDetailsByID($tawasulStaffCoverageDateID)
    {
        $data = ['tawasulStaffCoverageDateID' => $tawasulStaffCoverageDateID];
        $sql = "SELECT tawasulStaffCoverage.tawasulStaffCoverageID, tawasulStaffCoverage.status, tawasulStaffAbsence.tawasulStaffAbsenceID, tawasulStaffAbsenceType.name as type, tawasulStaffAbsence.reason, tawasulStaffCoverage.substituteTypes,
                tawasulStaffCoverageDate.date, tawasulStaffCoverageDate.allDay, tawasulStaffCoverageDate.timeStart, tawasulStaffCoverageDate.timeEnd, tawasulStaffCoverageDate.reason, tawasulStaffCoverage.timestampStatus, tawasulStaffCoverage.timestampCoverage, tawasulStaffCoverage.requestType,
                tawasulStaffCoverage.notesCoverage, tawasulStaffCoverage.notesStatus, 0 as urgent, tawasulStaffAbsence.notificationSent, tawasulStaffAbsence.tawasulGroupID, tawasulStaffCoverage.notificationList as notificationListCoverage, tawasulStaffAbsence.notificationList as notificationListAbsence, 
                tawasulStaffCoverage.tawasulPersonID, absence.title AS titleAbsence, absence.preferredName AS preferredNameAbsence, absence.surname AS surnameAbsence, 
                tawasulStaffCoverage.tawasulPersonIDStatus, status.title AS titleStatus, status.preferredName AS preferredNameStatus, status.surname AS surnameStatus, 
                tawasulStaffCoverage.tawasulPersonIDCoverage, coverage.title as titleCoverage, coverage.preferredName as preferredNameCoverage, coverage.surname as surnameCoverage, tawasulStaffCoverageDate.foreignTable, tawasulStaffCoverageDate.foreignTableID
            FROM tawasulStaffCoverageDate 
            JOIN tawasulStaffCoverage ON (tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID)
            LEFT JOIN tawasulStaffAbsence ON (tawasulStaffAbsence.tawasulStaffAbsenceID=tawasulStaffCoverage.tawasulStaffAbsenceID)
            LEFT JOIN tawasulStaffAbsenceType ON (tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID)
            LEFT JOIN tawasulPerson AS coverage ON (tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID)
            LEFT JOIN tawasulPerson AS status ON (tawasulStaffCoverage.tawasulPersonIDStatus=status.tawasulPersonID)
            LEFT JOIN tawasulPerson AS absence ON (tawasulStaffCoverage.tawasulPersonID=absence.tawasulPersonID)
            WHERE tawasulStaffCoverageDate.tawasulStaffCoverageDateID=:tawasulStaffCoverageDateID
            ";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectTimetabledClassCoverageByPersonAndDate($tawasulSchoolYearID, $tawasulPersonID, $dateStart, $dateEnd)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT DISTINCT tawasulTTDayDate.date, tawasulTT.tawasulTTID, tawasulTT.name, tawasulTTDayRowClass.tawasulTTDayRowClassID, tawasulCourseClass.tawasulCourseClassID, tawasulCourseClass.nameShort as classNameShort, tawasulTTColumnRow.name as columnName, tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd, tawasulCourse.name as courseName, tawasulCourse.nameShort as courseNameShort, tawasulStaffCoverage.tawasulStaffCoverageID, tawasulStaffCoverage.status, CONCAT(tawasulTTDayDate.date, ':', tawasulTTDayRowClass.tawasulTTDayRowClassID) as timetableClassPeriod, coverage.surname as surnameCoverage, coverage.preferredName as preferredNameCoverage
        FROM tawasulTT 
        JOIN tawasulTTDay ON (tawasulTT.tawasulTTID=tawasulTTDay.tawasulTTID) 
        JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulTTDayID=tawasulTTDay.tawasulTTDayID) 
        JOIN tawasulTTDayDate ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID) 
        JOIN tawasulCourseClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
        JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID)
        JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
        JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
        LEFT JOIN tawasulStaffCoverageDate ON (tawasulStaffCoverageDate.foreignTable='tawasulTTDayRowClass' AND tawasulStaffCoverageDate.foreignTableID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulStaffCoverageDate.date=tawasulTTDayDate.date)
        LEFT JOIN tawasulStaffCoverage ON (tawasulStaffCoverage.tawasulStaffCoverageID=tawasulStaffCoverageDate.tawasulStaffCoverageID AND tawasulStaffCoverage.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID)
        LEFT JOIN tawasulPerson as coverage ON (coverage.tawasulPersonID=tawasulStaffCoverage.tawasulPersonIDCoverage)
        LEFT JOIN tawasulTTDayRowClassException ON (tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTDayRowClassException.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID)
        WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID 
        AND tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID 
        AND tawasulTT.active='Y' 
        AND tawasulTTDayDate.date BETWEEN :dateStart AND :dateEnd
        AND tawasulCourseClassPerson.role NOT LIKE '%Left'
        AND tawasulTTDayRowClassExceptionID IS NULL
        ORDER BY tawasulTTDayDate.date, tawasulTTColumnRow.timeStart ASC";

        return $this->db()->select($sql, $data);
    }

    public function selectPotentialCoverageByPersonAndDate($tawasulSchoolYearID, $tawasulPersonID, $dateStart, $dateEnd)
    {
        $activityDateType = $this->db()->selectOne("SELECT value FROM tawasulSetting WHERE scope='Activities' AND name='dateType'");

        $query = $this
            ->newSelect()
            ->cols(['tawasulTT.tawasulTTID as groupBy', '"Class" as context', 'CONCAT(tawasulCourse.nameShort, ".", tawasulCourseClass.nameShort) as contextName', 'tawasulCourseClass.tawasulCourseClassID as contextID', '"tawasulTTDayRowClass" as foreignTable', 'tawasulTTDayRowClass.tawasulTTDayRowClassID as foreignTableID', 'tawasulTTDayDate.date', 'tawasulTTColumnRow.name as period', 'tawasulTTColumnRow.timeStart', 'tawasulTTColumnRow.timeEnd', 'tawasulStaffCoverage.tawasulStaffCoverageID', 'tawasulStaffCoverage.status as coverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage', 'coverage.surname as surnameCoverage', 'coverage.preferredName as preferredNameCoverage' ])
            ->from('tawasulCourseClassPerson')
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->innerJoin('tawasulTTDayRowClass', 'tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->innerJoin('tawasulTTColumnRow', 'tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID')
            ->innerJoin('tawasulTTDay', 'tawasulTTDayRowClass.tawasulTTDayID=tawasulTTDay.tawasulTTDayID') 
            ->innerJoin('tawasulTTDayDate', 'tawasulTTDay.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID') 
            ->innerJoin('tawasulTT', 'tawasulTT.tawasulTTID=tawasulTTDay.tawasulTTID') 
            ->leftJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverageDate.foreignTable="tawasulTTDayRowClass" AND tawasulStaffCoverageDate.foreignTableID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulStaffCoverageDate.date=tawasulTTDayDate.date')
            ->leftJoin('tawasulStaffCoverage', 'tawasulStaffCoverage.tawasulStaffCoverageID=tawasulStaffCoverageDate.tawasulStaffCoverageID AND tawasulStaffCoverage.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID AND tawasulStaffCoverage.status <> "Cancelled" AND tawasulStaffCoverage.status <> "Declined"')
            ->leftJoin('tawasulPerson as coverage', 'coverage.tawasulPersonID=tawasulStaffCoverage.tawasulPersonIDCoverage')
            ->leftJoin('tawasulTTDayRowClassException', 'tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTDayRowClassException.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID')
            ->where('tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulTT.active="Y"')
            ->where('tawasulTTDayDate.date BETWEEN :dateStart AND :dateEnd')
            ->bindValues(['dateStart' => $dateStart, 'dateEnd' => $dateEnd])
            ->where('tawasulCourseClassPerson.role NOT LIKE "%Left"')
            ->where('tawasulTTDayRowClassExceptionID IS NULL');

        $query->unionAll()
            ->cols([
                'tawasulStaffDuty.tawasulStaffDutyID as groupBy', '"Staff Duty" as context', 'tawasulStaffDuty.name as contextName', 'tawasulStaffDuty.tawasulStaffDutyID as contextID', '"tawasulStaffDutyPerson" as foreignTable', 'tawasulStaffDutyPerson.tawasulStaffDutyPersonID as foreignTableID',  "DATE_ADD((:dateStart - INTERVAL (WEEKDAY(:dateStart)) DAY), INTERVAL tawasulDaysOfWeek.tawasulDaysOfWeekID-1 DAY) as date", '"Staff Duty" as period', 'tawasulStaffDuty.timeStart', 'tawasulStaffDuty.timeEnd', 'tawasulStaffCoverage.tawasulStaffCoverageID', 'tawasulStaffCoverage.status as coverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage', 'coverage.surname as surnameCoverage, coverage.preferredName as preferredNameCoverage'
            ])
            ->from('tawasulStaffDutyPerson')
            ->innerJoin('tawasulStaffDuty', 'tawasulStaffDuty.tawasulStaffDutyID=tawasulStaffDutyPerson.tawasulStaffDutyID')
            ->innerJoin('tawasulDaysOfWeek', 'tawasulDaysOfWeek.tawasulDaysOfWeekID=tawasulStaffDutyPerson.tawasulDaysOfWeekID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulStaffDutyPerson.tawasulPersonID')

            ->leftJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverageDate.foreignTable="tawasulStaffDutyPerson" AND tawasulStaffCoverageDate.foreignTableID=tawasulStaffDutyPerson.tawasulStaffDutyPersonID AND tawasulStaffCoverageDate.date BETWEEN :dateStart AND :dateEnd')
            ->leftJoin('tawasulStaffCoverage', 'tawasulStaffCoverage.tawasulStaffCoverageID=tawasulStaffCoverageDate.tawasulStaffCoverageID AND tawasulStaffCoverage.tawasulPersonID=tawasulStaffDutyPerson.tawasulPersonID')
            ->leftJoin('tawasulPerson as coverage', 'coverage.tawasulPersonID=tawasulStaffCoverage.tawasulPersonIDCoverage')

            ->where('tawasulStaffDutyPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulPerson.status="Full"')
            ->having('`date` BETWEEN :dateStart AND :dateEnd')
            ->bindValues(['dateStart' => $dateStart, 'dateEnd' => $dateEnd]);

        $query->unionAll()
            ->cols([
                'tawasulActivitySlot.tawasulActivitySlotID as groupBy', '"Activity" as context', 'tawasulActivity.name as contextName', 'tawasulActivity.tawasulActivityID as contextID', '"tawasulActivitySlot" as foreignTable', 'tawasulActivitySlot.tawasulActivitySlotID as foreignTableID', "DATE_ADD((:dateStart - INTERVAL (WEEKDAY(:dateStart)) DAY), INTERVAL tawasulDaysOfWeek.tawasulDaysOfWeekID-1 DAY) as date", '"Activity" as period', 'tawasulActivitySlot.timeStart', 'tawasulActivitySlot.timeEnd', 
                'tawasulStaffCoverage.tawasulStaffCoverageID', 'tawasulStaffCoverage.status as coverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage', 'coverage.surname as surnameCoverage', 'coverage.preferredName as preferredNameCoverage'
            ])
            ->from('tawasulActivity')
            ->innerJoin('tawasulActivityStaff', 'tawasulActivity.tawasulActivityID=tawasulActivityStaff.tawasulActivityID AND tawasulActivityStaff.tawasulPersonID=:tawasulPersonID')
            ->innerJoin('tawasulActivitySlot', 'tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID')
            ->innerJoin('tawasulDaysOfWeek', 'tawasulDaysOfWeek.tawasulDaysOfWeekID=tawasulActivitySlot.tawasulDaysOfWeekID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulActivityStaff.tawasulPersonID')
            
            ->leftJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverageDate.foreignTable="tawasulActivitySlot" AND tawasulStaffCoverageDate.foreignTableID=tawasulActivitySlot.tawasulActivitySlotID AND tawasulStaffCoverageDate.date BETWEEN :dateStart AND :dateEnd')
            ->leftJoin('tawasulStaffCoverage', 'tawasulStaffCoverage.tawasulStaffCoverageID=tawasulStaffCoverageDate.tawasulStaffCoverageID AND tawasulStaffCoverage.tawasulPersonID=tawasulActivityStaff.tawasulPersonID')
            ->leftJoin('tawasulPerson as coverage', 'coverage.tawasulPersonID=tawasulStaffCoverage.tawasulPersonIDCoverage')

            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulActivity.active="Y"')
            ->where('tawasulPerson.status="Full"')
            ->where('tawasulActivity.active="Y"')
            ->having('`date` BETWEEN :dateStart AND :dateEnd')
            ->bindValues(['dateStart' => $dateStart, 'dateEnd' => $dateEnd]);

            if ($activityDateType == 'Term') {
                $query->leftJoin('tawasulSchoolYearTerm as activityTerm', 'FIND_IN_SET(activityTerm.tawasulSchoolYearTermID, tawasulActivity.tawasulSchoolYearTermIDList)')
                    ->where('(activityTerm.firstDay <= :dateStart AND activityTerm.lastDay >= :dateEnd)');
            } else {
                $query->where('(tawasulActivity.programStart <= :dateStart AND tawasulActivity.programEnd >= :dateEnd)');
            }

            $query->orderBy(['date', 'timeStart']);

        return $this->runSelect($query);
    }

    public function selectCoverageTimesByDate($tawasulSchoolYearID, $date)
    {
        $activityDateType = $this->db()->selectOne("SELECT value FROM tawasulSetting WHERE scope='Activities' AND name='dateType'");

        $query = $this
            ->newSelect()
            ->cols(['CONCAT("tt-", tawasulTTColumnRow.timeStart, "-", tawasulTTColumnRow.timeEnd) as groupBy', 'tawasulTTColumnRow.type', 'tawasulTTColumnRow.name as period', 'tawasulTTColumnRow.timeStart', 'tawasulTTColumnRow.timeEnd', 'GROUP_CONCAT(tawasulTT.name SEPARATOR ", ") as ttName'])
            ->from('tawasulTT')
            ->innerJoin('tawasulTTDay', 'tawasulTT.tawasulTTID=tawasulTTDay.tawasulTTID') 
            ->innerJoin('tawasulTTDayDate', 'tawasulTTDay.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID') 
            ->innerJoin('tawasulTTColumnRow', 'tawasulTTColumnRow.tawasulTTColumnID=tawasulTTDay.tawasulTTColumnID')
            ->where('tawasulTT.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulTT.active="Y"')
            ->where('tawasulTTDayDate.date=:date')
            ->where('(tawasulTTColumnRow.type="Lesson" OR tawasulTTColumnRow.type="Pastoral")')
            ->bindValue('date', $date)
            ->groupBy(['tawasulTTColumnRow.timeStart', 'tawasulTTColumnRow.timeEnd']);

        $query->unionAll()
            ->cols(['CONCAT("duty-", tawasulStaffDuty.timeStart, "-", tawasulStaffDuty.timeEnd) as groupBy', '"Staff Duty" AS type', '"Staff Duty" as period', 'tawasulStaffDuty.timeStart', 'tawasulStaffDuty.timeEnd', '"" as ttName'])
            ->from('tawasulStaffDutyPerson')
            ->innerJoin('tawasulStaffDuty', 'tawasulStaffDuty.tawasulStaffDutyID=tawasulStaffDutyPerson.tawasulStaffDutyID')
            ->innerJoin('tawasulDaysOfWeek', 'tawasulDaysOfWeek.tawasulDaysOfWeekID=tawasulStaffDutyPerson.tawasulDaysOfWeekID')
            ->where('(tawasulDaysOfWeek.tawasulDaysOfWeekID-1) = WEEKDAY(:date)')
            ->bindValue('date', $date)
            ->groupBy(['tawasulStaffDuty.tawasulStaffDutyID']);

        $query->unionAll()
            ->cols(['"activity" as groupBy', '"Activity" AS type', '"Activity" as period', 'MIN(tawasulActivitySlot.timeStart)', 'MIN(tawasulActivitySlot.timeEnd)', '"" as ttName'])
            ->from('tawasulActivitySlot')
            ->innerJoin('tawasulActivity', 'tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID')
            ->innerJoin('tawasulDaysOfWeek', 'tawasulDaysOfWeek.tawasulDaysOfWeekID=tawasulActivitySlot.tawasulDaysOfWeekID')
            ->where('(tawasulDaysOfWeek.tawasulDaysOfWeekID-1) = WEEKDAY(:date)')
            ->bindValue('date', $date)
            ->where('tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulActivity.active="Y"')
            ->groupBy(['tawasulDaysOfWeek.tawasulDaysOfWeekID']);

        if ($activityDateType == 'Term') {
            $query->leftJoin('tawasulSchoolYearTerm as activityTerm', 'FIND_IN_SET(activityTerm.tawasulSchoolYearTermID, tawasulActivity.tawasulSchoolYearTermIDList)')
                ->where(':date BETWEEN activityTerm.firstDay AND activityTerm.lastDay');
        } else {
            $query->where(':date BETWEEN tawasulActivity.programStart AND tawasulActivity.programEnd');
        }

        $query->orderBy(['timeStart', 'timeEnd']);

        return $this->runSelect($query);
    }

    public function selectCoveragePeriodsByDate($tawasulSchoolYearID, $date)
    {
        $query = $this
            ->newSelect()
            ->cols(['CONCAT("tt-", tawasulTTColumnRow.timeStart, "-", tawasulTTColumnRow.timeEnd) as groupBy', 'tawasulTTColumnRow.type', 'tawasulTTColumnRow.name as period', 'tawasulTTColumnRow.timeStart', 'tawasulTTColumnRow.timeEnd'])
            ->from('tawasulTT')
            ->innerJoin('tawasulTTDay', 'tawasulTT.tawasulTTID=tawasulTTDay.tawasulTTID') 
            ->innerJoin('tawasulTTDayDate', 'tawasulTTDay.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID') 
            ->innerJoin('tawasulTTColumnRow', 'tawasulTTColumnRow.tawasulTTColumnID=tawasulTTDay.tawasulTTColumnID')
            ->where('tawasulTT.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulTT.active="Y"')
            ->where('tawasulTTDayDate.date=:date')
            ->where('(tawasulTTColumnRow.type="Lesson" OR tawasulTTColumnRow.type="Pastoral" OR tawasulTTColumnRow.type="Break")')
            ->bindValue('date', $date)
            ->orderBy(['timeStart', 'timeEnd']);

        return $this->runSelect($query);
    }

    public function getCoverageTimesByForeignTable($foreignTable, $foreignTableID, $date)
    {
        switch ($foreignTable) {
            case 'tawasulTTDayRowClass': 
                return $this->getCoverageTimesByTimetableClass($foreignTableID);
            case 'tawasulStaffDutyPerson': 
                return $this->getCoverageTimesByStaffDuty($foreignTableID, $date);
            case 'tawasulActivitySlot': 
                return $this->getCoverageTimesByActivity($foreignTableID, $date);
            default:
                return [];
        }
    }

    public function getCoverageTimesByTimetableClass($tawasulTTDayRowClassID)
    {
        $data = ['tawasulTTDayRowClassID' => $tawasulTTDayRowClassID];
        $sql = "SELECT tawasulTTColumnRow.name as period, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as contextName, tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd, 'N' as allDay, tawasulCourse.nameShort as courseName, tawasulCourseClass.nameShort as className, tawasulSpace.name as spaceName, tawasulSpace.tawasulSpaceID, tawasulCourseClass.tawasulCourseClassID
            FROM tawasulTTDayRowClass
            JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID)
            JOIN tawasulCourseClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
            JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
            LEFT JOIN tawasulSpace ON (tawasulTTDayRowClass.tawasulSpaceID=tawasulSpace.tawasulSpaceID)
            WHERE tawasulTTDayRowClassID=:tawasulTTDayRowClassID";
        
        return $this->db()->selectOne($sql, $data);
    }

    public function getCoverageTimesByStaffDuty($tawasulStaffDutyPersonID, $date)
    {
        $data = ['tawasulStaffDutyPersonID' => $tawasulStaffDutyPersonID, 'date' => $date];
        $sql = "SELECT 'Staff Duty' as period, tawasulStaffDuty.name as contextName, tawasulStaffDuty.timeStart, tawasulStaffDuty.timeEnd, 'N' as allDay 
            FROM tawasulStaffDutyPerson
            JOIN tawasulStaffDuty ON (tawasulStaffDuty.tawasulStaffDutyID=tawasulStaffDutyPerson.tawasulStaffDutyID)
            JOIN tawasulDaysOfWeek ON (tawasulDaysOfWeek.tawasulDaysOfWeekID=tawasulStaffDutyPerson.tawasulDaysOfWeekID)
            WHERE tawasulStaffDutyPerson.tawasulStaffDutyPersonID=:tawasulStaffDutyPersonID
            AND tawasulDaysOfWeek.tawasulDaysOfWeekID-1 = WEEKDAY(:date)";
        
        return $this->db()->selectOne($sql, $data);
    }

    public function getCoverageTimesByActivity($tawasulActivitySlotID, $date)
    {
        $data = ['tawasulActivitySlotID' => $tawasulActivitySlotID, 'date' => $date];
        $sql = "SELECT 'Activity' as period, tawasulActivity.name as contextName, tawasulActivitySlot.timeStart, tawasulActivitySlot.timeEnd, 'N' as allDay
            FROM tawasulActivitySlot
            JOIN tawasulActivity ON (tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID)
            JOIN tawasulDaysOfWeek ON (tawasulDaysOfWeek.tawasulDaysOfWeekID=tawasulActivitySlot.tawasulDaysOfWeekID)
            WHERE tawasulActivitySlot.tawasulActivitySlotID=:tawasulActivitySlotID
            AND tawasulDaysOfWeek.tawasulDaysOfWeekID-1 = WEEKDAY(:date)";
        
        return $this->db()->selectOne($sql, $data);
    }

    public function deleteCoverageDatesByAbsenceID($tawasulStaffAbsenceID)
    {
        $data = ['tawasulStaffAbsenceID' => $tawasulStaffAbsenceID];
        $sql = "DELETE tawasulStaffCoverageDate FROM tawasulStaffCoverageDate
                JOIN tawasulStaffAbsenceDate ON (tawasulStaffAbsenceDate.tawasulStaffAbsenceDateID=tawasulStaffCoverageDate.tawasulStaffAbsenceDateID)
                WHERE tawasulStaffAbsenceDate.tawasulStaffAbsenceID = :tawasulStaffAbsenceID";

        return $this->db()->delete($sql, $data);
    }
}
