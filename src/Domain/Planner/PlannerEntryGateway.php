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

namespace TawasulOS\Domain\Planner;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * Planner Entry Gateway
 *
 * @version v17
 * @since   v17
 */
class PlannerEntryGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulPlannerEntry';
    private static $primaryKey = 'tawasulPlannerEntryID';
    private static $searchableColumns = [];
    

    public function queryPlannerByClass($criteria, $tawasulSchoolYearID, $tawasulPersonID, $tawasulCourseClassID, $viewingAs = 'Student')
    {
        $cols = ['tawasulPlannerEntry.tawasulPlannerEntryID', 'tawasulPlannerEntry.tawasulUnitID', 'tawasulUnit.name as unit', 'tawasulCourse.tawasulCourseID', 'tawasulPlannerEntry.tawasulCourseClassID', 'tawasulCourse.nameShort AS course', 'tawasulCourseClass.nameShort AS class', 'tawasulPlannerEntry.name as lesson', 'timeStart', 'timeEnd', 'viewableStudents', 'viewableParents', 'homework', 'homeworkSubmission', 'homeworkCrowdAssess', 'date'];

        $query = $this
            ->newQuery()
            ->cols(array_merge($cols, ['GROUP_CONCAT(DISTINCT teacher.tawasulPersonID) AS teacherIDs']))
            ->from('tawasulPlannerEntry')
            ->innerJoin('tawasulCourseClass', 'tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->leftJoin('tawasulUnit', 'tawasulUnit.tawasulUnitID=tawasulPlannerEntry.tawasulUnitID')
            ->leftJoin('tawasulCourseClassPerson as teacher', 'tawasulPlannerEntry.tawasulCourseClassID=teacher.tawasulCourseClassID AND (teacher.role = "Teacher" OR teacher.role="Assistant")')
            ->where('tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID')
            ->bindValue('tawasulCourseClassID', $tawasulCourseClassID)
            ->groupBy(['tawasulPlannerEntry.tawasulPlannerEntryID']);

        if (!empty($tawasulPersonID)) {
            $query->cols(['tawasulCourseClassPerson.role', 'tawasulPlannerEntryStudentHomework.homeworkDueDateTime AS myHomeworkDueDateTime'])
                ->innerJoin('tawasulCourseClassPerson', 'tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID')
                ->where('tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID')
                ->leftJoin('tawasulPlannerEntryStudentHomework', 'tawasulPlannerEntryStudentHomework.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID AND tawasulPlannerEntryStudentHomework.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID')
                ->bindValue('tawasulPersonID', $tawasulPersonID)
                ->where('tawasulCourseClassPerson.role NOT LIKE "%Left"')
                ->where('(tawasulPlannerEntry.timeStart != "" AND tawasulPlannerEntry.timeStart IS NOT NULL)');

                if ($viewingAs == 'Parent') {
                    $query->where('viewableParents = "Y"');
                } elseif ($viewingAs == 'Student') {
                    $query->where('(tawasulCourseClassPerson.role = "Student" AND viewableStudents = "Y")');
                }

            $this->unionAllWithCriteria($query, $criteria)
                ->cols(array_merge($cols, ['tawasulPlannerEntryGuest.role', 'NULL AS myHomeworkDueDateTime', 'NULL as teacherIDs']))
                ->from('tawasulPlannerEntry')
                ->innerJoin('tawasulCourseClass', 'tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
                ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
                ->innerJoin('tawasulPlannerEntryGuest', 'tawasulPlannerEntryGuest.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID')
                ->leftJoin('tawasulUnit', 'tawasulUnit.tawasulUnitID=tawasulPlannerEntry.tawasulUnitID')
                ->where('tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID')
                ->bindValue('tawasulCourseClassID', $tawasulCourseClassID)
                ->where('tawasulPlannerEntryGuest.tawasulPersonID=:tawasulPersonID')
                ->bindValue('tawasulPersonID', $tawasulPersonID);
        } else {
            $query->cols(['NULL as role']);
        }

        return $this->runQuery($query, $criteria);
    }

    public function queryPlannerByDate($criteria, $tawasulSchoolYearID, $tawasulPersonID, $date, $viewingAs = 'Student')
    {
        $cols = ['tawasulPlannerEntry.tawasulPlannerEntryID', 'tawasulPlannerEntry.summary', 'tawasulPlannerEntry.tawasulUnitID', 'tawasulUnit.name as unit', 'tawasulCourse.tawasulCourseID', 'tawasulPlannerEntry.tawasulCourseClassID', 'tawasulCourse.nameShort AS course', 'tawasulCourseClass.nameShort AS class', 'tawasulPlannerEntry.name as lesson', 'tawasulPlannerEntry.timeStart', 'tawasulPlannerEntry.timeEnd', 'viewableStudents', 'viewableParents', 'homework', 'homeworkSubmission', 'homeworkCrowdAssess', 'tawasulPlannerEntry.date'];

        $query = $this
            ->newQuery()
            ->cols(array_merge($cols, ['GROUP_CONCAT(DISTINCT teacher.tawasulPersonID) AS teacherIDs']))
            ->from('tawasulPlannerEntry')
            ->innerJoin('tawasulCourseClass', 'tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->leftJoin('tawasulUnit', 'tawasulUnit.tawasulUnitID=tawasulPlannerEntry.tawasulUnitID')
            ->leftJoin('tawasulCourseClassPerson as teacher', 'tawasulPlannerEntry.tawasulCourseClassID=teacher.tawasulCourseClassID AND (teacher.role = "Teacher" OR teacher.role="Assistant")')
            ->where('tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulPlannerEntry.date=:date')
            ->bindValue('date', $date)
            ->groupBy(['tawasulPlannerEntry.tawasulPlannerEntryID']);

        if (!empty($tawasulPersonID)) {
            $query->cols(['tawasulCourseClassPerson.role', 'tawasulPlannerEntryStudentHomework.homeworkDueDateTime AS myHomeworkDueDateTime', 'tawasulTTDayRowClass.tawasulTTDayRowClassID'])
                ->innerJoin('tawasulCourseClassPerson', 'tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID')
                ->leftJoin('tawasulPlannerEntryStudentHomework', 'tawasulPlannerEntryStudentHomework.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID AND tawasulPlannerEntryStudentHomework.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID')

                ->leftJoin('tawasulTTDayDate', 'tawasulTTDayDate.date=tawasulPlannerEntry.date')
                ->leftJoin('tawasulTTDay', 'tawasulTTDay.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID')
                ->leftJoin('tawasulTTColumnRow', 'tawasulTTColumnRow.tawasulTTColumnID=tawasulTTDay.tawasulTTColumnID AND tawasulTTColumnRow.timeStart=tawasulPlannerEntry.timeStart AND tawasulTTColumnRow.timeEnd=tawasulPlannerEntry.timeEnd')
                ->leftJoin('tawasulTTDayRowClass', 'tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulTTDayRowClass.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID AND tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID')
                ->leftJoin('tawasulTTDayRowClassException', 'tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTDayRowClassException.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID')

                ->where('tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID')
                ->bindValue('tawasulPersonID', $tawasulPersonID)
                ->where('tawasulCourseClassPerson.role NOT LIKE "%Left"')
                ->where('tawasulTTDayRowClassException.tawasulTTDayRowClassExceptionID IS NULL');

            if ($viewingAs == 'Parent') {
                $query->where('viewableParents = "Y"');
            } elseif ($viewingAs == 'Student') {
                $query->where('(tawasulCourseClassPerson.role = "Student" AND viewableStudents = "Y")');
            } elseif ($viewingAs == 'Teacher') {
                $query->where('(tawasulCourseClassPerson.role = "Teacher")');
            }

            $this->unionAllWithCriteria($query, $criteria)
                ->cols(array_merge($cols, ['tawasulPlannerEntryGuest.role', 'NULL AS myHomeworkDueDateTime', 'NULL as teacherIDs', 'NULL as tawasulTTDayRowClassID']))
                ->from('tawasulPlannerEntry')
                ->innerJoin('tawasulCourseClass', 'tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
                ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
                ->innerJoin('tawasulPlannerEntryGuest', 'tawasulPlannerEntryGuest.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID')
                ->leftJoin('tawasulUnit', 'tawasulUnit.tawasulUnitID=tawasulPlannerEntry.tawasulUnitID')
                ->where('tawasulPlannerEntry.date=:date')
                ->bindValue('date', $date)
                ->where('tawasulPlannerEntryGuest.tawasulPersonID=:tawasulPersonID')
                ->bindValue('tawasulPersonID', $tawasulPersonID);
        } else {
            $query->cols(['NULL as role']);
        }

        return $this->runQuery($query, $criteria);
    }

    public function queryPlannerTimeSlotsByClass($criteria, $tawasulSchoolYearID, $tawasulCourseClassID)
    {
        $query = $this
            ->newQuery()
            ->cols(['tawasulTTColumnRow.timeStart', 'tawasulTTColumnRow.timeEnd', 'tawasulTTDayDate.date', 'tawasulTTColumnRow.name AS period', 'tawasulTTDayRowClass.tawasulTTDayRowClassID', 'tawasulCourse.tawasulCourseID', 'tawasulTTDayRowClass.tawasulCourseClassID', 'tawasulTTDayDate.tawasulTTDayDateID', 'tawasulPlannerEntry.tawasulPlannerEntryID', 'tawasulPlannerEntry.name as lesson', 'tawasulUnit.name as unit', 'tawasulSchoolYearTerm.nameShort as termName', 'tawasulSchoolYearTerm.firstDay', 'tawasulSchoolYearTerm.lastDay', 'tawasulSchoolYearSpecialDay.name as specialDay', "CONCAT(tawasulTTDayRowClass.tawasulTTDayRowClassID, '-', tawasulTTDayDate.tawasulTTDayDateID) as identifier", 'tawasulSpace.name as spaceName', 'GROUP_CONCAT(DISTINCT teacher.tawasulPersonID) AS teacherIDs'])
            ->from('tawasulTTDayRowClass')
            ->innerJoin('tawasulTTColumnRow', 'tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID')
            ->innerJoin('tawasulTTColumn', 'tawasulTTColumnRow.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID')
            ->innerJoin('tawasulTTDayDate', 'tawasulTTDayDate.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID')
            ->innerJoin('tawasulSchoolYearTerm', 'tawasulTTDayDate.date BETWEEN tawasulSchoolYearTerm.firstDay AND tawasulSchoolYearTerm.lastDay')
            ->innerJoin('tawasulCourseClass', 'tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->leftJoin('tawasulCourseClassPerson as teacher', 'tawasulTTDayRowClass.tawasulCourseClassID=teacher.tawasulCourseClassID AND (teacher.role = "Teacher" OR teacher.role="Assistant")')
            ->leftJoin('tawasulSpace', 'tawasulSpace.tawasulSpaceID=tawasulTTDayRowClass.tawasulSpaceID')
            ->leftJoin('tawasulSchoolYearSpecialDay', "tawasulSchoolYearSpecialDay.date=tawasulTTDayDate.date and tawasulSchoolYearSpecialDay.type='School Closure'")
            ->leftJoin('tawasulPlannerEntry', 'tawasulPlannerEntry.date=tawasulTTDayDate.date 
                AND tawasulPlannerEntry.timeStart=tawasulTTColumnRow.timeStart 
                AND tawasulPlannerEntry.timeEnd=tawasulTTColumnRow.timeEnd 
                AND tawasulPlannerEntry.tawasulCourseClassID=tawasulTTDayRowClass.tawasulCourseClassID')
            ->leftJoin('tawasulUnit', 'tawasulPlannerEntry.tawasulUnitID=tawasulUnit.tawasulUnitID')
            ->where('tawasulTTDayRowClass.tawasulCourseClassID=:tawasulCourseClassID')
            ->bindValue('tawasulCourseClassID', $tawasulCourseClassID)
            ->where('tawasulSchoolYearTerm.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->groupBy(['tawasulTTDayDate.date', 'tawasulTTColumnRow.name', 'tawasulPlannerEntry.tawasulPlannerEntryID']);

        return $this->runQuery($query, $criteria);
    }

    public function getPlannerTTByIDs($tawasulTTDayRowClassID, $tawasulTTDayDateID)
    {
        $data = ['tawasulTTDayRowClassID' => $tawasulTTDayRowClassID, 'tawasulTTDayDateID' => $tawasulTTDayDateID];
        $sql = "SELECT tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd, tawasulTTColumnRow.name as period, tawasulTTDayDate.date, tawasulTTSpaceChangeID, (CASE WHEN tawasulTTSpaceChangeID IS NOT NULL THEN spaceChange.name ELSE tawasulSpace.name END) as spaceName 
            FROM tawasulTTDayRowClass
            JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID)
            JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
            LEFT JOIN tawasulSpace ON (tawasulSpace.tawasulSpaceID=tawasulTTDayRowClass.tawasulSpaceID)
            LEFT JOIN tawasulTTSpaceChange ON (tawasulTTSpaceChange.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTSpaceChange.date=tawasulTTDayDate.date)
            LEFT JOIN tawasulSpace AS spaceChange ON (spaceChange.tawasulSpaceID=tawasulTTSpaceChange.tawasulSpaceID)
            WHERE tawasulTTDayRowClass.tawasulTTDayRowClassID=:tawasulTTDayRowClassID
            AND tawasulTTDayDate.tawasulTTDayDateID=:tawasulTTDayDateID";
        
        return $this->db()->selectOne($sql, $data);
    }

    public function getPlannerTTByClassTimes($tawasulCourseClassID, $date, $timeStart, $timeEnd)
    {
        $data = ['date' => $date, 'timeStart' => $timeStart, 'timeEnd' => $timeEnd, 'tawasulCourseClassID' => $tawasulCourseClassID];
        $sql = 'SELECT timeStart, timeEnd, tawasulTTDayDate.date, tawasulTTColumnRow.name AS period, tawasulTTDayRowClass.tawasulTTDayRowClassID, tawasulTTDayDateID, tawasulTTSpaceChangeID, (CASE WHEN tawasulTTSpaceChangeID IS NOT NULL THEN spaceChange.name ELSE tawasulSpace.name END) as spaceName 
                FROM tawasulTTDayRowClass 
                JOIN tawasulTTColumnRow ON (tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID) 
                JOIN tawasulTTColumn ON (tawasulTTColumnRow.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID) 
                JOIN tawasulTTDay ON (tawasulTTDayRowClass.tawasulTTDayID=tawasulTTDay.tawasulTTDayID) 
                JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID) 
                LEFT JOIN tawasulSpace ON (tawasulSpace.tawasulSpaceID=tawasulTTDayRowClass.tawasulSpaceID)
                LEFT JOIN tawasulTTSpaceChange ON (tawasulTTSpaceChange.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTSpaceChange.date=tawasulTTDayDate.date)
                LEFT JOIN tawasulSpace AS spaceChange ON (spaceChange.tawasulSpaceID=tawasulTTSpaceChange.tawasulSpaceID)
                WHERE tawasulTTDayDate.date=:date 
                AND timeStart=:timeStart 
                AND timeEnd=:timeEnd AND 
                tawasulCourseClassID=:tawasulCourseClassID 
                ORDER BY tawasulTTDayDate.date, timeStart';
        
        return $this->db()->selectOne($sql, $data);
    }

    public function queryHomeworkByPerson($criteria, $tawasulSchoolYearID, $tawasulPersonID)
    {
        $criteria->addFilterRules([
            'class' => function ($query, $tawasulCourseClassID) {
                return $query
                    ->where('tawasulCourseClass.tawasulCourseClassID = :tawasulCourseClassID')
                    ->bindValue('tawasulCourseClassID', $tawasulCourseClassID);
            },
            'submission' => function ($query, $homeworkSubmission) {
                return $query
                    ->where('tawasulPlannerEntry.homeworkSubmission = :homeworkSubmission')
                    ->bindValue('homeworkSubmission', $homeworkSubmission);
            },
            'viewableParents' => function ($query, $viewableParents) {
                return $query
                    ->where('tawasulPlannerEntry.viewableParents = :viewableParents')
                    ->bindValue('viewableParents', $viewableParents);
            },
            'viewableStudents' => function ($query, $viewableStudents) {
                return $query
                    ->where('tawasulPlannerEntry.viewableStudents = :viewableStudents')
                    ->bindValue('viewableStudents', $viewableStudents);
            },
            'weekly' => function ($query, $weekly) {
                return $query
                    ->where('tawasulPlannerEntry.date>:lastWeek')
                    ->bindValue('lastWeek', date('Y-m-d', strtotime('-1 week')))
                    ->where('tawasulPlannerEntry.date<=:today')
                    ->bindValue('today', date('Y-m-d'));
            },
        ]);

        $query = $this
            ->newQuery()
            ->cols([
                "'teacherRecorded' AS type",
                'tawasulPlannerEntry.tawasulPlannerEntryID',
                'tawasulPlannerEntry.tawasulUnitID',
                'tawasulPlannerEntry.tawasulCourseClassID',
                'tawasulCourse.nameShort AS course',
                'tawasulCourseClass.nameShort AS class',
                'tawasulPlannerEntry.name',
                'tawasulPlannerEntry.date',
                'tawasulPlannerEntry.timeStart',
                'tawasulPlannerEntry.timeEnd',
                'tawasulPlannerEntry.viewableStudents',
                'tawasulPlannerEntry.viewableParents',
                'tawasulPlannerEntry.homework',
                'tawasulCourseClassPerson.role',
                'tawasulPlannerEntry.homeworkDueDateTime',
                'tawasulPlannerEntry.homeworkDetails',
                'tawasulPlannerEntry.homeworkTimeCap',
                'tawasulPlannerEntry.homeworkLocation',
                'tawasulPlannerEntry.homeworkSubmission',
                'tawasulPlannerEntry.homeworkSubmissionRequired',
                'tawasulPerson.dateStart',
                'tawasulUnit.name as unit',
                ])
            ->from($this->getTableName())
            ->innerJoin('tawasulCourseClass', 'tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->innerJoin('tawasulCourseClassPerson', 'tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID')
            ->leftJoin('tawasulUnit', 'tawasulUnit.tawasulUnitID=tawasulPlannerEntry.tawasulUnitID')
            ->where('tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("tawasulPlannerEntry.homework='Y'")
            ->where('(tawasulCourseClassPerson.dateEnrolled IS NULL OR tawasulCourseClassPerson.dateEnrolled <= tawasulPlannerEntry.date)')
            ->where("(tawasulCourseClassPerson.role NOT LIKE '%Left' OR tawasulCourseClassPerson.dateUnenrolled > tawasulPlannerEntry.homeworkDueDateTime)")
            ->where("(tawasulPlannerEntry.date < :todayDate OR (tawasulPlannerEntry.date=:todayDate AND timeEnd <= :todayTime))")
            ->bindValue('todayDate', date('Y-m-d'))
            ->bindValue('todayTime', date('H:i:s'));
          
        $this->unionAllWithCriteria($query, $criteria)
            ->cols([
                "'studentRecorded' AS type",
                'tawasulPlannerEntry.tawasulPlannerEntryID',
                'tawasulPlannerEntry.tawasulUnitID',
                'tawasulPlannerEntry.tawasulCourseClassID',
                'tawasulCourse.nameShort AS course',
                'tawasulCourseClass.nameShort AS class',
                'tawasulPlannerEntry.name',
                'tawasulPlannerEntry.date',
                'tawasulPlannerEntry.timeStart',
                'tawasulPlannerEntry.timeEnd',
                "'Y' AS viewableStudents",
                "'Y' AS viewableParents",
                "'Y' AS homework",
                'tawasulCourseClassPerson.role',
                'tawasulPlannerEntryStudentHomework.homeworkDueDateTime',
                'tawasulPlannerEntryStudentHomework.homeworkDetails',
                'tawasulPlannerEntry.homeworkTimeCap',
                'tawasulPlannerEntry.homeworkLocation',
                "'N' AS homeworkSubmission",
                "'N' AS homeworkSubmissionRequired",
                'tawasulPerson.dateStart',
                'tawasulUnit.name as unit',
                ])
            ->from($this->getTableName())
            ->innerJoin('tawasulCourseClass', 'tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->innerJoin('tawasulCourseClassPerson', 'tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID')
            ->innerJoin('tawasulPlannerEntryStudentHomework', 'tawasulPlannerEntryStudentHomework.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID 
            AND tawasulPlannerEntryStudentHomework.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID')
            ->leftJoin('tawasulUnit', 'tawasulUnit.tawasulUnitID=tawasulPlannerEntry.tawasulUnitID')
            ->where('tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('(tawasulCourseClassPerson.dateEnrolled IS NULL OR tawasulCourseClassPerson.dateEnrolled <= tawasulPlannerEntry.date)')
            ->where("(tawasulCourseClassPerson.role NOT LIKE '%Left' OR tawasulCourseClassPerson.dateUnenrolled > tawasulPlannerEntry.homeworkDueDateTime)")
            ->where("(tawasulPlannerEntry.date < :todayDate OR (tawasulPlannerEntry.date=:todayDate AND timeEnd <= :todayTime))")
            ->bindValue('todayDate', date('Y-m-d'))
            ->bindValue('todayTime', date('H:i:s'));

        return $this->runQuery($query, $criteria);
    }

    public function getPlannerEntryByID($tawasulPlannerEntryID)
    {
        $data = ['tawasulPlannerEntryID' => $tawasulPlannerEntryID];
        $sql = "SELECT * FROM tawasulPlannerEntry WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectPlannerEntriesByPersonAndDateRange($tawasulPersonID, $dateStart, $dateEnd)
    {
        $data = ['dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT CONCAT(tawasulPlannerEntry.tawasulCourseClassID, tawasulPlannerEntry.date, tawasulPlannerEntry.timeStart, tawasulPlannerEntry.timeEnd) as lessonID, tawasulPlannerEntry.tawasulPlannerEntryID, tawasulPlannerEntry.name, tawasulPlannerEntry.date, tawasulPlannerEntry.timeStart, tawasulPlannerEntry.timeEnd, tawasulCourse.tawasulSchoolYearID, tawasulCourse.tawasulCourseID, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.name as courseName, tawasulCourse.nameShort AS courseNameShort, tawasulCourseClass.nameShort AS classNameShort, tawasulUnit.tawasulUnitID, tawasulUnit.name as unitName, tawasulPlannerEntry.tawasulSpaceID, tawasulSpace.name AS plannerRoomName, tawasulSpace.phoneInternal AS plannerRoomPhone, tawasulTTColumnRow.name as period
        FROM tawasulCourse 
        JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) 
        JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) 
        JOIN tawasulPlannerEntry ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
        LEFT JOIN tawasulUnit ON (tawasulUnit.tawasulUnitID=tawasulPlannerEntry.tawasulUnitID)
        LEFT JOIN tawasulSpace ON tawasulSpace.tawasulSpaceID = tawasulPlannerEntry.tawasulSpaceID
        LEFT JOIN tawasulTTDayDate ON (tawasulTTDayDate.date=tawasulPlannerEntry.date)
        LEFT JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID)
        LEFT JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnID=tawasulTTDay.tawasulTTColumnID AND tawasulTTColumnRow.timeStart=tawasulPlannerEntry.timeStart AND tawasulTTColumnRow.timeEnd=tawasulPlannerEntry.timeEnd)
        LEFT JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulPlannerEntry.tawasulCourseClassID AND tawasulTTDayRowClass.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID AND tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID)
        LEFT JOIN tawasulTTDayRowClassException ON (tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND tawasulTTDayRowClassException.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID)

        WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID
        AND tawasulPlannerEntry.date BETWEEN :dateStart AND :dateEnd
        AND tawasulCourseClassPerson.role NOT LIKE '%- Left'
        AND ((tawasulCourseClassPerson.role = 'Student' AND viewableStudents='Y') OR (tawasulCourseClassPerson.role = 'Parent' AND viewableParents='Y') OR tawasulCourseClassPerson.role = 'Teacher' OR tawasulCourseClassPerson.role = 'Assistant')
        GROUP BY tawasulPlannerEntry.tawasulPlannerEntryID
        HAVING COUNT(tawasulTTDayRowClassException.tawasulTTDayRowClassExceptionID) = 0
        ORDER BY timeStart, timeEnd, FIND_IN_SET(tawasulCourseClassPerson.role, 'Teacher,Assistant,Student') DESC";

        return $this->db()->select($sql, $data);
    }

    public function getPlannerEntryByClassTimes($tawasulCourseClassID, $date, $timeStart, $timeEnd)
    {
        $data = ['date' => $date, 'timeStart' => $timeStart, 'timeEnd' => $timeEnd, 'tawasulCourseClassID' => $tawasulCourseClassID];
        $sql = "SELECT * FROM tawasulPlannerEntry WHERE tawasulCourseClassID=:tawasulCourseClassID AND date=:date AND timeStart=:timeStart AND timeEnd=:timeEnd GROUP BY name";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectPlannerEntriesByUnitAndClass($tawasulUnitID, $tawasulCourseClassID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulUnitID' => $tawasulUnitID];
        $sql = "SELECT * 
            FROM tawasulPlannerEntry 
            WHERE tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID 
            AND tawasulPlannerEntry.tawasulUnitID=:tawasulUnitID 
            AND tawasulPlannerEntry.date IS NOT NULL 
            ORDER BY tawasulPlannerEntry.date, tawasulPlannerEntry.timeStart";

        return $this->db()->select($sql, $data);
    }

    public function selectUpcomingHomeworkByStudent($tawasulSchoolYearID, $tawasulPersonID, $viewableBy = 'viewableStudents')
    {
        $data = [
            'tawasulSchoolYearID' => $tawasulSchoolYearID,
            'tawasulPersonID' => $tawasulPersonID,
            'todayTime' => date('Y-m-d H:i:s'),
            'todayDate' => date('Y-m-d'),
            'time' => date('H:i:s'),
        ];
        // UNION Teacher Online + Teacher Manual + Student Manual
        $sql = "
            (SELECT 'teacherRecorded' AS type, tawasulPlannerEntry.tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulPlannerEntry.name, date, timeStart, timeEnd, viewableStudents, viewableParents, homework, homeworkDueDateTime, role, (CASE WHEN tawasulPlannerEntryHomework.version='Final' THEN 'Y' ELSE 'N' END) AS homeworkComplete, (CASE WHEN tawasulPlannerEntryHomework.tawasulPlannerEntryHomeworkID IS NOT NULL THEN 'Y' ELSE 'N' END) as onlineSubmission
                FROM tawasulPlannerEntry 
                JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) 
                JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) 
                JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) 
                LEFT JOIN tawasulPlannerEntryHomework ON (tawasulPlannerEntryHomework.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID 
                    AND tawasulPlannerEntryHomework.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID)
                WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID 
                AND homework='Y' 
                AND (role='Teacher' OR (role='Student' AND $viewableBy='Y')) 
                AND homeworkDueDateTime>:todayTime 
                AND tawasulPlannerEntry.homeworkSubmission='Y'
                AND ((date<:todayDate) OR (date=:todayDate AND timeEnd<=:time))
                AND (tawasulCourseClassPerson.dateEnrolled IS NULL OR tawasulCourseClassPerson.dateEnrolled <= tawasulPlannerEntry.date)
                AND (tawasulCourseClassPerson.dateUnenrolled IS NULL OR tawasulCourseClassPerson.dateUnenrolled > tawasulPlannerEntry.homeworkDueDateTime)
            )
            UNION
            (SELECT 'teacherRecorded' AS type, tawasulPlannerEntry.tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulPlannerEntry.name, date, timeStart, timeEnd, viewableStudents, viewableParents, homework, homeworkDueDateTime, role, tawasulPlannerEntryStudentTracker.homeworkComplete, 'N' as onlineSubmission
                FROM tawasulPlannerEntry 
                JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) 
                JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) 
                JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) 
                LEFT JOIN tawasulPlannerEntryStudentTracker ON (tawasulPlannerEntryStudentTracker.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID 
                    AND tawasulPlannerEntryStudentTracker.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID)

                WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID 
                AND homework='Y' 
                AND (role='Teacher' OR (role='Student' AND $viewableBy='Y')) 
                AND homeworkDueDateTime>:todayTime 
                AND tawasulPlannerEntry.homeworkSubmission<>'Y'
                AND ((date<:todayDate) OR (date=:todayDate AND timeEnd<=:time))
                AND (tawasulCourseClassPerson.dateEnrolled IS NULL OR tawasulCourseClassPerson.dateEnrolled <= tawasulPlannerEntry.date)
                AND (tawasulCourseClassPerson.dateUnenrolled IS NULL OR tawasulCourseClassPerson.dateUnenrolled > tawasulPlannerEntry.homeworkDueDateTime)
            )
            UNION
            (SELECT 'studentRecorded' AS type, tawasulPlannerEntry.tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulPlannerEntry.name, date, timeStart, timeEnd, 'Y' AS viewableStudents, 'Y' AS viewableParents, 'Y' AS homework, tawasulPlannerEntryStudentHomework.homeworkDueDateTime, tawasulCourseClassPerson.role, tawasulPlannerEntryStudentHomework.homeworkComplete, 'N' as onlineSubmission FROM tawasulPlannerEntry JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) 
                JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) 
                JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) 
                JOIN tawasulPlannerEntryStudentHomework ON (tawasulPlannerEntryStudentHomework.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID 
                AND tawasulPlannerEntryStudentHomework.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID) 
                LEFT JOIN tawasulPlannerEntryHomework ON (tawasulPlannerEntryHomework.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID 
                    AND tawasulPlannerEntryHomework.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID AND tawasulPlannerEntryHomework.version='Final')
                WHERE tawasulSchoolYearID=:tawasulSchoolYearID 
                AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID 
                AND (tawasulCourseClassPerson.role='Teacher' OR (tawasulCourseClassPerson.role='Student' AND $viewableBy='Y')) 
                AND tawasulPlannerEntryStudentHomework.homeworkDueDateTime>:todayTime 
                AND ((date<:todayDate) OR (date=:todayDate AND timeEnd<=:time))
                AND (tawasulCourseClassPerson.dateEnrolled IS NULL OR tawasulCourseClassPerson.dateEnrolled <= tawasulPlannerEntry.date)
                AND (tawasulCourseClassPerson.dateUnenrolled IS NULL OR tawasulCourseClassPerson.dateUnenrolled > tawasulPlannerEntry.homeworkDueDateTime)
            )
            ORDER BY homeworkDueDateTime, type";

        return $this->db()->select($sql, $data);
    }

    public function selectTeacherRecordedHomeworkTrackerByStudent($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "
            SELECT TRIM(LEADING '0' FROM tawasulPlannerEntryStudentTracker.tawasulPlannerEntryID) as groupBy, 'teacherRecorded' AS type, homeworkComplete 
            FROM tawasulPlannerEntryStudentTracker 
            JOIN tawasulPlannerEntry ON (tawasulPlannerEntryStudentTracker.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID) 
            JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) 
            JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) 
            WHERE tawasulSchoolYearID=:tawasulSchoolYearID 
            AND tawasulPersonID=:tawasulPersonID 
            AND homeworkComplete='Y'
            ORDER BY groupBy, type
            ";

        return $this->db()->select($sql, $data);
    }

    public function selectStudentRecordedHomeworkTrackerByStudent($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "
            SELECT TRIM(LEADING '0' FROM tawasulPlannerEntryStudentHomework.tawasulPlannerEntryID) as groupBy,  'studentRecorded' AS type, homeworkComplete
            FROM tawasulPlannerEntryStudentHomework 
            JOIN tawasulPlannerEntry ON (tawasulPlannerEntryStudentHomework.tawasulPlannerEntryID=tawasulPlannerEntry.tawasulPlannerEntryID) 
            JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) 
            JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) 
            WHERE tawasulSchoolYearID=:tawasulSchoolYearID 
            AND tawasulPersonID=:tawasulPersonID 
            AND homeworkComplete='Y'
            ORDER BY groupBy, type
            ";

        return $this->db()->select($sql, $data);
    }

    public function selectHomeworkSubmissionsByStudent($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT TRIM(LEADING '0' FROM tawasulPlannerEntryHomework.tawasulPlannerEntryID) as groupBy, tawasulPlannerEntryHomework.* 
            FROM tawasulPlannerEntryHomework 
            JOIN tawasulPlannerEntry ON (tawasulPlannerEntry.tawasulPlannerEntryID=tawasulPlannerEntryHomework.tawasulPlannerEntryID) 
            JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) 
            JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) 
            WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID
            AND tawasulPlannerEntryHomework.tawasulPersonID=:tawasulPersonID 
            ORDER BY count DESC";

        return $this->db()->select($sql, $data);
    }

    public function selectHomeworkSubmissionCounts($tawasulPlannerEntryID)
    {
        $tawasulPlannerEntryIDList = is_array($tawasulPlannerEntryID)? $tawasulPlannerEntryID : [$tawasulPlannerEntryID];
        $tawasulPlannerEntryIDList = array_map(function($item) {
            return str_pad($item, 14, '0', STR_PAD_LEFT);
        }, $tawasulPlannerEntryIDList);

        $data = ['tawasulPlannerEntryIDList' => implode(',', $tawasulPlannerEntryIDList)];
        $sql = "SELECT TRIM(LEADING '0' FROM tawasulPlannerEntry.tawasulPlannerEntryID) as groupBy,
            COUNT(DISTINCT CASE WHEN tawasulPlannerEntryHomework.version='Final' AND tawasulPlannerEntryHomework.status='On Time' THEN  tawasulPlannerEntryHomework.tawasulPersonID END) as onTime,
            COUNT(DISTINCT CASE WHEN tawasulPlannerEntryHomework.version='Final' AND tawasulPlannerEntryHomework.status='Late' THEN  tawasulPlannerEntryHomework.tawasulPersonID END) as late,
            (SELECT COUNT(*) FROM tawasulCourseClassPerson JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID) WHERE tawasulCourseClassPerson.tawasulCourseClassID=tawasulPlannerEntry.tawasulCourseClassID AND role='Student' AND tawasulPerson.status='Full' AND (tawasulCourseClassPerson.dateEnrolled IS NULL OR tawasulCourseClassPerson.dateEnrolled <= CURRENT_DATE) AND (tawasulCourseClassPerson.dateUnenrolled IS NULL OR tawasulCourseClassPerson.dateUnenrolled > CURRENT_DATE) ) as total
            FROM tawasulPlannerEntry
            LEFT JOIN tawasulPlannerEntryHomework ON (tawasulPlannerEntry.tawasulPlannerEntryID=tawasulPlannerEntryHomework.tawasulPlannerEntryID)
            WHERE FIND_IN_SET(tawasulPlannerEntry.tawasulPlannerEntryID, :tawasulPlannerEntryIDList)
            GROUP BY tawasulPlannerEntry.tawasulPlannerEntryID
            ";

        return $this->db()->select($sql, $data);


    }

    public function selectAllUpcomingHomework($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'homeworkDueDateTime' => date('Y-m-d H:i:s'), 'date1' => date('Y-m-d'), 'date2' => date('Y-m-d'), 'timeEnd' => date('H:i:s')];
        $sql = "SELECT tawasulPlannerEntryID, tawasulUnitID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulPlannerEntry.name, date, timeStart, timeEnd, viewableStudents, viewableParents, homework, homeworkDueDateTime 
            FROM tawasulPlannerEntry 
            JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) 
            JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) 
            WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID AND homework='Y' AND homeworkDueDateTime>:homeworkDueDateTime AND ((date<:date1) OR (date=:date2 AND timeEnd<=:timeEnd)) ORDER BY homeworkDueDateTime";

        return $this->db()->select($sql, $data);
    }

    public function selectPlannerClassesByPerson($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = [
            'tawasulSchoolYearID' => $tawasulSchoolYearID,
            'tawasulPersonID' => $tawasulPersonID,
            'today' => date('Y-m-d'),
        ];
        $sql = "SELECT DISTINCT tawasulCourseClass.tawasulCourseClassID as value, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as name 
            FROM tawasulPlannerEntry 
            JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) 
            JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) 
            JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) 
            WHERE tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID 
            AND tawasulSchoolYearID=:tawasulSchoolYearID  
            AND NOT role='Student - Left' AND NOT role='Teacher - Left' 
            AND homework='Y' AND date<=:today 
            AND (tawasulCourseClassPerson.dateEnrolled IS NULL OR tawasulCourseClassPerson.dateEnrolled <= tawasulPlannerEntry.date)
            AND (tawasulCourseClassPerson.dateUnenrolled IS NULL OR tawasulCourseClassPerson.dateUnenrolled > tawasulPlannerEntry.date)
            ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    public function selectPlannerGuests($tawasulPlannerEntryID)
    {
        $data = ['tawasulPlannerEntryID' => $tawasulPlannerEntryID];
        $sql = "SELECT title, surname, preferredName, image_240, tawasulPlannerEntryGuest.role
                FROM tawasulPlannerEntryGuest 
                JOIN tawasulPerson ON tawasulPlannerEntryGuest.tawasulPersonID=tawasulPerson.tawasulPersonID 
                JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID) 
                WHERE tawasulPlannerEntryID=:tawasulPlannerEntryID 
                AND status='Full' 
                ORDER BY role DESC, surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function getPlannerClassDetails($tawasulCourseClassID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID];
        $sql = "SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class 
                FROM tawasulCourseClass
                JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) 
                WHERE tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getLatestLessonByClass($tawasulCourseClassID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID];
        $sql = "SELECT * FROM tawasulPlannerEntry 
                WHERE tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID
                ORDER BY date DESC 
                LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectUpcomingPlannerTTByDate($tawasulCourseClassID, $date)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $date];
        $sql = "SELECT tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd, tawasulTTDayDate.date
                FROM tawasulTTDayRowClass
                JOIN tawasulTTColumnRow ON (tawasulTTDayRowClass.tawasulTTColumnRowID=tawasulTTColumnRow.tawasulTTColumnRowID)
                JOIN tawasulTTColumn ON (tawasulTTColumnRow.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID)
                JOIN tawasulTTDay ON (tawasulTTDayRowClass.tawasulTTDayID=tawasulTTDay.tawasulTTDayID)
                JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID)
                LEFT JOIN tawasulSchoolYearSpecialDay ON (tawasulSchoolYearSpecialDay.date=tawasulTTDayDate.date 
                    AND tawasulSchoolYearSpecialDay.type='School Closure')
                WHERE tawasulTTDayRowClass.tawasulCourseClassID=:tawasulCourseClassID
                AND tawasulTTDayDate.date>=:date
                AND tawasulSchoolYearSpecialDayID IS NULL
                ORDER BY tawasulTTDayDate.date, tawasulTTColumnRow.timestart
                LIMIT 0, 10";

        return $this->db()->select($sql, $data);
    }

    public function getPreviousLesson($tawasulCourseClassID, $date, $timeStart, $role)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $date, 'timeStart' => $timeStart];
        $sql = "SELECT tawasulPlannerEntry.tawasulPlannerEntryID, tawasulPlannerEntry.name, timeStart, timeEnd, viewableStudents, viewableParents 
            FROM tawasulPlannerEntry
            JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
            JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
            WHERE tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID 
            AND (date<:date OR (date=:date AND timeStart<:timeStart)) ";

        if ($role == 'Student') {
            $sql .= ' AND viewableStudents="Y" ';
        } elseif ($role == 'Parent') {
            $sql .= ' AND viewableParents="Y" ';
        }
        $sql .= " ORDER BY date DESC, timeStart DESC LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }

    public function getNextLesson($tawasulCourseClassID, $date, $timeStart, $role)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $date, 'timeStart' => $timeStart];
        $sql = "SELECT tawasulPlannerEntry.tawasulPlannerEntryID, tawasulPlannerEntry.name, timeStart, timeEnd, viewableStudents, viewableParents 
            FROM tawasulPlannerEntry
            JOIN tawasulCourseClass ON (tawasulPlannerEntry.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
            JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
            WHERE tawasulPlannerEntry.tawasulCourseClassID=:tawasulCourseClassID 
            AND (date>:date OR (date=:date AND timeStart>:timeStart)) ";

        if ($role == 'Student') {
            $sql .= ' AND viewableStudents="Y" ';
        } elseif ($role == 'Parent') {
            $sql .= ' AND viewableParents="Y" ';
        }
        $sql .= " ORDER BY date, timeStart LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectPlannerLessonsByStudent($tawasulSchoolYearID, $tawasulPersonID, $date = null, $minDate = null)
    {
        $minDate = $minDate ?? date('Y-m-d', (time() - (24 * 60 * 60 * 30)));
        $date = $date ?? date('Y-m-d', time());
        $data = ['date1' => $date, 'date2' => $minDate, 'tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulPlannerEntry.name AS lesson, tawasulPlannerEntryID, date, homework, homeworkSubmission FROM tawasulCourse JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) JOIN tawasulPlannerEntry ON (tawasulCourseClass.tawasulCourseClassID=tawasulPlannerEntry.tawasulCourseClassID) WHERE (date<=:date1 AND date>=:date2) AND tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND role='Student' ORDER BY course, class, date DESC, timeStart";
        
        return $this->db()->select($sql, $data);
    }
}
