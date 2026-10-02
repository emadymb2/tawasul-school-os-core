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

namespace TawasulOS\Domain\Attendance;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v18
 * @since   v18
 */
class AttendanceLogPersonGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulAttendanceLogPerson';
    private static $primaryKey = 'tawasulAttendanceLogPersonID';

    private static $searchableColumns = [''];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryByPersonAndDate(QueryCriteria $criteria, $tawasulPersonID, $date)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulAttendanceLogPersonID', 'tawasulAttendanceLogPerson.direction', 'tawasulAttendanceLogPerson.type', 'tawasulAttendanceLogPerson.reason', 'tawasulAttendanceLogPerson.context', 'tawasulAttendanceLogPerson.comment', 'tawasulAttendanceLogPerson.timestampTaken', 'tawasulAttendanceLogPerson.tawasulCourseClassID', 'takenBy.title', 'takenBy.preferredName', 'takenBy.surname', 'tawasulCourseClass.nameShort as className', 'tawasulCourse.nameShort as courseName', 'tawasulAttendanceCode.scope'
            ])
            ->innerJoin('tawasulPerson as takenBy', 'tawasulAttendanceLogPerson.tawasulPersonIDTaker=takenBy.tawasulPersonID')
            ->leftJoin('tawasulAttendanceCode', 'tawasulAttendanceCode.tawasulAttendanceCodeID=tawasulAttendanceLogPerson.tawasulAttendanceCodeID')
            ->leftJoin('tawasulCourseClass', 'tawasulAttendanceLogPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->leftJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->where('tawasulAttendanceLogPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulAttendanceLogPerson.date=:date')
            ->bindValue('date', $date);

        $criteria->addFilterRules([
            'notClass' => function ($query, $context) {
                return $query->where('NOT tawasulAttendanceLogPerson.context="Class"');
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryClassAttendanceByPersonAndDate(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonID, $date)
    {
        $subSelect = $this
            ->newSelect()
            ->from('tawasulTTDayRowClass')
            ->cols(['tawasulTTColumnRow.name as period', 'tawasulTTColumnRow.timeStart', 'tawasulTTColumnRow.timeEnd', 'tawasulTTDayDate.date', 'tawasulTTDayRowClass.tawasulCourseClassID', 'tawasulTTDayRowClass.tawasulTTDayRowClassID'])
            ->innerJoin('tawasulTTColumnRow', 'tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID')
            ->innerJoin('tawasulTTDay', 'tawasulTTDay.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID AND tawasulTTDay.tawasulTTColumnID=tawasulTTColumnRow.tawasulTTColumnID')
            ->innerJoin('tawasulTTDayDate', 'tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID')
            ->where('tawasulTTDayDate.date=:date')
            ->bindValue('date', $date);

        $query = $this
            ->newQuery()
            ->from('tawasulCourseClassPerson')
            ->cols([
                'tawasulAttendanceLogPersonID', 'tawasulAttendanceLogPerson.direction', 'tawasulAttendanceLogPerson.type', 'tawasulAttendanceLogPerson.reason',  "'Class' as context", 'tawasulAttendanceLogPerson.comment', 'tawasulAttendanceLogPerson.timestampTaken', 'takenBy.title', 'takenBy.preferredName', 'takenBy.surname',
                'tawasulCourseClass.tawasulCourseClassID', 'tawasulCourseClass.nameShort as className', 'tawasulCourse.nameShort as courseName',
                'timetable.period', '(CASE WHEN timetable.timeStart IS NOT NULL THEN timetable.timeStart ELSE tawasulAttendanceLogPerson.timestampTaken END) as timeStart', '(CASE WHEN timetable.timeEnd IS NOT NULL THEN timetable.timeEnd ELSE tawasulAttendanceLogPerson.timestampTaken END) as timeEnd', 'tawasulAttendanceCode.scope'
            ])
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->leftJoin('tawasulAttendanceLogPerson', "tawasulAttendanceLogPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID
                AND tawasulAttendanceLogPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID
                AND tawasulAttendanceLogPerson.date=:date
                AND tawasulAttendanceLogPerson.context = 'Class'")
            ->leftJoin('tawasulAttendanceCode', 'tawasulAttendanceCode.tawasulAttendanceCodeID=tawasulAttendanceLogPerson.tawasulAttendanceCodeID')
            ->leftJoin('tawasulPerson as takenBy', 'tawasulAttendanceLogPerson.tawasulPersonIDTaker=takenBy.tawasulPersonID')
            ->joinSubSelect('LEFT', $subSelect, 'timetable', '(timetable.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND timetable.date=:date AND (tawasulAttendanceLogPerson.tawasulTTDayRowClassID IS NULL OR tawasulAttendanceLogPerson.tawasulTTDayRowClassID=timetable.tawasulTTDayRowClassID))')
            ->where("tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID")
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where("tawasulCourseClassPerson.role = 'Student'")
            ->where("tawasulCourseClass.attendance='Y'")
            ->where('tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('NOT (tawasulAttendanceLogPerson.tawasulAttendanceLogPersonID IS NULL AND timetable.tawasulCourseClassID IS NULL)')
            ->bindValue('date', $date)
            ->groupBy(['tawasulAttendanceLogPerson.tawasulAttendanceLogPersonID', 'timetable.tawasulTTDayRowClassID']);

        return $this->runQuery($query, $criteria);
    }

	/**
     * Select all attendance logs for a person within a school year.
     *
     * @param string $tawasulSchoolYearID
     * @param string $tawasulPersonID
     * @return \TawasulOS\Domain\DataSet|array
     */
    public function selectAllAttendanceLogsByPerson($tawasulSchoolYearID, $tawasulPersonID)
    {
        $query = $this
            ->newSelect()
            ->from('tawasulSchoolYear')
            ->cols([
				'tawasulAttendanceLogPerson.date as groupBy',
				'tawasulAttendanceLogPerson.date',
				'tawasulAttendanceLogPerson.type',
				'tawasulAttendanceLogPerson.reason',
				'tawasulAttendanceLogPerson.timestampTaken',
				'tawasulAttendanceCode.nameShort as code',
				'tawasulAttendanceCode.direction',
				'tawasulAttendanceCode.scope',
				'tawasulAttendanceLogPerson.context',
				"(CASE WHEN tawasulCourse.tawasulCourseID IS NOT NULL THEN CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) END) as contextName",
				'tawasulTTColumnRow.name AS periodName',
				'tawasulAttendanceLogPerson.tawasulTTDayRowClassID',
			])
            ->innerJoin('tawasulAttendanceLogPerson', 'tawasulAttendanceLogPerson.date >= firstDay AND tawasulAttendanceLogPerson.date <= lastDay')
            ->innerJoin('tawasulAttendanceCode', 'tawasulAttendanceLogPerson.type=tawasulAttendanceCode.name')
            ->leftJoin('tawasulCourseClass', "tawasulCourseClass.tawasulCourseClassID=tawasulAttendanceLogPerson.tawasulCourseClassID AND tawasulAttendanceLogPerson.context='Class'")
            ->leftJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
			->leftJoin('tawasulTTDayRowClass', 'tawasulTTDayRowClass.tawasulTTDayRowClassID = tawasulAttendanceLogPerson.tawasulTTDayRowClassID')
			->leftJoin('tawasulTTColumnRow',	'tawasulTTColumnRow.tawasulTTColumnRowID = tawasulTTDayRowClass.tawasulTTColumnRowID')
            ->where('tawasulSchoolYear.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulAttendanceLogPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
			->orderBy([
				'tawasulAttendanceLogPerson.date ASC',
				'tawasulTTColumnRow.timeStart ASC',
				'tawasulAttendanceLogPerson.timestampTaken ASC'
			]);

        return $this->runSelect($query);
    }

    public function queryAttendanceCountsByType($criteria, $tawasulSchoolYearID, $formGroups, $dateStart, $dateEnd, $countClassAsSchool)
    {
        $subSelect = $this
            ->newSelect()
            ->from('tawasulAttendanceLogPerson')
            ->cols(['tawasulPersonID', 'date', 'MAX(timestampTaken) as maxTimestamp', 'context'])
            ->where("date>=:dateStart AND date<=:dateEnd")
            ->groupBy(['tawasulPersonID', 'date']);

        if ($countClassAsSchool == 'N') {
            $subSelect->where("context <> 'Class'");
        }

        $query = $this
            ->newQuery()
            ->from('tawasulAttendanceLogPerson')
            ->cols([
                'tawasulAttendanceCode.name', 'tawasulAttendanceLogPerson.reason', 'count(DISTINCT tawasulAttendanceLogPerson.tawasulPersonID) as count', 'tawasulAttendanceLogPerson.date'
            ])
            ->innerJoin('tawasulAttendanceCode', 'tawasulAttendanceLogPerson.type=tawasulAttendanceCode.name')
            ->joinSubSelect(
                'INNER',
                $subSelect,
                'log',
                'tawasulAttendanceLogPerson.tawasulPersonID=log.tawasulPersonID AND tawasulAttendanceLogPerson.date=log.date'
            )
            ->where('tawasulAttendanceLogPerson.timestampTaken=log.maxTimestamp')
            ->where('tawasulAttendanceLogPerson.date>=:dateStart')
            ->bindValue('dateStart', $dateStart)
            ->where('tawasulAttendanceLogPerson.date<=:dateEnd')
            ->bindValue('dateEnd', $dateEnd)
            ->groupBy(['tawasulAttendanceLogPerson.date', 'tawasulAttendanceCode.name', 'tawasulAttendanceLogPerson.reason'])
            ->orderBy(['tawasulAttendanceLogPerson.date', 'tawasulAttendanceCode.direction DESC', 'tawasulAttendanceCode.name']);

        if ($countClassAsSchool == 'N') {
            $query->where("tawasulAttendanceLogPerson.context <> 'Class'");
        }

        if ($formGroups != array('all')) {
            $query
                ->innerJoin('tawasulStudentEnrolment', 'tawasulAttendanceLogPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
                ->where('FIND_IN_SET(tawasulStudentEnrolment.tawasulFormGroupID, :formGroups)')
                ->bindValue('formGroups', implode(',', $formGroups))
                ->where('tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID')
                ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);
        }

        return $this->runQuery($query, $criteria);
    }

    public function queryStudentsNotPresent(QueryCriteria $criteria, $tawasulSchoolYearID, $date, $allStudents = null, $countClassAsSchool = null)
    {
        $subSelect = $this
            ->newSelect()
            ->from('tawasulAttendanceLogPerson')
            ->cols(['tawasulPersonID', 'date', 'MAX(timestampTaken) as maxTimestamp', 'context', 'MAX(tawasulAttendanceLogPersonID) as tawasulAttendanceLogPersonID'])
            ->where("date=:date")
            ->groupBy(['tawasulPersonID', 'date']);

        if ($countClassAsSchool == 'N') {
            $subSelect->where("context<>'Class'");
        }

        $query = $this
            ->newQuery()
            ->cols([
                'tawasulPerson.tawasulPersonID',
                'tawasulPerson.title',
                'tawasulPerson.preferredName',
                'tawasulPerson.surname',
                'tawasulFormGroup.name as formGroupName',
                'tawasulFormGroup.nameShort as formGroup',
                'tawasulAttendanceLogPerson.type',
                'tawasulAttendanceLogPerson.reason',
                'tawasulAttendanceLogPerson.comment',
            ])
            ->from('tawasulPerson')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID = tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID = tawasulFormGroup.tawasulFormGroupID')
            ->leftJoin('tawasulAttendanceLogPerson', 'tawasulAttendanceLogPerson.tawasulPersonID = tawasulPerson.tawasulPersonID AND tawasulAttendanceLogPerson.date = :date' .( $countClassAsSchool == 'N' ? " AND tawasulAttendanceLogPerson.context<>'Class'" : ""))
            ->joinSubSelect(
                'LEFT',
                $subSelect,
                'log',
                'tawasulAttendanceLogPerson.tawasulPersonID=log.tawasulPersonID AND tawasulAttendanceLogPerson.date=log.date'
            )
            ->where("tawasulPerson.status = 'Full'")
            ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= CURRENT_TIMESTAMP)')
            ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= CURRENT_TIMESTAMP)')
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->bindValue('date', $date);

        if ($allStudents == 'Y') {
            $query->where("(tawasulAttendanceLogPerson.tawasulAttendanceLogPersonID IS NULL OR (tawasulAttendanceLogPerson.direction = 'Out' AND tawasulAttendanceLogPerson.timestampTaken=log.maxTimestamp AND tawasulAttendanceLogPerson.tawasulAttendanceLogPersonID>=log.tawasulAttendanceLogPersonID))");
        } else {
            $query->where("(tawasulAttendanceLogPerson.direction = 'Out' AND tawasulAttendanceLogPerson.timestampTaken=log.maxTimestamp AND tawasulAttendanceLogPerson.tawasulAttendanceLogPersonID>=log.tawasulAttendanceLogPersonID)");
        }

        $criteria->addFilterRules([
            'yearGroup' => function ($query, $tawasulYearGroupIDList) {
                if (empty($tawasulYearGroupIDList)) return $query;
                return $query
                    ->where('FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, :tawasulYearGroupIDList)')
                    ->bindValue('tawasulYearGroupIDList', $tawasulYearGroupIDList);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryStudentsNotOnsite(QueryCriteria $criteria, $tawasulSchoolYearID, $date, $allStudents = null, $countClassAsSchool = null)
    {
        $subSelect = $this
            ->newSelect()
            ->from('tawasulAttendanceLogPerson')
            ->cols(['tawasulPersonID', 'date', 'MAX(timestampTaken) as maxTimestamp', 'context', 'MAX(tawasulAttendanceLogPersonID) as tawasulAttendanceLogPersonID'])
            ->where("date=:date")
            ->groupBy(['tawasulPersonID', 'date']);

        if ($countClassAsSchool == 'N') {
            $subSelect->where("context<>'Class'");
        }

        $query = $this
            ->newQuery()
            ->cols([
                'tawasulPerson.tawasulPersonID',
                'tawasulPerson.title',
                'tawasulPerson.preferredName',
                'tawasulPerson.surname',
                'tawasulFormGroup.name as formGroupName',
                'tawasulFormGroup.nameShort as formGroup',
                'tawasulAttendanceLogPerson.type',
                'tawasulAttendanceLogPerson.reason',
                'tawasulAttendanceLogPerson.comment',
            ])
            ->from('tawasulPerson')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID = tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID = tawasulFormGroup.tawasulFormGroupID')
            ->leftJoin('tawasulAttendanceLogPerson', 'tawasulAttendanceLogPerson.tawasulPersonID = tawasulPerson.tawasulPersonID AND tawasulAttendanceLogPerson.date = :date '.( $countClassAsSchool == 'N' ? " AND tawasulAttendanceLogPerson.context<>'Class'" : "") )
            ->leftJoin('tawasulAttendanceCode', 'tawasulAttendanceCode.tawasulAttendanceCodeID=tawasulAttendanceLogPerson.tawasulAttendanceCodeID')
            ->joinSubSelect(
                'LEFT',
                $subSelect,
                'log',
                'tawasulAttendanceLogPerson.tawasulPersonID=log.tawasulPersonID AND tawasulAttendanceLogPerson.date=log.date'
            )
            ->where("tawasulPerson.status = 'Full'")
            ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= CURRENT_TIMESTAMP)')
            ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= CURRENT_TIMESTAMP)')
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->bindValue('date', $date);

        if ($allStudents == 'Y') {
            $query->where("(tawasulAttendanceLogPerson.tawasulAttendanceLogPersonID IS NULL OR (tawasulAttendanceCode.scope LIKE 'Offsite%' AND tawasulAttendanceLogPerson.timestampTaken=log.maxTimestamp AND tawasulAttendanceLogPerson.tawasulAttendanceLogPersonID>=log.tawasulAttendanceLogPersonID))");
        } else {
            $query->where("(tawasulAttendanceCode.scope LIKE 'Offsite%' AND tawasulAttendanceLogPerson.timestampTaken=log.maxTimestamp AND tawasulAttendanceLogPerson.tawasulAttendanceLogPersonID>=log.tawasulAttendanceLogPersonID)");
        }

        $criteria->addFilterRules([
            'yearGroup' => function ($query, $tawasulYearGroupIDList) {
                if (empty($tawasulYearGroupIDList)) return $query;
                return $query
                    ->where('FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, :tawasulYearGroupIDList)')
                    ->bindValue('tawasulYearGroupIDList', $tawasulYearGroupIDList);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryStudentsNotInClass($criteria, $tawasulSchoolYearID, $date, $allStudents = null)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulAttendanceLogPerson')
            ->cols([
                'tawasulAttendanceLogPersonID', 'tawasulAttendanceLogPerson.type', 'tawasulAttendanceLogPerson.reason', 'tawasulAttendanceLogPerson.comment', 'tawasulAttendanceLogPerson.date','tawasulAttendanceLogPerson.tawasulPersonID', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.username', 'tawasulPerson.status', 'tawasulFormGroup.nameShort as formGroup', 'tawasulYearGroup.nameShort as yearGroup', 'tawasulCourse.nameShort as courseName', 'tawasulCourseClass.nameShort as className'
            ])
            ->innerJoin('tawasulPerson', 'tawasulAttendanceLogPerson.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID')
            ->leftJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseClassID=tawasulAttendanceLogPerson.tawasulCourseClassID')
            ->leftJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->where("tawasulAttendanceLogPerson.context='Class'")
            ->where("tawasulAttendanceLogPerson.type<>'Present'")
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulAttendanceLogPerson.date=:date')
            ->bindValue('date', $date)
            ->where("tawasulPerson.status='Full'")
            ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:today)')
            ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today)')
            ->bindValue('today', date('Y-m-d'));

        if ($allStudents != 'Y') {
            $query->cols(["(SELECT type FROM tawasulAttendanceLogPerson as schoolAttendance WHERE schoolAttendance.tawasulPersonID=tawasulPerson.tawasulPersonID AND schoolAttendance.date=tawasulAttendanceLogPerson.date AND schoolAttendance.context<>'Class' ORDER BY schoolAttendance.timestampTaken DESC LIMIT 1) as schoolAttendanceType"])
                  ->having("schoolAttendanceType NOT LIKE '%Absent%'");
        }

        $criteria->addFilterRules([
            'yearGroup' => function ($query, $tawasulYearGroupIDList) {
                return $query->where('FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, :tawasulYearGroupIDList)')
                             ->bindValue('tawasulYearGroupIDList', $tawasulYearGroupIDList);
            },
            'types' => function ($query, $types) {
                return $query->where('FIND_IN_SET(tawasulAttendanceLogPerson.tawasulAttendanceCodeID, :types)')
                             ->bindValue('types', $types);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    function selectClassAttendanceLogsByPersonAndDate($tawasulCourseClassID, $tawasulPersonID, $date)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'date' => $date, 'tawasulCourseClassID' => $tawasulCourseClassID];
        $sql = "SELECT tawasulAttendanceLogPerson.type, reason, comment, direction, context, timestampTaken, tawasulAttendanceLogPerson.tawasulTTDayRowClassID FROM tawasulAttendanceLogPerson
                JOIN tawasulPerson ON (tawasulAttendanceLogPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE tawasulAttendanceLogPerson.tawasulPersonID=:tawasulPersonID
                AND date=:date
                AND context='Class' AND tawasulCourseClassID=:tawasulCourseClassID
                ORDER BY timestampTaken DESC";

        return $this->db()->select($sql, $data);
    }

    function selectNonClassAttendanceLogsByPersonAndDate($tawasulPersonID, $date)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'date' => $date];
        $sql = "SELECT tawasulAttendanceLogPerson.type, reason, comment, direction, context, timestampTaken FROM tawasulAttendanceLogPerson
                WHERE tawasulAttendanceLogPerson.tawasulPersonID=:tawasulPersonID
                AND date=:date
                AND context <> 'Class'
                ORDER BY timestampTaken DESC";

        return $this->db()->select($sql, $data);
    }

    public function selectFutureAttendanceLogsByPersonAndDate($tawasulPersonID, $date)
    {
        $data = array('tawasulPersonID' => $tawasulPersonID, 'date' => $date);
        $sql = "SELECT tawasulAttendanceLogPersonID, date, direction, type, context, reason, comment, timestampTaken, tawasulAttendanceLogPerson.tawasulCourseClassID, preferredName, surname, tawasulCourseClass.nameShort as className, tawasulCourse.nameShort as courseName, tawasulAttendanceLogPerson.tawasulTTDayRowClassID
            FROM tawasulAttendanceLogPerson 
            JOIN tawasulPerson ON (tawasulAttendanceLogPerson.tawasulPersonIDTaker=tawasulPerson.tawasulPersonID) 
            LEFT JOIN tawasulCourseClass ON (tawasulAttendanceLogPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) 
            LEFT JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) 
            WHERE tawasulAttendanceLogPerson.tawasulPersonIDTaker=tawasulPerson.tawasulPersonID 
            AND tawasulAttendanceLogPerson.tawasulPersonID=:tawasulPersonID AND date>=:date 
            ORDER BY date";

        return $this->db()->select($sql, $data);
    }
    public function selectFutureAttendanceLogsByDate($dateStart, $dateEnd)
    {
        $data = ['dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT tawasulAttendanceLogPerson.tawasulAttendanceLogPersonID, tawasulAttendanceLogPerson.tawasulPersonID as groupBy, tawasulAttendanceLogPerson.type, tawasulAttendanceLogPerson.reason, tawasulAttendanceLogPerson.context, tawasulAttendanceLogPerson.date, tawasulAttendanceLogPerson.direction, tawasulAttendanceLogPerson.comment
            FROM tawasulAttendanceLogPerson 
            WHERE tawasulAttendanceLogPerson.date >= :dateStart
            AND tawasulAttendanceLogPerson.date <= :dateEnd
            AND tawasulAttendanceLogPerson.context = 'Future'
            ORDER BY tawasulAttendanceLogPerson.date, tawasulAttendanceLogPerson.tawasulPersonID";

        return $this->db()->select($sql, $data);
    }

    public function selectFutureAttendanceLogsByDateAndTime($dateStart, $dateEnd, $timeStart, $timeEnd)
    {
        $data = ['dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'timeStart' => $timeStart, 'timeEnd' => $timeEnd];
        $sql = "SELECT tawasulAttendanceLogPerson.tawasulAttendanceLogPersonID, tawasulAttendanceLogPerson.tawasulPersonID as groupBy, tawasulAttendanceLogPerson.type, tawasulAttendanceLogPerson.reason, tawasulAttendanceLogPerson.context, tawasulAttendanceLogPerson.date, tawasulAttendanceLogPerson.direction, tawasulAttendanceLogPerson.comment, tawasulTTColumnRow.name
            FROM tawasulAttendanceLogPerson 
            JOIN tawasulCourseClass ON (tawasulAttendanceLogPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
            JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulTTDayRowClassID=tawasulAttendanceLogPerson.tawasulTTDayRowClassID)
            JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID)
            WHERE tawasulAttendanceLogPerson.context = 'Class'
            AND (tawasulAttendanceLogPerson.date >= :dateStart
            AND tawasulAttendanceLogPerson.date <= :dateEnd)
            AND ((tawasulTTColumnRow.timeStart >= :timeStart AND tawasulTTColumnRow.timeStart < :timeEnd) OR (:timeStart >= tawasulTTColumnRow.timeStart AND :timeStart < tawasulTTColumnRow.timeEnd))
            ORDER BY tawasulAttendanceLogPerson.type, tawasulAttendanceLogPerson.date";

        return $this->db()->select($sql, $data);
    }

    function selectAttendanceLogsByPersonAndDate($tawasulPersonID, $date, $crossFillClasses)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'date' => $date];
        $sql = "SELECT tawasulAttendanceLogPerson.tawasulAttendanceLogPersonID, tawasulAttendanceLogPerson.type, reason, comment, tawasulAttendanceLogPerson.direction, context, timestampTaken, tawasulAttendanceCode.prefill, tawasulAttendanceCode.scope, tawasulAttendanceLogPerson.tawasulTTDayRowClassID
                FROM tawasulAttendanceLogPerson
                JOIN tawasulPerson ON (tawasulAttendanceLogPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                JOIN tawasulAttendanceCode ON (tawasulAttendanceCode.tawasulAttendanceCodeID=tawasulAttendanceLogPerson.tawasulAttendanceCodeID)
                WHERE tawasulAttendanceLogPerson.tawasulPersonID=:tawasulPersonID
                AND date=:date";
        if ($crossFillClasses == "N") {
            $sql .= " AND NOT context='Class'";
        }
        $sql .= " ORDER BY timestampTaken DESC";

        return $this->db()->select($sql, $data);
    }

    function selectNonAbsentAttendanceLogsByDate($tawasulPersonIDList, $date)
    {
        $tawasulPersonIDList = is_array($tawasulPersonIDList) ? implode(',', $tawasulPersonIDList) : $tawasulPersonIDList;

        $data = ['tawasulPersonIDList' => $tawasulPersonIDList, 'date' => $date];
        $sql = "SELECT tawasulAttendanceLogPerson.tawasulPersonID, GROUP_CONCAT(DISTINCT type SEPARATOR ',') 
                FROM tawasulAttendanceLogPerson
                JOIN tawasulPerson ON (tawasulAttendanceLogPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE date=:date
                AND tawasulAttendanceLogPerson.context='Class'
                AND tawasulAttendanceLogPerson.type<>'Absent'
                AND FIND_IN_SET(tawasulAttendanceLogPerson.tawasulPersonID, :tawasulPersonIDList)
                GROUP BY tawasulAttendanceLogPerson.tawasulPersonID
                ORDER BY timestampTaken DESC";

        return $this->db()->select($sql, $data);
    }

    public function selectAdHocAttendanceStudents($tawasulSchoolYearID, $target, $targetID, $currentDate)
    {
        switch ($target) {
            case 'Activity':
                $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulActivityID' => $targetID, 'date' => $currentDate];
                $sql = "SELECT tawasulPerson.image_240, tawasulPerson.dob, tawasulPerson.preferredName, tawasulPerson.surname, tawasulPerson.tawasulPersonID, tawasulFormGroup.nameShort AS formGroup
                        FROM tawasulStudentEnrolment 
                        JOIN tawasulPerson ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID)
                        JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                        AND tawasulActivityStudent.tawasulActivityID=:tawasulActivityID
                        AND tawasulActivityStudent.status='Accepted'
                        AND tawasulPerson.status='Full' 
                        AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:date) 
                        AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:date) 
                        ORDER BY tawasulStudentEnrolment.rollOrder, tawasulPerson.surname, tawasulPerson.preferredName";
                    break;
            case 'Messenger':
                $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulGroupID' => $targetID, 'date' => $currentDate];
                $sql = "SELECT tawasulPerson.image_240, tawasulPerson.dob, tawasulPerson.preferredName, tawasulPerson.surname, tawasulPerson.tawasulPersonID, tawasulFormGroup.nameShort AS formGroup 
                        FROM tawasulStudentEnrolment 
                        JOIN tawasulPerson ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID)
                        JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                        AND tawasulGroupPerson.tawasulGroupID=:tawasulGroupID
                        AND tawasulPerson.status='Full' 
                        AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:date) 
                        AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:date) 
                        ORDER BY tawasulStudentEnrolment.rollOrder, tawasulPerson.surname, tawasulPerson.preferredName";
                    break;
            case 'Class':
                $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulCourseClassID' => $targetID];
                $sql = "SELECT tawasulCourseClassPerson.tawasulPersonID, tawasulPerson.image_240, tawasulPerson.dob, tawasulPerson.preferredName, tawasulPerson.surname, tawasulFormGroup.nameShort AS formGroup
                        FROM tawasulCourseClassPerson
                        JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
                        JOIN tawasulPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID)
                        WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                        AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID
                        AND tawasulPerson.status='Full'
                        AND tawasulCourseClassPerson.role='Student'
                        GROUP BY tawasulCourseClassPerson.tawasulPersonID
                        ORDER BY tawasulPerson.surname, tawasulPerson.preferredName";
                    break;
            case 'Select':
                $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonIDList' => implode(',', $targetID), 'date' => $currentDate];
                $sql = "SELECT tawasulPerson.image_240, tawasulPerson.dob, tawasulPerson.preferredName, tawasulPerson.surname, tawasulPerson.tawasulPersonID, tawasulFormGroup.nameShort AS formGroup 
                        FROM tawasulStudentEnrolment 
                        JOIN tawasulPerson ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) 
                        JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID)
                        WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                        AND FIND_IN_SET(tawasulPerson.tawasulPersonID, :tawasulPersonIDList)
                        AND tawasulPerson.status='Full' 
                        AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:date) 
                        AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:date) 
                        ORDER BY tawasulStudentEnrolment.rollOrder, tawasulPerson.surname, tawasulPerson.preferredName";
                break;
        }

        return $this->db()->select($sql, $data);
    }
    
    public function selectAttendanceLogByStudentAndClassID($tawasulPersonID, $date, $tawasulCourseClassID, $tawasulTTDayRowClassID, $crossFillClasses)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'date' => $date . '%', 'tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulTTDayRowClassID' => $tawasulTTDayRowClassID];
        $sql = "SELECT tawasulAttendanceLogPerson.type, tawasulAttendanceLogPerson.reason, tawasulAttendanceLogPerson.comment, tawasulAttendanceLogPerson.direction, tawasulAttendanceLogPerson.context, timestampTaken FROM tawasulAttendanceLogPerson JOIN tawasulAttendanceCode ON (tawasulAttendanceCode.tawasulAttendanceCodeID=tawasulAttendanceLogPerson.tawasulAttendanceCodeID) JOIN tawasulPerson ON (tawasulAttendanceLogPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulAttendanceLogPerson.tawasulPersonID=:tawasulPersonID AND date LIKE :date AND tawasulAttendanceLogPerson.context='Class' AND tawasulCourseClassID=:tawasulCourseClassID";
        
        if ($crossFillClasses == "N") {
            $sql .= " AND (tawasulTTDayRowClassID=:tawasulTTDayRowClassID OR tawasulTTDayRowClassID IS NULL)";
        } else {
            $sql .= " AND (tawasulTTDayRowClassID=:tawasulTTDayRowClassID OR tawasulAttendanceCode.prefill='Y')";
        }
        
        $sql .= " ORDER BY timestampTaken DESC";
        
        return $this->db()->select($sql, $data);
    }

    public function selectConsecutiveAbsencesByDates($datesList, $tawasulSchoolYearID, $threshold)
    {
        $subSelect = $this
            ->newSelect()
            ->from('tawasulAttendanceLogPerson')
            ->cols(['tawasulPersonID', 'date', 'MAX(timestampTaken) as maxTimestamp', 'context', 'MAX(tawasulAttendanceLogPersonID) as tawasulAttendanceLogPersonID'])
            ->where("FIND_IN_SET(date, :datesList)")
            ->where("context<>'Class'")
            ->where("(date >= NOW() - INTERVAL 30 DAY)")
            ->groupBy(['tawasulPersonID', 'date']);

        $query = $this
            ->newSelect()
            ->cols([
                'tawasulPerson.tawasulPersonID',
                'tawasulPerson.title',
                'tawasulPerson.preferredName',
                'tawasulPerson.surname',
                'tawasulFormGroup.tawasulFormGroupID',
                'tawasulYearGroup.tawasulYearGroupID',
                'tawasulFormGroup.nameShort as formGroup',
                'tawasulAttendanceLogPerson.type',
                'tawasulAttendanceLogPerson.reason',
                'tawasulAttendanceLogPerson.comment',
            ])
            ->from('tawasulPerson')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID = tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID = tawasulFormGroup.tawasulFormGroupID')
            ->innerJoin('tawasulAttendanceLogPerson', 'tawasulAttendanceLogPerson.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulAttendanceCode', 'tawasulAttendanceCode.tawasulAttendanceCodeID=tawasulAttendanceLogPerson.tawasulAttendanceCodeID')
            ->joinSubSelect(
                'INNER',
                $subSelect,
                'log',
                'tawasulAttendanceLogPerson.tawasulPersonID=log.tawasulPersonID AND tawasulAttendanceLogPerson.date=log.date'
            )
            ->where("tawasulPerson.status = 'Full'")
            ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= CURRENT_TIMESTAMP)')
            ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= CURRENT_TIMESTAMP)')
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->where('(tawasulAttendanceLogPerson.date >= NOW() - INTERVAL 30 DAY)')
            ->where('FIND_IN_SET(tawasulAttendanceLogPerson.date, :datesList)')
            ->where('tawasulAttendanceLogPerson.context<>"Class"')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->bindValue('threshold', $threshold)
            ->bindValue('datesList', $datesList)
            ->where("tawasulAttendanceCode.direction='Out' ")
            ->where("tawasulAttendanceCode.type='Absent' ")
            ->where("tawasulAttendanceLogPerson.timestampTaken=log.maxTimestamp ")
            ->where("tawasulAttendanceLogPerson.tawasulAttendanceLogPersonID>=log.tawasulAttendanceLogPersonID")
            ->having(['COUNT(DISTINCT tawasulAttendanceLogPerson.date) >= :threshold)']);
        

        return $this->runSelect($query);
    }
}
