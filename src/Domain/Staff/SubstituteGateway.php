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

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * Substitute Gateway
 *
 * @version v18
 * @since   v18
 */
class SubstituteGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulSubstitute';
    private static $primaryKey = 'tawasulSubstituteID';

    private static $searchableColumns = ['preferredName', 'surname', 'username'];
    
    /**
     * Queries the list of users for the Manage Substitutes page.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAllSubstitutes(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols([
                'tawasulSubstitute.tawasulSubstituteID', 'tawasulSubstitute.type', 'tawasulSubstitute.details', 'tawasulSubstitute.priority', 'tawasulSubstitute.active',
                'tawasulPerson.tawasulPersonID', 'tawasulPerson.title', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.status', 'tawasulPerson.image_240', 'tawasulPerson.username',
                'tawasulStaff.tawasulStaffID', 'tawasulStaff.type as staffType', 'tawasulStaff.jobTitle'
                
            ])
            ->innerJoin('tawasulRole', 'tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary')
            ->leftJoin('tawasulStaff', 'tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID');

        if ($criteria->hasFilter('allStaff', 'Y')) {
            $query->leftJoin('tawasulSubstitute', 'tawasulPerson.tawasulPersonID=tawasulSubstitute.tawasulPersonID')
                ->where("tawasulRole.category='Staff' AND tawasulStaff.type='Teaching'")
                ->where('tawasulStaff.coverageExclude != "Y"');
                
            $criteria->addFilterRules([
                'active' => function ($query, $active) {
                    if ($active != 'Y') return $query;
                    return $query->where("tawasulPerson.status='Full'");
                },
            ]);
            
        } else {
            $query->innerJoin('tawasulSubstitute', 'tawasulPerson.tawasulPersonID=tawasulSubstitute.tawasulPersonID');

            $criteria->addFilterRules([
                'active' => function ($query, $active) {
                    return $query
                        ->where('tawasulSubstitute.active = :active')
                        ->bindValue('active', $active);
                },
            ]);
        }

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('tawasulPerson.status = :status')
                    ->bindValue('status', ucfirst($status));
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryUnavailableDatesBySub(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonIDCoverage)
    {
        $query = $this
            ->newQuery()
            ->cols([
                'date as groupBy', 'tawasulStaffCoverageDate.*', 'tawasulStaffCoverageDate.tawasulPersonIDUnavailable as tawasulPersonID'
            ])
            ->from('tawasulStaffCoverageDate')
            ->innerJoin('tawasulSchoolYear', 'date BETWEEN tawasulSchoolYear.firstDay AND tawasulSchoolYear.lastDay')
            ->where('tawasulStaffCoverageDate.tawasulPersonIDUnavailable = :tawasulPersonIDCoverage')
            ->bindValue('tawasulPersonIDCoverage', $tawasulPersonIDCoverage)
            ->where('tawasulSchoolYear.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    public function queryAvailableSubsByDate($criteria, $date, $timeStart = null, $timeEnd = null)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.tawasulPersonID as groupBy', ':date as date', 'tawasulPerson.tawasulPersonID', 'tawasulSubstitute.details', '(CASE WHEN tawasulSubstitute.tawasulSubstituteID IS NOT NULL THEN tawasulSubstitute.type ELSE "Internal Substitute" END) as type', 'tawasulSubstitute.priority', 'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulPerson.status', 'tawasulPerson.image_240', 'tawasulPerson.email', 'tawasulPerson.phone1', 'tawasulPerson.phone1Type', 'tawasulPerson.phone1CountryCode', 'tawasulStaff.tawasulStaffID', 'tawasulPerson.username', 'tawasulStaff.jobTitle', 'tawasulStaff.coveragePriority',
                '(absence.ID IS NULL AND coverage.ID IS NULL AND timetable.ID IS NULL AND duty.ID IS NULL AND activity.ID IS NULL AND unavailable.tawasulStaffCoverageDateID IS NULL) as available',
                'absence.status as absence', 'coverage.status as coverage', 'timetable.status as timetable', 'timetable.ID as courseClassID', 'duty.ID as duty', 'activity.ID as activity', 'unavailable.reason as unavailable',
            ])
            ->where('tawasulStaff.coverageExclude != "Y"')
            ->leftJoin('tawasulSubstitute', 'tawasulSubstitute.tawasulPersonID=tawasulPerson.tawasulPersonID');
                
        if ($criteria->hasFilter('allStaff', 'Y')) {
            $query->innerJoin('tawasulStaff', 'tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID')
                  ->innerJoin('tawasulRole', 'tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary');
        } else {
            $query->leftJoin('tawasulStaff', 'tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID');
        }

        if (empty($timeStart) || empty($timeEnd)) {
            $times = $this->db()->selectOne("SELECT schoolStart, schoolEnd FROM tawasulDaysOfWeek WHERE name=:dayOfWeek", ['dayOfWeek' => date('l', strtotime($date))]);

            $timeStart = $times['schoolStart'];
            $timeEnd = $times['schoolEnd'];
        }

        $query->bindValue('timeStart', $timeStart)
                ->bindValue('timeEnd', $timeEnd);
        $query->cols([':timeStart as timeStart', ':timeEnd as timeEnd']);

        // Not available?
        $query->leftJoin('tawasulStaffCoverageDate as unavailable', "unavailable.tawasulPersonIDUnavailable=tawasulPerson.tawasulPersonID AND unavailable.date = :date 
                AND (unavailable.allDay='Y' OR (unavailable.allDay='N' AND unavailable.timeStart < :timeEnd AND unavailable.timeEnd > :timeStart))");

        // Already covering?
        $query->joinSubSelect(
            'LEFT',
            "SELECT tawasulStaffCoverageDateID as ID, (CASE WHEN absence.tawasulPersonID IS NOT NULL THEN CONCAT(absence.preferredName, ' ', absence.surname) ELSE CONCAT(status.preferredName, ' ', status.surname) END) as status, tawasulStaffCoverage.tawasulPersonIDCoverage, tawasulStaffCoverageDate.date, allDay, timeStart, timeEnd
                FROM tawasulStaffCoverage 
                JOIN tawasulStaffCoverageDate ON (tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID)
                LEFT JOIN tawasulStaffAbsence ON (tawasulStaffAbsence.tawasulStaffAbsenceID=tawasulStaffCoverage.tawasulStaffAbsenceID)
                LEFT JOIN tawasulPerson as absence ON (absence.tawasulPersonID=tawasulStaffAbsence.tawasulPersonID)
                LEFT JOIN tawasulPerson as status ON (status.tawasulPersonID=tawasulStaffCoverage.tawasulPersonID)
                WHERE tawasulStaffCoverage.status = 'Accepted'",
            'coverage',
            "coverage.tawasulPersonIDCoverage=tawasulPerson.tawasulPersonID AND coverage.date = :date 
                AND (coverage.allDay='Y' OR (coverage.allDay='N' AND coverage.timeStart < :timeEnd AND coverage.timeEnd > :timeStart))"
        );

        // Already absent?
        $query->joinSubSelect(
            'LEFT',
            "SELECT tawasulStaffAbsenceDateID as ID, tawasulStaffAbsenceType.name as status, tawasulStaffAbsence.tawasulPersonID, tawasulStaffAbsenceDate.date, allDay, timeStart, timeEnd
                FROM tawasulStaffAbsence 
                JOIN tawasulStaffAbsenceDate ON (tawasulStaffAbsenceDate.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID)
                JOIN tawasulStaffAbsenceType ON (tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID=tawasulStaffAbsence.tawasulStaffAbsenceTypeID) 
                WHERE tawasulStaffAbsence.status <> 'Declined'",
            'absence',
            "absence.tawasulPersonID=tawasulPerson.tawasulPersonID AND absence.date = :date 
                AND (absence.allDay='Y' OR (absence.allDay='N' AND absence.timeStart < :timeEnd AND absence.timeEnd > :timeStart))"
        );

        // Already teaching?
        $query->joinSubSelect(
            'LEFT',
            "SELECT tawasulTTDayRowClass.tawasulCourseClassID as ID, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as status, tawasulCourseClassPerson.tawasulPersonID, tawasulTTDayDate.date, timeStart, timeEnd
                FROM tawasulCourseClassPerson 
                JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
                JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
                JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID)
                JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID 
                    AND tawasulTTDay.tawasulTTColumnID=tawasulTTColumnRow.tawasulTTColumnID)
                JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulTTDayRowClass.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                LEFT JOIN tawasulTTDayRowClassException ON (tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTDayRowClassException.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID)
                WHERE (tawasulCourseClassPerson.role = 'Teacher' OR tawasulCourseClassPerson.role = 'Assistant')
                AND tawasulTTDayRowClassExceptionID IS NULL",
            'timetable',
            "timetable.tawasulPersonID=tawasulPerson.tawasulPersonID AND timetable.date = :date 
                AND timetable.timeStart < :timeEnd AND timetable.timeEnd > :timeStart"
        );

        // Already doing staff duty?
        $query->joinSubSelect(
            'LEFT',
            "SELECT tawasulStaffDutyPerson.tawasulStaffDutyPersonID as ID, tawasulStaffDuty.name as status, tawasulStaffDutyPerson.tawasulPersonID, :date as date, tawasulStaffDuty.timeStart, tawasulStaffDuty.timeEnd, tawasulDaysOfWeek.tawasulDaysOfWeekID
                FROM tawasulStaffDutyPerson 
                JOIN tawasulStaffDuty ON (tawasulStaffDutyPerson.tawasulStaffDutyID=tawasulStaffDuty.tawasulStaffDutyID)
                JOIN tawasulDaysOfWeek ON (tawasulStaffDutyPerson.tawasulDaysOfWeekID=tawasulDaysOfWeek.tawasulDaysOfWeekID)",
            'duty',
            "duty.tawasulPersonID=tawasulPerson.tawasulPersonID AND (duty.tawasulDaysOfWeekID-1) = WEEKDAY(:date) 
                AND duty.timeStart < :timeEnd AND duty.timeEnd > :timeStart"
        );

        // Already doing activity?
        $activityDateType = $this->db()->selectOne("SELECT value FROM tawasulSetting WHERE scope='Activities' AND name='dateType'");
        if ($activityDateType == 'Term') {
            $sql = "SELECT tawasulActivityStaff.tawasulActivityStaffID as ID, tawasulActivity.name as status, tawasulActivityStaff.tawasulPersonID, :date as date, tawasulActivitySlot.timeStart, tawasulActivitySlot.timeEnd, tawasulDaysOfWeek.tawasulDaysOfWeekID, activityTerm.firstDay as dateStart, activityTerm.lastDay as dateEnd
            FROM tawasulActivityStaff 
            JOIN tawasulActivity ON (tawasulActivityStaff.tawasulActivityID=tawasulActivity.tawasulActivityID)
            JOIN tawasulActivitySlot ON (tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID)
            JOIN tawasulDaysOfWeek ON (tawasulActivitySlot.tawasulDaysOfWeekID=tawasulDaysOfWeek.tawasulDaysOfWeekID)
            JOIN tawasulSchoolYearTerm as activityTerm ON FIND_IN_SET(activityTerm.tawasulSchoolYearTermID, tawasulActivity.tawasulSchoolYearTermIDList)";
        } else {
            $sql = "SELECT tawasulActivityStaff.tawasulActivityStaffID as ID, tawasulActivity.name as status, tawasulActivityStaff.tawasulPersonID, :date as date, tawasulActivitySlot.timeStart, tawasulActivitySlot.timeEnd, tawasulDaysOfWeek.tawasulDaysOfWeekID, tawasulActivity.programStart as dateStart, tawasulActivity.programEnd as dateEnd
            FROM tawasulActivityStaff 
            JOIN tawasulActivity ON (tawasulActivityStaff.tawasulActivityID=tawasulActivity.tawasulActivityID)
            JOIN tawasulActivitySlot ON (tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID)
            JOIN tawasulDaysOfWeek ON (tawasulActivitySlot.tawasulDaysOfWeekID=tawasulDaysOfWeek.tawasulDaysOfWeekID)";
        }
        $query->joinSubSelect(
            'LEFT',
            $sql,
            'activity',
            "activity.tawasulPersonID=tawasulPerson.tawasulPersonID AND (activity.tawasulDaysOfWeekID-1) = WEEKDAY(:date) 
                AND activity.timeStart < :timeEnd AND activity.timeEnd > :timeStart AND :date BETWEEN activity.dateStart AND activity.dateEnd"
        );
        

        $query->where("tawasulPerson.status='Full'")
              ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:date)')
              ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:date)')
              ->bindValue('date', $date);

        if ($criteria->hasFilter('allStaff', 'Y')) {
            $query->where("tawasulRole.category='Staff' AND tawasulStaff.type='Teaching'");
        } else {
            $query->where("tawasulSubstitute.active='Y'");
        }

        $showUnavailable = $criteria->hasFilter('showUnavailable', true);

        if ($showUnavailable) {
            $query->groupBy(['tawasulPerson.tawasulPersonID']);
            $query->orderBy(['available DESC', 'priority DESC']);
        }

        $criteria->addFilterRules([
            'substituteTypes' => function ($query, $substituteTypes) {
                if (!empty($substituteTypes)) {
                    $query->where('FIND_IN_SET(tawasulSubstitute.type, :substituteTypes)')
                          ->bindValue('substituteTypes', $substituteTypes);
                }

                return $query;
            },
        ]);
        
        $dataSet = $this->runQuery($query, $criteria);

        // Get any Off Timetable special days for this date
        $sql = "SELECT * FROM tawasulSchoolYearSpecialDay WHERE date =:date AND type='Off Timetable'";
        $specialDay = $this->db()->select($sql, ['date' => $date])->fetch();

        // Update the results to release teachers from off-timetable classes
        $dataSet->transform(function (&$item) use (&$specialDay) {
            $item['available'] = empty($item['absence']) && empty($item['coverage']) && empty($item['timetable']) && empty($item['duty']) && empty($item['activity']) && empty($item['unavailable']);

            if (!empty($specialDay) && !empty($item['courseClassID'])) {
                if ($this->getIsClassOffTimetableByDate($item['courseClassID'], $specialDay['date'])) {
                    $item['available'] = empty($item['absence']) && empty($item['coverage']) && empty($item['duty']) && empty($item['activity']) && empty($item['unavailable']);
                    $item['courseClassID'] = '';
                    $item['timetable'] = '';
                }
            }
        });

        // Filter the results based on updated availability
        $dataSet->filter(function ($item) use (&$showUnavailable) {
            return $item['available'] || $showUnavailable;
        });

        return $dataSet;
    }

    public function selectUnavailableDatesBySub($tawasulPersonID, $dateStart, $dateEnd, $tawasulStaffCoverageIDExclude = '')
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'tawasulStaffCoverageIDExclude' => $tawasulStaffCoverageIDExclude];
        $sql = "(
                SELECT date as groupBy, date, 'Not Available' as status, allDay, timeStart, timeEnd, tawasulStaffCoverageDate.tawasulStaffCoverageDateID as contextID
                FROM tawasulStaffCoverageDate 
                WHERE tawasulStaffCoverageDate.tawasulPersonIDUnavailable=:tawasulPersonID 
                AND tawasulStaffCoverageDate.date BETWEEN :dateStart AND :dateEnd
                ORDER BY DATE
            ) UNION ALL (
                SELECT date as groupBy, date, 'Covering' as status, allDay, timeStart, timeEnd, tawasulStaffCoverage.tawasulStaffCoverageID as contextID
                FROM tawasulStaffCoverage
                JOIN tawasulStaffCoverageDate ON (tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID)
                WHERE tawasulStaffCoverage.tawasulPersonIDCoverage=:tawasulPersonID 
                AND (tawasulStaffCoverage.status='Accepted')
                AND tawasulStaffCoverage.tawasulStaffCoverageID <> :tawasulStaffCoverageIDExclude
                AND tawasulStaffCoverageDate.date BETWEEN :dateStart AND :dateEnd
            ) UNION ALL (
                SELECT date as groupBy, date, 'Absent' as status, allDay, timeStart, timeEnd, tawasulStaffAbsence.tawasulStaffAbsenceID as contextID
                FROM tawasulStaffAbsence
                JOIN tawasulStaffAbsenceDate ON (tawasulStaffAbsenceDate.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID)
                WHERE tawasulStaffAbsence.tawasulPersonID=:tawasulPersonID 
                AND tawasulStaffAbsence.status <> 'Declined'
                AND tawasulStaffAbsenceDate.date BETWEEN :dateStart AND :dateEnd
            ) UNION ALL (
                SELECT date as groupBy, date, 'Staff Duty' as status, 'N' as allDay, timeStart, timeEnd, tawasulStaffDuty.tawasulStaffDutyID as contextID
                FROM tawasulStaffDutyPerson
                JOIN tawasulStaffDuty ON (tawasulStaffDutyPerson.tawasulStaffDutyID=tawasulStaffDuty.tawasulStaffDutyID)
                JOIN tawasulDaysOfWeek ON (tawasulStaffDutyPerson.tawasulDaysOfWeekID=tawasulDaysOfWeek.tawasulDaysOfWeekID)
                JOIN tawasulTTDayDate ON ( (tawasulDaysOfWeek.tawasulDaysOfWeekID-1) = WEEKDAY(tawasulTTDayDate.date) )
                WHERE tawasulStaffDutyPerson.tawasulPersonID=:tawasulPersonID 
                AND tawasulTTDayDate.date BETWEEN :dateStart AND :dateEnd
                GROUP BY tawasulTTDayDate.date
            ) UNION ALL (
                SELECT date as groupBy, date, 'Teaching' as status, 'N', timeStart, timeEnd, tawasulCourseClassPerson.tawasulCourseClassID as contextID
                FROM tawasulCourseClassPerson 
                JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
                JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
                JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID)
                JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID 
                    AND tawasulTTDay.tawasulTTColumnID=tawasulTTColumnRow.tawasulTTColumnID)
                LEFT JOIN tawasulTTDayRowClassException ON (tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTDayRowClassException.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID)
                WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID 
                AND (tawasulCourseClassPerson.role = 'Teacher' OR tawasulCourseClassPerson.role = 'Assistant')
                AND tawasulTTDayRowClassExceptionID IS NULL
                AND tawasulTTDayDate.date BETWEEN :dateStart AND :dateEnd
            )";

        return $this->db()->select($sql, $data);
    }

    public function selectUnavailableDatesByDateRange($dateStart, $dateEnd)
    {
        $data = ['dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "(
                SELECT tawasulStaffCoverageDate.tawasulPersonIDUnavailable as tawasulPersonID, tawasulStaffCoverageDate.date, 'Not Available' as status, allDay, timeStart, timeEnd, TIMESTAMPDIFF(MINUTE, timeStart, timeEnd) as mins, tawasulSubstitute.type, tawasulSubstitute.priority, tawasulStaffCoverageDate.tawasulStaffCoverageDateID as contextID, tawasulStaffCoverageDate.reason
                FROM tawasulStaffCoverageDate 
                LEFT JOIN tawasulSubstitute ON (tawasulSubstitute.tawasulPersonID=tawasulStaffCoverageDate.tawasulPersonIDUnavailable AND tawasulSubstitute.active='Y')
                WHERE tawasulStaffCoverageDate.date BETWEEN :dateStart AND :dateEnd
            ) UNION ALL (
                SELECT tawasulStaffCoverage.tawasulPersonIDCoverage as tawasulPersonID, tawasulStaffCoverageDate.date, 'Covering' as status, allDay, timeStart, timeEnd, TIMESTAMPDIFF(MINUTE, timeStart, timeEnd) as mins, tawasulSubstitute.type, tawasulSubstitute.priority, tawasulStaffCoverage.tawasulStaffCoverageID as contextID, tawasulStaffCoverageDate.reason
                FROM tawasulStaffCoverage
                JOIN tawasulStaffCoverageDate ON (tawasulStaffCoverageDate.tawasulStaffCoverageID=tawasulStaffCoverage.tawasulStaffCoverageID)
                LEFT JOIN tawasulSubstitute ON (tawasulSubstitute.tawasulPersonID=tawasulStaffCoverage.tawasulPersonIDCoverage AND tawasulSubstitute.active='Y')
                WHERE tawasulStaffCoverageDate.date BETWEEN :dateStart AND :dateEnd
                AND (tawasulStaffCoverage.status='Accepted')
            ) UNION ALL (
                SELECT tawasulStaffAbsence.tawasulPersonID as tawasulPersonID, tawasulStaffAbsenceDate.date, 'Absent' as status, allDay, timeStart, timeEnd, TIMESTAMPDIFF(MINUTE, timeStart, timeEnd) as mins, tawasulSubstitute.type, tawasulSubstitute.priority, tawasulStaffAbsence.tawasulStaffAbsenceID as contextID, tawasulStaffAbsenceType.name as reason
                FROM tawasulStaffAbsence
                JOIN tawasulStaffAbsenceType ON (tawasulStaffAbsence.tawasulStaffAbsenceTypeID=tawasulStaffAbsenceType.tawasulStaffAbsenceTypeID)
                JOIN tawasulStaffAbsenceDate ON (tawasulStaffAbsenceDate.tawasulStaffAbsenceID=tawasulStaffAbsence.tawasulStaffAbsenceID)
                LEFT JOIN tawasulSubstitute ON (tawasulSubstitute.tawasulPersonID=tawasulStaffAbsence.tawasulPersonID AND tawasulSubstitute.active='Y')
                WHERE tawasulStaffAbsenceDate.date BETWEEN :dateStart AND :dateEnd
                AND tawasulStaffAbsence.status <> 'Declined'
            ) UNION ALL (
                SELECT tawasulStaffDutyPerson.tawasulPersonID, tawasulTTDayDate.date, 'Staff Duty' as status, 'N' as allDay, timeStart, timeEnd, TIMESTAMPDIFF(MINUTE, timeStart, timeEnd) as mins, tawasulSubstitute.type, tawasulSubstitute.priority, tawasulStaffDuty.tawasulStaffDutyID as contextID, tawasulStaffDuty.name as reason
                FROM tawasulStaffDutyPerson
                JOIN tawasulStaffDuty ON (tawasulStaffDutyPerson.tawasulStaffDutyID=tawasulStaffDuty.tawasulStaffDutyID)
                JOIN tawasulDaysOfWeek ON (tawasulStaffDutyPerson.tawasulDaysOfWeekID=tawasulDaysOfWeek.tawasulDaysOfWeekID)
                JOIN tawasulTTDayDate ON ( (tawasulDaysOfWeek.tawasulDaysOfWeekID-1) = WEEKDAY(tawasulTTDayDate.date) )
                LEFT JOIN tawasulSubstitute ON (tawasulSubstitute.tawasulPersonID=tawasulStaffDutyPerson.tawasulPersonID AND tawasulSubstitute.active='Y')
                WHERE tawasulTTDayDate.date BETWEEN :dateStart AND :dateEnd
                GROUP BY tawasulTTDayDate.date
            ) UNION ALL (
                SELECT DISTINCT tawasulCourseClassPerson.tawasulPersonID as tawasulPersonID, tawasulTTDayDate.date, 'Teaching' as status, 'N', timeStart, timeEnd, TIMESTAMPDIFF(MINUTE, timeStart, timeEnd) as mins, tawasulSubstitute.type, tawasulSubstitute.priority, tawasulCourseClassPerson.tawasulCourseClassID as contextID, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as reason
                FROM tawasulCourseClassPerson
                JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                LEFT JOIN tawasulSubstitute ON (tawasulSubstitute.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID AND tawasulSubstitute.active='Y')
                JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
                JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
                JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID)
                JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID 
                    AND tawasulTTDay.tawasulTTColumnID=tawasulTTColumnRow.tawasulTTColumnID)
                LEFT JOIN tawasulTTDayRowClassException ON (tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTDayRowClassException.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID)
                WHERE tawasulTTDayDate.date BETWEEN :dateStart AND :dateEnd 
                AND (tawasulCourseClassPerson.role = 'Teacher' OR tawasulCourseClassPerson.role = 'Assistant')
                AND tawasulTTDayRowClassExceptionID IS NULL
            ) ORDER BY priority DESC, type DESC, date, timeStart, timeEnd";

        return $this->db()->select($sql, $data);
    }

    public function getSubstituteByPerson($tawasulPersonID, $internalCoverage = 'N')
    {
        $data = ['tawasulPersonID' => $tawasulPersonID];
        if ($internalCoverage == 'Y') {
            $sql = "SELECT tawasulSubstitute.*, tawasulPerson.tawasulPersonID FROM tawasulPerson LEFT JOIN tawasulSubstitute ON (tawasulSubstitute.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID";
        } else {
            $sql = "SELECT * FROM tawasulSubstitute WHERE tawasulSubstitute.tawasulPersonID=:tawasulPersonID";
        }

        return $this->db()->selectOne($sql, $data);
    }

    protected function getIsClassOffTimetableByDate($tawasulCourseClassID, $date)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $date];
        $sql = "SELECT COUNT(*) as studentTotal, COUNT(CASE WHEN (tawasulSchoolYearSpecialDayID IS NULL OR NOT FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, tawasulSchoolYearSpecialDay.tawasulYearGroupIDList) ) AND (tawasulSchoolYearSpecialDayID IS NULL OR NOT FIND_IN_SET(tawasulStudentEnrolment.tawasulFormGroupID, tawasulSchoolYearSpecialDay.tawasulFormGroupIDList)) THEN student.tawasulPersonID ELSE NULL END) as studentCount 
            FROM tawasulCourseClassPerson 
            JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
            JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
            JOIN tawasulPerson AS student ON (tawasulCourseClassPerson.tawasulPersonID=student.tawasulPersonID) 
            JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=student.tawasulPersonID) 
            LEFT JOIN tawasulSchoolYearSpecialDay ON (tawasulSchoolYearSpecialDay.date=:date AND tawasulSchoolYearSpecialDay.type='Off Timetable')
            WHERE tawasulStudentEnrolment.tawasulSchoolYearID=tawasulCourse.tawasulSchoolYearID
            AND tawasulCourseClassPerson.role='Student' 
            AND student.status='Full' 
            AND tawasulCourseClassPerson.tawasulCourseClassID=:tawasulCourseClassID 
            AND (student.dateStart IS NULL OR student.dateStart<=:date) 
            AND (student.dateEnd IS NULL OR student.dateEnd>=:date) 
            AND (
                (tawasulSchoolYearSpecialDayID IS NULL OR NOT FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, tawasulSchoolYearSpecialDay.tawasulYearGroupIDList) )
                OR (tawasulSchoolYearSpecialDayID IS NULL OR NOT FIND_IN_SET(tawasulStudentEnrolment.tawasulFormGroupID, tawasulSchoolYearSpecialDay.tawasulFormGroupIDList))
            )";

        $result = $this->db()->selectOne($sql, $data);

        return !empty($result) && ($result['studentTotal'] > 0 && $result['studentCount'] <= 0);

    }
}
