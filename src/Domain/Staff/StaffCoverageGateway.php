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
 * Staff Coverage Gateway
 *
 * @version v18
 * @since   v18
 */
class StaffCoverageGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulStaffCoverage';
    private static $primaryKey = 'tawasulStaffCoverageID';

    private static $searchableColumns = ['absence.username', 'absence.preferredName', 'absence.surname', 'coverage.username', 'coverage.preferredName', 'coverage.surname', 'status.preferredName', 'status.surname', 'tawasulStaffCoverage.status'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryCoverageBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulStaffCoverage.tawasulStaffCoverageID', 'tawasulStaffCoverage.status',  'tawasulStaffAbsenceType.name as type', 'tawasulStaffAbsence.reason', 'tawasulStaffCoverageDate.date', 'COUNT(*) as days', 'MIN(date) as dateStart', 'MAX(date) as dateEnd', 'tawasulStaffCoverageDate.allDay', 'tawasulStaffCoverageDate.timeStart', 'tawasulStaffCoverageDate.timeEnd', 'tawasulStaffCoverage.timestampStatus', 'tawasulStaffCoverage.timestampCoverage', 'tawasulStaffCoverage.tawasulStaffAbsenceID',
                'tawasulStaffCoverage.tawasulPersonID', 'absence.title AS titleAbsence', 'absence.preferredName AS preferredNameAbsence', 'absence.surname AS surnameAbsence', 
                'tawasulStaffCoverage.tawasulPersonIDCoverage', 'coverage.title as titleCoverage', 'coverage.preferredName as preferredNameCoverage', 'coverage.surname as surnameCoverage',
                'tawasulStaffCoverage.tawasulPersonIDStatus', 'status.title as titleStatus', 'status.preferredName as preferredNameStatus', 'status.surname as surnameStatus',
                'tawasulStaffCoverage.notesStatus', 'absenceStaff.jobTitle as jobTitleAbsence', 'tawasulStaffCoverageDate.foreignTableID',
                '(CASE WHEN tawasulStaffCoverage.status = "Pending" THEN 0 ELSE tawasulStaffCoverage.status END) as statusSort',

                '(CASE WHEN foreignTable="tawasulTTDayRowClass" THEN tawasulTTColumnRow.name WHEN foreignTable="tawasulStaffDutyPerson" THEN "Staff Duty" WHEN foreignTable="tawasulActivitySlot" THEN "Activity" END ) as period',
                '(CASE WHEN foreignTable="tawasulTTDayRowClass" THEN CONCAT(tawasulCourse.nameShort, ".", tawasulCourseClass.nameShort) WHEN foreignTable="tawasulStaffDutyPerson" THEN tawasulStaffDuty.name WHEN foreignTable="tawasulActivitySlot" THEN tawasulActivity.name END) as contextName', 'tawasulStaffCoverageDate.reason as coverageReason'
            ])
            ->innerJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID')
            ->innerJoin('tawasulSchoolYear', 'tawasulStaffCoverageDate.date BETWEEN firstDay AND lastDay')
            ->leftJoin('tawasulStaffAbsence', 'tawasulStaffCoverage.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID')
            ->leftJoin('tawasulStaffAbsenceType', 'tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID')

            ->leftJoin('tawasulTTDayRowClass', 'tawasulTTDayRowClass.tawasulTTDayRowClassID=tawasulStaffCoverageDate.foreignTableID AND tawasulStaffCoverageDate.foreignTable="tawasulTTDayRowClass"')
            ->leftJoin('tawasulTTColumnRow', 'tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID')
            ->leftJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseClassID=tawasulTTDayRowClass.tawasulCourseClassID')
            ->leftJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')

            ->leftJoin('tawasulStaffDutyPerson', 'tawasulStaffDutyPerson.tawasulStaffDutyPersonID=tawasulStaffCoverageDate.foreignTableID AND tawasulStaffCoverageDate.foreignTable="tawasulStaffDutyPerson"')
            ->leftJoin('tawasulStaffDuty', 'tawasulStaffDutyPerson.tawasulStaffDutyID=tawasulStaffDuty.tawasulStaffDutyID')

            ->leftJoin('tawasulActivitySlot', 'tawasulActivitySlot.tawasulActivitySlotID=tawasulStaffCoverageDate.foreignTableID AND tawasulStaffCoverageDate.foreignTable="tawasulActivitySlot"')
            ->leftJoin('tawasulActivity', 'tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID')

            ->leftJoin('tawasulPerson AS coverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID')
            ->leftJoin('tawasulPerson AS status', 'tawasulStaffCoverage.tawasulPersonIDStatus=status.tawasulPersonID')
            ->leftJoin('tawasulPerson AS absence', 'tawasulStaffCoverage.tawasulPersonID=absence.tawasulPersonID')
            ->leftJoin('tawasulStaff AS absenceStaff', 'absence.tawasulPersonID=absenceStaff.tawasulPersonID')

            ->where('tawasulSchoolYear.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->groupBy(['tawasulStaffCoverage.tawasulStaffCoverageID']);

        $criteria->addFilterRules($this->getSharedFilterRules());

        return $this->runQuery($query, $criteria);
    }

    public function queryCoverageByPersonCovering(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonID, $grouped = true)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulStaffCoverage.tawasulStaffCoverageID', 'tawasulStaffCoverage.status', 'tawasulStaffCoverage.requestType', 'tawasulStaffAbsenceType.name as type', 'tawasulStaffAbsence.reason', 'tawasulStaffCoverageDate.date', 'COUNT(*) as days', 'MIN(tawasulStaffCoverageDate.date) as dateStart', 'MAX(tawasulStaffCoverageDate.date) as dateEnd', 'tawasulStaffCoverageDate.allDay', 'tawasulStaffCoverageDate.timeStart', 'tawasulStaffCoverageDate.timeEnd', 'timestampStatus', 'timestampCoverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage', 
                'tawasulStaffCoverage.tawasulPersonID', 'absence.title AS titleAbsence', 'absence.preferredName AS preferredNameAbsence', 'absence.surname AS surnameAbsence',
                'tawasulStaffCoverage.tawasulPersonIDStatus', 'status.title as titleStatus', 'status.preferredName as preferredNameStatus', 'status.surname as surnameStatus',
                'tawasulStaffCoverage.notesStatus', 'absenceStaff.jobTitle as jobTitleAbsence', 'SUM(tawasulStaffCoverageDate.value) as value',
                'tawasulStaffCoverageDate.foreignTableID AS tawasulTTDayRowClassID', 'tawasulTTDayRowClass.tawasulTTDayID', 'tawasulSpace.name as roomName', 'tawasulSpace.phoneInternal', 'tawasulCourse.tawasulCourseID', 'tawasulCourse.name as courseName', 'tawasulCourse.nameShort as course',  'tawasulCourse.tawasulYearGroupIDList', 'tawasulCourseClass.tawasulCourseClassID', 'tawasulCourseClass.nameShort as class', 'tawasulTTColumnRow.tawasulTTColumnRowID', 'tawasulTTColumnRow.name', 'tawasulTTColumnRow.nameShort', 'tawasulTTSpaceChange.tawasulTTSpaceChangeID as spaceChanged', 'spaceChange.name as roomNameChange', 'spaceChange.phoneInternal as phoneChange', 'tawasulStaffCoverageDate.foreignTableID',
                '(CASE WHEN foreignTable="tawasulTTDayRowClass" THEN CONCAT(tawasulCourse.nameShort, ".", tawasulCourseClass.nameShort) WHEN foreignTable="tawasulStaffDutyPerson" THEN "Staff Duty" WHEN foreignTable="tawasulActivitySlot" THEN "Activity" ELSE tawasulStaffCoverageDate.reason END) as contextName'
            ])
            ->leftJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID')
            ->leftJoin('tawasulStaffAbsence', 'tawasulStaffCoverage.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID')
            ->leftJoin('tawasulStaffAbsenceType', 'tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID')

            ->leftJoin('tawasulTTDayRowClass', 'tawasulStaffCoverageDate.foreignTable="tawasulTTDayRowClass" AND tawasulTTDayRowClass.tawasulTTDayRowClassID=tawasulStaffCoverageDate.foreignTableID')
            ->leftJoin('tawasulTTColumnRow', 'tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID')
            ->leftJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseClassID=tawasulTTDayRowClass.tawasulCourseClassID')
            ->leftJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->leftJoin('tawasulSpace', 'tawasulSpace.tawasulSpaceID=tawasulTTDayRowClass.tawasulSpaceID')
            
            ->leftJoin('tawasulTTSpaceChange', 'tawasulTTSpaceChange.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTSpaceChange.date=tawasulStaffCoverageDate.date')
            ->leftJoin('tawasulSpace as spaceChange', 'spaceChange.tawasulSpaceID=tawasulTTSpaceChange.tawasulSpaceID')

            ->leftJoin('tawasulPerson AS status', 'tawasulStaffCoverage.tawasulPersonIDStatus=status.tawasulPersonID')
            ->leftJoin('tawasulPerson AS absence', 'tawasulStaffCoverage.tawasulPersonID=absence.tawasulPersonID')
            ->leftJoin('tawasulStaff AS absenceStaff', 'absence.tawasulPersonID=absenceStaff.tawasulPersonID')

            ->where('tawasulStaffCoverage.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulStaffCoverage.tawasulPersonIDCoverage = :tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->groupBy($grouped ? ['tawasulStaffCoverage.tawasulStaffCoverageID'] : ['tawasulStaffCoverageDate.tawasulStaffCoverageDateID'])
            ->orderBy(["tawasulStaffCoverage.status = 'Requested' DESC"]);

        $criteria->addFilterRules($this->getSharedFilterRules());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get coverage for a single date grouped by timetable column.
     *
     * @param string $tawasulSchoolYearID
     * @param string $date
     * @return Result
     */
    public function selectCoverageByTimetableDate($tawasulSchoolYearID, $date)
    {
        $cols = [
             'tawasulStaffCoverageDate.foreignTableID', 'tawasulStaffCoverageDate.foreignTable', 'tawasulStaffCoverage.tawasulStaffCoverageID', 'tawasulStaffCoverageDate.tawasulStaffCoverageDateID', 'tawasulStaffCoverage.status', 'tawasulStaffCoverageDate.date', 'tawasulStaffCoverageDate.allDay', 'tawasulStaffCoverageDate.timeStart', 'tawasulStaffCoverageDate.timeEnd', 'tawasulStaffCoverage.timestampStatus', 'tawasulStaffCoverage.timestampCoverage', 'tawasulStaffCoverage.notesStatus', 'tawasulStaffCoverage.tawasulStaffAbsenceID', 'tawasulStaffAbsence.status as absenceStatus', 'tawasulStaffAbsenceType.name AS type', 'tawasulStaffAbsence.reason',
            'tawasulStaffAbsence.tawasulPersonID', 'absence.title AS titleAbsence', 'absence.preferredName AS preferredNameAbsence', 'absence.surname AS surnameAbsence', 'absenceStaff.initials as initialsAbsence',  
            'tawasulStaffCoverage.tawasulPersonIDCoverage', 'coverage.title as titleCoverage', 'coverage.preferredName as preferredNameCoverage', 'coverage.surname as surnameCoverage',
            'tawasulStaffCoverage.tawasulPersonIDStatus', 'status.title as titleStatus', 'status.preferredName as preferredNameStatus', 'status.surname as surnameStatus',
        ];

        $query = $this
            ->newSelect()
            ->from('tawasulStaffAbsence')
            ->cols(array_merge(['CONCAT("tt-", tawasulTTColumnRow.timeStart, "-", tawasulTTColumnRow.timeEnd) as groupBy', '"Class" as context', 'CONCAT(tawasulCourse.nameShort, ".", tawasulCourseClass.nameShort) as contextName','tawasulTTColumnRow.name as period', 'tawasulCourse.tawasulCourseID', 'tawasulCourse.tawasulDepartmentID', 'tawasulCourseClass.tawasulCourseClassID', 'tawasulTTDay.tawasulTTDayID', '"" as tawasulStaffDutyID', '(CASE WHEN tawasulSpaceChanged.tawasulSpaceID IS NOT NULL THEN tawasulSpaceChanged.name ELSE tawasulSpace.name END) AS space', '"" AS tawasulActivityID'], $cols))

            ->innerJoin('tawasulStaffAbsenceDate', 'tawasulStaffAbsenceDate.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID')
            ->innerJoin('tawasulStaffAbsenceType', 'tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID')
            ->innerJoin('tawasulStaffCoverage', 'tawasulStaffCoverage.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID AND tawasulStaffCoverage.status <> "Cancelled" AND tawasulStaffCoverage.status <> "Declined"')
            ->innerJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID AND tawasulStaffCoverageDate.date=tawasulStaffAbsenceDate.date')

            
            ->leftJoin('tawasulTTDayRowClass', 'tawasulTTDayRowClass.tawasulTTDayRowClassID=tawasulStaffCoverageDate.foreignTableID')
            ->leftJoin('tawasulTTColumnRow', 'tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID')
            ->leftJoin('tawasulTTDay', 'tawasulTTDay.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID')
            ->leftJoin('tawasulTTDayDate', 'tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID AND tawasulTTDayDate.date=:date')

            ->leftJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseClassID=tawasulTTDayRowClass.tawasulCourseClassID')
            ->leftJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->leftJoin('tawasulSpace', 'tawasulSpace.tawasulSpaceID=tawasulTTDayRowClass.tawasulSpaceID')
            ->leftJoin('tawasulTTSpaceChange', 'tawasulTTSpaceChange.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTSpaceChange.date=:date')
            ->leftJoin('tawasulSpace AS tawasulSpaceChanged', 'tawasulSpaceChanged.tawasulSpaceID=tawasulTTSpaceChange.tawasulSpaceID')
            
            ->leftJoin('tawasulPerson AS coverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID')
            ->leftJoin('tawasulPerson AS status', 'tawasulStaffCoverage.tawasulPersonIDStatus=status.tawasulPersonID')
            ->leftJoin('tawasulPerson AS absence', 'tawasulStaffCoverage.tawasulPersonID=absence.tawasulPersonID')
            ->leftJoin('tawasulStaff AS absenceStaff', 'absence.tawasulPersonID=absenceStaff.tawasulPersonID')
            
            ->where('tawasulStaffCoverageDate.foreignTable="tawasulTTDayRowClass"')
            ->where('tawasulStaffCoverage.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulStaffAbsenceDate.date = :date')
            ->bindValue('date', $date)
            ->where('tawasulStaffAbsence.coverageRequired <> "N"')
            ->groupBy(['tawasulStaffCoverageDate.tawasulStaffCoverageDateID']);


        $query->unionAll()
            ->from('tawasulStaffAbsence')
            ->cols(array_merge(['CONCAT("duty-", tawasulStaffDuty.timeStart, "-", tawasulStaffDuty.timeEnd) as groupBy', 'tawasulStaffDuty.name as context', '"Staff Duty" contextName', '"Staff Duty" as period', '"" AS tawasulCourseID', '"" AS tawasulDepartmentID', '"" AS tawasulCourseClassID', '"" AS tawasulTTDayID', 'tawasulStaffDuty.tawasulStaffDutyID as tawasulStaffDutyID', 'tawasulStaffDuty.nameShort AS space', '"" AS tawasulActivityID'], $cols))

            ->innerJoin('tawasulStaffAbsenceDate', 'tawasulStaffAbsenceDate.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID')
            ->innerJoin('tawasulStaffAbsenceType', 'tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID')
            ->innerJoin('tawasulStaffCoverage', 'tawasulStaffCoverage.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID')
            ->innerJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID AND tawasulStaffCoverageDate.date=tawasulStaffAbsenceDate.date')

            ->innerJoin('tawasulStaffDutyPerson', 'tawasulStaffDutyPerson.tawasulStaffDutyPersonID=tawasulStaffCoverageDate.foreignTableID')
            ->innerJoin('tawasulStaffDuty', 'tawasulStaffDuty.tawasulStaffDutyID=tawasulStaffDutyPerson.tawasulStaffDutyID')
            
            ->leftJoin('tawasulPerson AS coverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID')
            ->leftJoin('tawasulPerson AS status', 'tawasulStaffCoverage.tawasulPersonIDStatus=status.tawasulPersonID')
            ->leftJoin('tawasulPerson AS absence', 'tawasulStaffCoverage.tawasulPersonID=absence.tawasulPersonID')
            ->leftJoin('tawasulStaff AS absenceStaff', 'absence.tawasulPersonID=absenceStaff.tawasulPersonID')
            
            ->where('tawasulStaffCoverageDate.foreignTable="tawasulStaffDutyPerson"')
            ->where('tawasulStaffCoverage.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulStaffAbsenceDate.date = :date')
            ->bindValue('date', $date)
            ->where('tawasulStaffAbsence.coverageRequired <> "N"')
            ->where('tawasulStaffCoverage.status <> "Cancelled" AND tawasulStaffCoverage.status <> "Declined"')
            ->groupBy(['tawasulStaffCoverageDate.tawasulStaffCoverageDateID']);

        $query->unionAll()
            ->from('tawasulStaffAbsence')
            ->cols(array_merge(['"activity" as groupBy', '"Activity" as context', 'tawasulActivity.name contextName', '"Activity" as period', '"" AS tawasulCourseID', '"" AS tawasulDepartmentID', '"" AS tawasulCourseClassID', '"" AS tawasulTTDayID', '"" as tawasulStaffDutyID', '"" AS space', 'tawasulActivity.tawasulActivityID as tawasulActivityID'], $cols))

            ->innerJoin('tawasulStaffAbsenceDate', 'tawasulStaffAbsenceDate.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID')
            ->innerJoin('tawasulStaffAbsenceType', 'tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID')
            ->innerJoin('tawasulStaffCoverage', 'tawasulStaffCoverage.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID')
            ->innerJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID AND tawasulStaffCoverageDate.date=tawasulStaffAbsenceDate.date')

            ->innerJoin('tawasulActivitySlot', 'tawasulActivitySlot.tawasulActivitySlotID=tawasulStaffCoverageDate.foreignTableID')
            ->innerJoin('tawasulActivity', 'tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID')
            ->innerJoin('tawasulActivityStaff', 'tawasulActivitySlot.tawasulActivityID=tawasulActivityStaff.tawasulActivityID && tawasulActivityStaff.tawasulPersonID=tawasulStaffCoverage.tawasulPersonID')
            
            ->leftJoin('tawasulPerson AS coverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID')
            ->leftJoin('tawasulPerson AS status', 'tawasulStaffCoverage.tawasulPersonIDStatus=status.tawasulPersonID')
            ->leftJoin('tawasulPerson AS absence', 'tawasulStaffCoverage.tawasulPersonID=absence.tawasulPersonID')
            ->leftJoin('tawasulStaff AS absenceStaff', 'absence.tawasulPersonID=absenceStaff.tawasulPersonID')
            
            ->where('tawasulStaffCoverageDate.foreignTable="tawasulActivitySlot"')
            ->where('tawasulStaffCoverage.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulStaffAbsenceDate.date = :date')
            ->bindValue('date', $date)
            ->where('tawasulStaffAbsence.coverageRequired <> "N"')
            ->where('tawasulStaffCoverage.status <> "Cancelled" AND tawasulStaffCoverage.status <> "Declined"')
            ->groupBy(['tawasulStaffCoverageDate.tawasulStaffCoverageDateID']);

        $query->orderBy(['timeStart', 'timeEnd']);

        return $this->runSelect($query);
    }

    /**
     * Get ad hoc coverage for a single date.
     *
     * @param string $tawasulSchoolYearID
     * @param string $date
     * @return Result
     */
    public function selectAdHocCoverageByDate($tawasulSchoolYearID, $date)
    {
        $query = $this
            ->newSelect()
            ->from('tawasulStaffCoverage')
            ->cols(['"Ad Hoc" as context', 'tawasulStaffCoverageDate.reason as contextName','"Ad Hoc" as period', 'tawasulStaffCoverageDate.foreignTableID', 'tawasulStaffCoverageDate.foreignTable', 'tawasulStaffCoverage.tawasulStaffCoverageID', 'tawasulStaffCoverageDate.tawasulStaffCoverageDateID', 'tawasulStaffCoverage.status', 'tawasulStaffCoverageDate.date', 'tawasulStaffCoverageDate.allDay', 'tawasulStaffCoverageDate.timeStart', 'tawasulStaffCoverageDate.timeEnd', 'tawasulStaffCoverage.timestampStatus', 'tawasulStaffCoverage.timestampCoverage', 'tawasulStaffCoverage.notesStatus', 'tawasulStaffAbsence.tawasulStaffAbsenceID',
            '"" as absenceStatus', '"" as reason', '"" as type',
            'tawasulStaffCoverage.tawasulPersonIDCoverage', 'coverage.title as titleCoverage', 'coverage.preferredName as preferredNameCoverage', 'coverage.surname as surnameCoverage',
            'tawasulStaffCoverage.tawasulPersonIDStatus', 'status.title as titleStatus', 'status.preferredName as preferredNameStatus', 'status.surname as surnameStatus',
            'absence.title AS titleAbsence', 'absence.preferredName AS preferredNameAbsence', 'absence.surname AS surnameAbsence', ])

            ->innerJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID')
            ->leftJoin('tawasulStaffAbsence', 'tawasulStaffCoverage.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID')

            ->leftJoin('tawasulPerson AS coverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID')
            ->leftJoin('tawasulPerson AS status', 'tawasulStaffCoverage.tawasulPersonIDCoverage=status.tawasulPersonID')
            ->leftJoin('tawasulPerson AS absence', 'tawasulStaffCoverage.tawasulPersonID=absence.tawasulPersonID')
            
            ->where('tawasulStaffCoverageDate.foreignTable IS NULL')
            ->where('tawasulStaffCoverage.status <> "Cancelled" AND tawasulStaffCoverage.status <> "Declined"')
            ->where('tawasulStaffCoverage.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulStaffCoverageDate.date = :date')
            ->bindValue('date', $date)
            ->groupBy(['tawasulStaffCoverageDate.tawasulStaffCoverageDateID']);

        $query->orderBy(['timeStart', 'timeEnd']);

        return $this->runSelect($query);

    }

    public function selectCoverageByDateRange($dateStart, $dateEnd = null)
    {
        if (empty($dateEnd)) $dateEnd = $dateStart;

        $query = $this
            ->newSelect()
            ->from('tawasulStaffCoverage')
            ->cols(['tawasulStaffCoverage.tawasulPersonIDCoverage', 'tawasulStaffCoverageDate.date', 'tawasulStaffCoverageDate.value'])
            ->innerJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverage.tawasulStaffCoverageID=tawasulStaffCoverageDate.tawasulStaffCoverageID')
            ->where('tawasulStaffCoverageDate.date BETWEEN :dateStart AND :dateEnd')
            ->where("tawasulStaffCoverage.status = 'Accepted'")
            ->bindValue('dateStart', $dateStart)
            ->bindValue('dateEnd', $dateEnd);

        return $this->runSelect($query);
    }

    public function queryCoverageByPersonAbsent(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonID, $grouped = true)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulStaffCoverage.tawasulStaffCoverageID', 'tawasulStaffCoverage.status', 'tawasulStaffCoverage.requestType',  'tawasulStaffAbsence.tawasulStaffAbsenceID', 'tawasulStaffAbsenceType.name as type', 'tawasulStaffAbsence.reason', 'tawasulStaffAbsence.coverageRequired', 'tawasulStaffAbsence.status as absenceStatus', 'tawasulStaffCoverageDate.date',  'tawasulStaffCoverageDate.allDay', 'tawasulStaffCoverageDate.timeStart', 'tawasulStaffCoverageDate.timeEnd', 'timestampStatus', 'timestampCoverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage', 'tawasulStaffCoverage.tawasulPersonID', 
                'coverage.title as titleCoverage', 'coverage.preferredName as preferredNameCoverage', 'coverage.surname as surnameCoverage', 'tawasulStaffCoverage.notesStatus', 'tawasulStaffCoverage.notesCoverage', 'tawasulTTDayRowClass.tawasulTTDayRowClassID',  'tawasulStaffCoverageDate.foreignTable', 'tawasulStaffCoverageDate.foreignTableID',
                'tawasulCourse.tawasulCourseID', 'tawasulCourseClass.tawasulCourseClassID', 'tawasulCourse.tawasulDepartmentID',

                '(CASE WHEN foreignTable="tawasulTTDayRowClass" THEN tawasulTTColumnRow.name WHEN foreignTable="tawasulStaffDutyPerson" THEN "Staff Duty" WHEN foreignTable="tawasulActivitySlot" THEN "Activity" END ) as period',
                '(CASE WHEN foreignTable="tawasulTTDayRowClass" THEN CONCAT(tawasulCourse.nameShort, ".", tawasulCourseClass.nameShort) WHEN foreignTable="tawasulStaffDutyPerson" THEN tawasulStaffDuty.name WHEN foreignTable="tawasulActivitySlot" THEN tawasulActivity.name END ) as contextName'
            ])
            ->leftJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID')
            ->leftJoin('tawasulStaffAbsence', 'tawasulStaffCoverage.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID')
            ->leftJoin('tawasulStaffAbsenceType', 'tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID')
            ->leftJoin('tawasulPerson AS coverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID')

            ->leftJoin('tawasulTTDayRowClass', 'tawasulTTDayRowClass.tawasulTTDayRowClassID=tawasulStaffCoverageDate.foreignTableID AND tawasulStaffCoverageDate.foreignTable="tawasulTTDayRowClass"')
            ->leftJoin('tawasulTTColumnRow', 'tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID')
            ->leftJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseClassID=tawasulTTDayRowClass.tawasulCourseClassID')
            ->leftJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')

            ->leftJoin('tawasulStaffDutyPerson', 'tawasulStaffDutyPerson.tawasulStaffDutyPersonID=tawasulStaffCoverageDate.foreignTableID AND tawasulStaffCoverageDate.foreignTable="tawasulStaffDutyPerson"')
            ->leftJoin('tawasulStaffDuty', 'tawasulStaffDutyPerson.tawasulStaffDutyID=tawasulStaffDuty.tawasulStaffDutyID')

            ->leftJoin('tawasulActivitySlot', 'tawasulActivitySlot.tawasulActivitySlotID=tawasulStaffCoverageDate.foreignTableID AND tawasulStaffCoverageDate.foreignTable="tawasulActivitySlot"')
            ->leftJoin('tawasulActivity', 'tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID')

            ->where('tawasulStaffCoverage.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulStaffCoverage.tawasulPersonID = :tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->groupBy(['tawasulStaffCoverage.tawasulStaffCoverageID']);

        if ($grouped) {
            $query->cols(['COUNT(*) as days', 'MIN(tawasulStaffCoverageDate.date) as dateStart', 'MAX(tawasulStaffCoverageDate.date) as dateEnd'])
                  ->groupBy(['tawasulStaffCoverage.tawasulStaffCoverageID']);
        } else {
            $query->cols(['tawasulStaffCoverageDate.value as days', 'tawasulStaffCoverageDate.date as dateStart', 'tawasulStaffCoverageDate.date as dateEnd'])
                ->groupBy(['tawasulStaffCoverageDate.tawasulStaffCoverageDateID']);
        }

        $criteria->addFilterRules($this->getSharedFilterRules());

        return $this->runQuery($query, $criteria);
    }

    public function queryCoverageWithNoPersonAssigned(QueryCriteria $criteria, $substituteType = '')
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulStaffCoverage.tawasulStaffCoverageID', 'tawasulStaffCoverage.status',  'tawasulStaffAbsenceType.name as type', 'tawasulStaffAbsence.reason', 'date', 'COUNT(*) as days', 'MIN(date) as dateStart', 'MAX(date) as dateEnd', 'allDay', 'timeStart', 'timeEnd', 'timestampStatus', 'timestampCoverage', 'tawasulStaffCoverage.tawasulPersonIDCoverage', 'tawasulStaffCoverage.tawasulPersonID', 
                'absence.title AS titleAbsence', 'absence.preferredName AS preferredNameAbsence', 'absence.surname AS surnameAbsence', 'absenceStaff.jobTitle as jobTitleAbsence'
            ])
            ->innerJoin('tawasulStaffAbsence', 'tawasulStaffCoverage.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID')
            ->innerJoin('tawasulStaffAbsenceType', 'tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID')
            ->leftJoin('tawasulStaffCoverageDate', 'tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID')
            ->leftJoin('tawasulPerson AS absence', 'tawasulStaffCoverage.tawasulPersonID=absence.tawasulPersonID')
            ->leftJoin('tawasulStaff AS absenceStaff', 'absence.tawasulPersonID=absenceStaff.tawasulPersonID')
            ->where('tawasulStaffCoverage.tawasulPersonIDCoverage IS NULL')
            ->groupBy(['tawasulStaffCoverage.tawasulStaffCoverageID']);

        if (!empty($substituteType)) {
            $query->where("(tawasulStaffCoverage.substituteTypes = '' OR tawasulStaffCoverage.substituteTypes IS NULL OR FIND_IN_SET(:substituteType, tawasulStaffCoverage.substituteTypes))")
                  ->bindValue('substituteType', $substituteType);
        }

        $criteria->addFilterRules($this->getSharedFilterRules());

        return $this->runQuery($query, $criteria);
    }

    public function getCoverageDetailsByID($tawasulStaffCoverageID)
    {
        $data = ['tawasulStaffCoverageID' => $tawasulStaffCoverageID];
        $sql = "SELECT tawasulStaffCoverage.tawasulStaffCoverageID, tawasulStaffCoverage.status, tawasulStaffAbsence.tawasulStaffAbsenceID, tawasulStaffAbsenceType.name as type, tawasulStaffAbsence.reason, substituteTypes,
                MIN(date) as date, COUNT(*) as days, MIN(date) as dateStart, MAX(date) as dateEnd, MAX(allDay) as allDay, MIN(timeStart) as timeStart, MAX(timeEnd) as timeEnd, timestampStatus, timestampCoverage, tawasulStaffCoverage.requestType,
                tawasulStaffCoverage.notesCoverage, tawasulStaffCoverage.notesStatus, 0 as urgent, tawasulStaffAbsence.comment, tawasulStaffAbsence.notificationSent, tawasulStaffAbsence.tawasulGroupID, tawasulStaffAbsence.tawasulPersonIDApproval, tawasulStaffCoverage.notificationList as notificationListCoverage, tawasulStaffAbsence.notificationList as notificationListAbsence, 
                tawasulStaffCoverage.tawasulPersonID, absence.title AS titleAbsence, absence.preferredName AS preferredNameAbsence, absence.surname AS surnameAbsence, 
                tawasulStaffCoverage.tawasulPersonIDStatus, status.title AS titleStatus, status.preferredName AS preferredNameStatus, status.surname AS surnameStatus, 
                tawasulStaffCoverage.tawasulPersonIDCoverage, coverage.title as titleCoverage, coverage.preferredName as preferredNameCoverage, coverage.surname as surnameCoverage
            FROM tawasulStaffCoverage
            LEFT JOIN tawasulStaffCoverageDate ON (tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID)
            LEFT JOIN tawasulStaffAbsence ON (tawasulStaffAbsence.tawasulStaffAbsenceID=tawasulStaffCoverage.tawasulStaffAbsenceID)
            LEFT JOIN tawasulStaffAbsenceType ON (tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID)
            LEFT JOIN tawasulPerson AS coverage ON (tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID)
            LEFT JOIN tawasulPerson AS status ON (tawasulStaffCoverage.tawasulPersonIDStatus=status.tawasulPersonID)
            LEFT JOIN tawasulPerson AS absence ON (tawasulStaffCoverage.tawasulPersonID=absence.tawasulPersonID)
            WHERE tawasulStaffCoverage.tawasulStaffCoverageID=:tawasulStaffCoverageID
            GROUP BY tawasulStaffCoverage.tawasulStaffCoverageID
            ";

        return $this->db()->selectOne($sql, $data);
    }

    public function getStaffDutyCoverageByID($tawasulStaffDutyPersonID, $date)
    {
        $data = ['tawasulStaffDutyPersonID' => $tawasulStaffDutyPersonID, 'date' => $date];
        $sql = "SELECT tawasulStaffCoverage.tawasulStaffCoverageID, tawasulStaffCoverage.status, 
                tawasulStaffCoverage.tawasulPersonIDCoverage, coverage.title, coverage.preferredName, coverage.surname
            FROM tawasulStaffCoverage
            JOIN tawasulStaffCoverageDate ON (tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID)
            JOIN tawasulStaffDutyPerson ON (tawasulStaffDutyPerson.tawasulStaffDutyPersonID=tawasulStaffCoverageDate.foreignTableID AND tawasulStaffCoverageDate.foreignTable='tawasulStaffDutyPerson')
            LEFT JOIN tawasulPerson AS coverage ON (tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID)
            WHERE tawasulStaffDutyPerson.tawasulStaffDutyPersonID=:tawasulStaffDutyPersonID
            AND tawasulStaffCoverageDate.date=:date
            GROUP BY tawasulStaffCoverage.tawasulStaffCoverageID
            ";

        return $this->db()->selectOne($sql, $data);
    }

    public function getActivityCoverageByID($tawasulActivitySlotID, $date)
    {
        $data = ['tawasulActivitySlotID' => $tawasulActivitySlotID, 'date' => $date];
        $sql = "SELECT tawasulStaffCoverage.tawasulStaffCoverageID, tawasulStaffCoverage.status, 
                tawasulStaffCoverage.tawasulPersonIDCoverage, coverage.title, coverage.preferredName, coverage.surname
            FROM tawasulStaffCoverage
            JOIN tawasulStaffCoverageDate ON (tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID)
            JOIN tawasulActivitySlot ON (tawasulActivitySlot.tawasulActivitySlotID=tawasulStaffCoverageDate.foreignTableID AND tawasulStaffCoverageDate.foreignTable='tawasulActivitySlot')
            LEFT JOIN tawasulPerson AS coverage ON (tawasulStaffCoverage.tawasulPersonIDCoverage=coverage.tawasulPersonID)
            WHERE tawasulActivitySlot.tawasulActivitySlotID=:tawasulActivitySlotID
            AND tawasulStaffCoverageDate.date=:date
            GROUP BY tawasulStaffCoverage.tawasulStaffCoverageID
            ";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectTimetableRowsByCoverageDate($tawasulStaffCoverageID, $date)
    {
        $data = ['tawasulStaffCoverageID' => $tawasulStaffCoverageID, 'date' => $date];
        $sql = "SELECT tawasulCourseClass.tawasulCourseClassID, tawasulTTColumnRow.name as period, tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd, tawasulCourse.name as courseName, tawasulCourse.nameShort as courseNameShort, tawasulCourseClass.nameShort as className, tawasulCourseClass.attendance, tawasulSpace.name as spaceName
                FROM tawasulStaffCoverage
                JOIN tawasulStaffCoverageDate ON (tawasulStaffCoverage.tawasulStaffCoverageID=tawasulStaffCoverageDate.tawasulStaffCoverageID)
                JOIN tawasulTTDayRowClass ON (tawasulStaffCoverageDate.foreignTable='tawasulTTDayRowClass' AND tawasulTTDayRowClass.tawasulTTDayRowClassID=tawasulStaffCoverageDate.foreignTableID)
                JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID AND tawasulTTDayDate.date=tawasulStaffCoverageDate.date)
                JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID)
                JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulStaffCoverage.tawasulPersonID AND tawasulCourseClassPerson.tawasulCourseClassID=tawasulTTDayRowClass.tawasulCourseClassID)
                JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                LEFT JOIN tawasulSpace ON (tawasulSpace.tawasulSpaceID=tawasulTTDayRowClass.tawasulSpaceID)
                WHERE tawasulStaffCoverage.tawasulStaffCoverageID=:tawasulStaffCoverageID 
                AND (tawasulCourseClassPerson.role = 'Teacher' OR tawasulCourseClassPerson.role = 'Assistant')
                AND tawasulStaffCoverageDate.date=:date
                AND tawasulCourse.tawasulSchoolYearID=tawasulStaffCoverage.tawasulSchoolYearID
                AND (tawasulStaffCoverageDate.allDay='Y' 
                    OR (tawasulStaffCoverageDate.allDay='N' AND tawasulTTColumnRow.timeStart <= tawasulStaffCoverageDate.timeEnd AND tawasulTTColumnRow.timeEnd >= tawasulStaffCoverageDate.timeStart)
                )
                GROUP BY tawasulTTColumnRow.tawasulTTColumnRowID, tawasulCourse.tawasulCourseID, tawasulCourseClass.tawasulCourseClassID, tawasulSpace.tawasulSpaceID
                ORDER BY tawasulTTColumnRow.timeStart
        ";

        return $this->db()->select($sql, $data);
    }

    public function selectCoverageCountsByPerson($tawasulPersonID, $date = null)
    {
        $tawasulPersonIDCoverage = is_array($tawasulPersonID)? implode(',', $tawasulPersonID) : $tawasulPersonID;

        $data = ['tawasulPersonIDCoverage' => $tawasulPersonIDCoverage, 'today' => $date ?? date('Y-m-d')];
        $sql = "SELECT tawasulStaffCoverage.tawasulPersonIDCoverage, COUNT(DISTINCT tawasulStaffCoverageDate.tawasulStaffCoverageDateID) as yearlyCoverage, SUM(CASE WHEN tawasulStaffCoverageDate.date BETWEEN DATE_ADD(:today, INTERVAL(1-DAYOFWEEK(:today)) DAY) AND DATE_ADD(:today, INTERVAL(7-DAYOFWEEK(:today)) DAY) THEN 1 ELSE 0 END) as weeklyCoverage, SUM(CASE WHEN tawasulStaffCoverageDate.date BETWEEN DATE_ADD(:today, INTERVAL(1-DAYOFWEEK(:today)) DAY) AND DATE_ADD(:today, INTERVAL(7-DAYOFWEEK(:today)) DAY) THEN TIMESTAMPDIFF(MINUTE, tawasulStaffCoverageDate.timeStart, tawasulStaffCoverageDate.timeEnd) ELSE 0 END) as weeklyCoverageMins
                FROM tawasulStaffCoverage
                JOIN tawasulStaffCoverageDate ON (tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID)
                JOIN tawasulSchoolYear ON (tawasulStaffCoverage.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                WHERE FIND_IN_SET(tawasulStaffCoverage.tawasulPersonIDCoverage, :tawasulPersonIDCoverage)
                AND tawasulStaffCoverage.status='Accepted'
                AND tawasulSchoolYear.status='Current'
                GROUP BY tawasulStaffCoverage.tawasulPersonIDCoverage";

        return $this->db()->select($sql, $data);
    }

    public function selectTimetableCountsByPerson($tawasulPersonID, $dateStart, $dateEnd)
    {
        $tawasulPersonIDList = is_array($tawasulPersonID)? implode(',', $tawasulPersonID) : $tawasulPersonID;

        $data = ['tawasulPersonIDList' => $tawasulPersonIDList, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT tawasulCourseClassPerson.tawasulPersonID, COUNT(DISTINCT tawasulTTDayRowClass.tawasulTTDayRowClassID) as totalClasses, SUM(TIMESTAMPDIFF(MINUTE, tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd)) as totalMinutes
                FROM tawasulCourseClassPerson 
                JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
                JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
                JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID)
                JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID 
                    AND tawasulTTDay.tawasulTTColumnID=tawasulTTColumnRow.tawasulTTColumnID)
                LEFT JOIN tawasulTTDayRowClassException ON (tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTDayRowClassException.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID)
                WHERE FIND_IN_SET(tawasulCourseClassPerson.tawasulPersonID, :tawasulPersonIDList) 
                AND (tawasulCourseClassPerson.role = 'Teacher' OR tawasulCourseClassPerson.role = 'Assistant')
                AND tawasulTTDayRowClassExceptionID IS NULL
                AND tawasulTTDayDate.date BETWEEN :dateStart AND :dateEnd
                GROUP BY tawasulCourseClassPerson.tawasulPersonID";
                
        return $this->db()->select($sql, $data);
    }

    public function selectCoverageByAbsenceID($tawasulStaffAbsenceID, $grouped = false)
    {
        $data = ['tawasulStaffAbsenceID' => $tawasulStaffAbsenceID];
        $sql = "SELECT tawasulStaffCoverageID
                FROM tawasulStaffCoverage
                WHERE tawasulStaffCoverage.tawasulStaffAbsenceID = :tawasulStaffAbsenceID ";
        if ($grouped) {
            $sql .= " GROUP BY tawasulStaffCoverage.tawasulStaffAbsenceID ";
        }
        $sql .= " ORDER BY tawasulStaffCoverage.timestampStatus ASC";

        return $this->db()->select($sql, $data);
    }

    public function deleteCoverageByAbsenceID($tawasulStaffAbsenceID)
    {
        $data = ['tawasulStaffAbsenceID' => $tawasulStaffAbsenceID];
        $sql = "DELETE FROM tawasulStaffCoverage
                WHERE tawasulStaffCoverage.tawasulStaffAbsenceID = :tawasulStaffAbsenceID";

        return $this->db()->delete($sql, $data);
    }

    protected function getSharedFilterRules()
    {
        return [
            'requested' => function ($query, $requested) {
                return $requested == 'Y'
                    ? $query->where("tawasulStaffCoverage.status = 'Requested'")
                    : $query->where("tawasulStaffCoverage.status <> 'Requested'");
            },
            'status' => function ($query, $status) {
                return $query->where('tawasulStaffCoverage.status = :status')
                             ->bindValue('status', $status);
            },
            'dateStart' => function ($query, $dateStart) {
                return $query->where("tawasulStaffCoverageDate.date >= :dateStart")
                             ->bindValue('dateStart', $dateStart);
            },
            'dateEnd' => function ($query, $dateEnd) {
                return $query->where("tawasulStaffCoverageDate.date <= :dateEnd")
                             ->bindValue('dateEnd', $dateEnd);
            },
            'date' => function ($query, $date) {
                switch (ucfirst($date)) {
                    case 'Upcoming': return $query->where("tawasulStaffCoverageDate.date >= CURRENT_DATE()")->where("tawasulStaffCoverage.status <> 'Declined' AND tawasulStaffCoverage.status <> 'Cancelled'")->orderBy(['tawasulStaffCoverageDate.date']);
                    case 'Today'   : return $query->where("tawasulStaffCoverageDate.date = CURRENT_DATE()");
                    case 'Past'    : return $query->where("tawasulStaffCoverageDate.date < CURRENT_DATE()");
                    default: return $query->bindValue('dateFilter', $date)->where("tawasulStaffCoverageDate.date = :dateFilter");
                }
            },
        ];
    }
}
