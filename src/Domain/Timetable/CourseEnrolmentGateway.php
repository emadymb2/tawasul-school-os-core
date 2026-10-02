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
 * @version v16
 * @since   v16
 */
class CourseEnrolmentGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulCourseClassPerson';
    private static $primaryKey = 'tawasulCourseClassPersonID';

    private static $searchableColumns = ['tawasulCourse.name', 'tawasulCourse.nameShort'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryCourseEnrolmentByClass(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulCourseClassID, $left = false, $includeExpected = false)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulCourseClassPerson.tawasulCourseClassPersonID', 'tawasulCourseClass.tawasulCourseClassID', 'tawasulCourseClass.tawasulCourseID', 'tawasulPerson.tawasulPersonID', 'tawasulPerson.title', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.status', 'tawasulPerson.dateStart', 'tawasulPerson.dateEnd', 'tawasulPerson.email', 'tawasulPerson.privacy', 'tawasulPerson.image_240', 'tawasulPerson.dob', 'tawasulCourseClassPerson.reportable', 'tawasulCourseClassPerson.role', "(CASE WHEN tawasulCourseClassPerson.role LIKE 'Teacher%' THEN 0 WHEN tawasulCourseClassPerson.role LIKE 'Assistant%' THEN 1 WHEN tawasulCourseClassPerson.role LIKE 'Technician%' THEN 2 WHEN tawasulCourseClassPerson.role LIKE 'Parent%' THEN 3 WHEN tawasulCourseClassPerson.role LIKE 'Student%' THEN 4 ELSE 5 END) as roleSortOrder", "'Student' as roleCategory", 'tawasulCourse.tawasulYearGroupIDList as yearGroup'
            ])
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID')
            ->where('tawasulCourse.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulCourseClassPerson.tawasulCourseClassID = :tawasulCourseClassID')
            ->bindValue('tawasulCourseClassID', $tawasulCourseClassID);

        if ($left) {
            $query->where("tawasulCourseClassPerson.role LIKE '%Left'");
        } else {
            $query->where("tawasulCourseClassPerson.role NOT LIKE '%Left'");
        }

        if ($includeExpected) {
            $query->where("(tawasulPerson.status = 'Full' OR tawasulPerson.status = 'Expected')")
                  ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
                  ->bindValue('today', date('Y-m-d'));
        } else {
            $query->where("tawasulPerson.status = 'Full'")
                  ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)')
                  ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
                  ->bindValue('today', date('Y-m-d'));
        }

        $criteria->addFilterRules([
            'nonStudents' => function ($query, $role) {
                return $query->where("tawasulCourseClassPerson.role NOT LIKE 'Student%'");
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryCourseEnrolmentByPerson(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonID, $left = false)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulCourseClass.tawasulCourseClassID', 'tawasulCourse.name AS courseName', 'tawasulCourse.nameShort AS course', 'tawasulCourseClass.nameShort AS class', 'tawasulCourseClassPerson.reportable', 'tawasulCourseClassPerson.role', "(CASE WHEN tawasulCourseClassPerson.role NOT LIKE 'Student%' THEN 0 ELSE 1 END) as roleSortOrder"
            ])
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID')
            ->where('tawasulCourse.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulCourseClassPerson.tawasulPersonID = :tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID);

        if ($left) {
            $query->where("tawasulCourseClassPerson.role LIKE '%Left'");
        } else {
            $query->where("tawasulCourseClassPerson.role NOT LIKE '%Left'");
        }

        return $this->runQuery($query, $criteria);
    }

    public function selectEnrolableClassesByYearGroup($tawasulSchoolYearID, $tawasulYearGroupID)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulYearGroupID' => $tawasulYearGroupID, 'today' => date('Y-m-d'));
        $sql = "SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.name as courseName, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, enrolmentMin, enrolmentMax,
                    teacher.surname, teacher.preferredName,
                    (SELECT count(*) FROM tawasulCourseClassPerson JOIN tawasulPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                    WHERE tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND (status='Full' OR status='Expected') AND role='Student' AND (dateEnd IS NULL OR dateEnd>=:today))
                    AS studentCount
                FROM tawasulCourse
                JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                LEFT JOIN
                    (SELECT tawasulCourseClassID, title, surname, preferredName FROM tawasulCourseClassPerson
                    JOIN tawasulPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                    WHERE tawasulPerson.status='Full' AND tawasulCourseClassPerson.role = 'Teacher')
                    AS teacher ON (teacher.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID
                AND FIND_IN_SET(:tawasulYearGroupID, tawasulCourse.tawasulYearGroupIDList)
                GROUP BY tawasulCourseClass.tawasulCourseClassID
                ORDER BY course, class";

        return $this->db()->select($sql, $data);
    }

    public function selectEnrolableStudentsByYearGroup($tawasulSchoolYearID, $tawasulYearGroupID)
    {
        $tawasulYearGroupIDList = is_array($tawasulYearGroupID)? implode(',', $tawasulYearGroupID) : $tawasulYearGroupID;
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulYearGroupIDList' => $tawasulYearGroupIDList);
        $sql = "SELECT tawasulPerson.tawasulPersonID, preferredName, surname, username, tawasulFormGroup.name AS formGroupName
                FROM tawasulPerson
                JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                AND (tawasulPerson.status='Full' OR tawasulPerson.status='Expected')
                AND FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, :tawasulYearGroupIDList)
                ORDER BY formGroupName, surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectCourseEnrolmentByFormGroup($tawasulFormGroupID)
    {
        $data = array('tawasulFormGroupID' => $tawasulFormGroupID);
        $sql = "SELECT DISTINCT tawasulPerson.tawasulPersonID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulFormGroup.name as formGroup,
                    (SELECT COUNT(*) FROM tawasulCourseClassPerson
                    JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
                    JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                    WHERE tawasulCourseClassPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID
                    AND tawasulCourse.tawasulSchoolYearID=tawasulFormGroup.tawasulSchoolYearID
                    AND tawasulCourseClassPerson.role = 'Student') AS classCount
                FROM tawasulPerson
                JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                WHERE tawasulFormGroup.tawasulFormGroupID=:tawasulFormGroupID
                AND (tawasulPerson.status='Full' OR tawasulPerson.status='Expected')
                ORDER BY tawasulPerson.surname, tawasulPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectClassTeachersByStudent($tawasulSchoolYearID, $tawasulPersonIDStudent, $tawasulCourseClassID = null)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonIDStudent' => $tawasulPersonIDStudent);
        $sql = "SELECT DISTINCT teacher.tawasulPersonID, teacher.surname, teacher.preferredName, teacher.email
                FROM tawasulCourseClassPerson AS studentClass
                JOIN tawasulCourseClassPerson AS teacherClass ON (studentClass.tawasulCourseClassID=teacherClass.tawasulCourseClassID)
                JOIN tawasulPerson AS teacher ON (teacherClass.tawasulPersonID=teacher.tawasulPersonID)
                JOIN tawasulCourseClass ON (studentClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                WHERE teacher.status='Full'
                AND teacherClass.role='Teacher'
                AND studentClass.role='Student'
                AND studentClass.tawasulPersonID=:tawasulPersonIDStudent
                AND tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID ";

        if (!empty($tawasulCourseClassID)) {
            $data['tawasulCourseClassID'] = $tawasulCourseClassID;
            $sql .= " AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ";
        }

        $sql .= " ORDER BY teacher.preferredName, teacher.surname, teacher.email";

        return $this->db()->select($sql, $data);
    }

    public function selectClassStudentEnrolment($tawasulCourseClassID)
    {
        $data =['tawasulCourseClassID' => $tawasulCourseClassID, 'today' => date('Y-m-d')];
        $sql = "SELECT tawasulCourseClassPerson.role, tawasulPerson.tawasulPersonID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulFormGroup.name as formGroup
            FROM tawasulPerson
            INNER JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
            INNER JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
            INNER JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
            INNER JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulCourse.tawasulSchoolYearID)
            INNER JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID)
            WHERE tawasulCourseClassPerson.tawasulCourseClassID=:tawasulCourseClassID
            AND tawasulPerson.status='Full'
            AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:today)
            AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today)
            AND tawasulCourseClassPerson.role='Student'
            GROUP BY tawasulPerson.tawasulPersonID
            ORDER BY tawasulPerson.surname, tawasulPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectClassParticipantsByDate($tawasulCourseClassID, $date, $timeStart, $timeEnd)
    {
        $data =['tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $date, 'timeStart' => $timeStart, 'timeEnd' => $timeEnd, 'today' => date('Y-m-d')];
        $sql = "SELECT tawasulCourseClassPerson.*, tawasulPerson.*
            FROM tawasulCourseClassPerson
            INNER JOIN tawasulPerson ON tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID
            LEFT JOIN (
                SELECT tawasulTTDayRowClass.tawasulCourseClassID, tawasulTTDayRowClass.tawasulTTDayRowClassID
                FROM tawasulTTDayDate
                JOIN tawasulTTDayRowClass ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
                JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID)
                WHERE tawasulTTDayDate.date=:date AND tawasulTTColumnRow.timeStart>=:timeStart AND tawasulTTColumnRow.timeEnd<=:timeEnd) AS tawasulTTDayRowClassSubset ON (tawasulTTDayRowClassSubset.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
            LEFT JOIN tawasulTTDayRowClassException ON (tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClassSubset.tawasulTTDayRowClassID AND tawasulTTDayRowClassException.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID)
            WHERE tawasulCourseClassPerson.tawasulCourseClassID=:tawasulCourseClassID
            AND status='Full'
            AND (dateStart IS NULL OR dateStart<=:today)
            AND (dateEnd IS NULL OR dateEnd>=:today)
            AND (NOT role='Student - Left') AND (NOT role='Teacher - Left') AND NOT (role='Teacher' AND reportable='N')
            GROUP BY tawasulCourseClassPerson.tawasulCourseClassPersonID, tawasulPerson.tawasulPersonID
            HAVING COUNT(tawasulTTDayRowClassExceptionID) = 0
            ORDER BY FIELD(role, 'Teacher', 'Assistant', 'Technician', 'Student', 'Parent'), surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectClassesByPersonAndDate($tawasulSchoolYearID, $tawasulPersonID, $date)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID, 'date' => $date];
        $sql = "SELECT DISTINCT tawasulTT.tawasulTTID, tawasulTT.name, tawasulTTDayRowClass.tawasulTTDayRowClassID, tawasulCourseClass.tawasulCourseClassID, tawasulCourseClass.nameShort as classNameShort, tawasulCourseClass.attendance, tawasulTTColumnRow.name as columnName, tawasulTTColumnRow.timeStart, tawasulTTColumnRow.timeEnd, tawasulCourse.name as courseName, tawasulCourse.nameShort as courseNameShort
            FROM tawasulTT
            JOIN tawasulTTDay ON (tawasulTT.tawasulTTID=tawasulTTDay.tawasulTTID)
            JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulTTDayID=tawasulTTDay.tawasulTTDayID)
            JOIN tawasulTTDayDate ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID)
            JOIN tawasulCourseClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
            JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID)
            JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
            JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
            WHERE tawasulPersonID=:tawasulPersonID
            AND tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID
            AND tawasulTT.active='Y'
            AND tawasulTTDayDate.date=:date
            AND tawasulCourseClassPerson.role='Student'
            ORDER BY tawasulTTColumnRow.timeStart ASC";

        return $this->db()->select($sql, $data);
    }

    public function getClassStudentCount($tawasulCourseClassID, $honourStartDate = true)
    {
        $data =['tawasulCourseClassID' => $tawasulCourseClassID, 'today' => date('Y-m-d')];
        $sql = "SELECT COUNT(tawasulCourseClassPerson.tawasulCourseClassPersonID)
            FROM tawasulCourseClassPerson
            INNER JOIN tawasulPerson ON tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID
            WHERE tawasulCourseClassPerson.tawasulCourseClassID=:tawasulCourseClassID
            AND (tawasulPerson.status='Full' OR tawasulPerson.status='Expected')
            AND (tawasulPerson.dateEnd IS NULL  OR tawasulPerson.dateEnd>=:today)
            AND tawasulCourseClassPerson.role='Student'";

            if ($honourStartDate) {
                $sql .= " AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:today)";
            }

        return $this->db()->selectOne($sql, $data);
    }

    public function getEnrolmentDateBySchoolYear($tawasulSchoolYearID) {
        $data = ['tawasulSchoolYearIDEntry' => $tawasulSchoolYearID];
        $sql = "SELECT GREATEST((SELECT firstDay FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearIDEntry), CURRENT_DATE)";

        return $this->db()->selectOne($sql, $data);
    }

    public function unenrolAutomaticCourseEnrolments($tawasulFormGroupID, $tawasulStudentEnrolmentID, $date = null)
    {
        $data = array('tawasulFormGroupIDOriginal' => $tawasulFormGroupID, 'tawasulStudentEnrolmentID' => $tawasulStudentEnrolmentID, 'dateUnenrolled' => $date ?? date('Y-m-d'));
        $sql = "UPDATE tawasulCourseClassPerson
                JOIN tawasulStudentEnrolment ON (tawasulCourseClassPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulCourseClassMap ON (tawasulCourseClassMap.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
                SET role='Student - Left', dateUnenrolled=:dateUnenrolled
                WHERE tawasulStudentEnrolment.tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID
                AND tawasulCourseClassMap.tawasulFormGroupID=:tawasulFormGroupIDOriginal";

        return $this->db()->update($sql, $data);
    }

    public function deleteAutomaticCourseEnrolments($tawasulFormGroupID, $tawasulStudentEnrolmentID)
    {
        $data = array('tawasulFormGroupIDOriginal' => $tawasulFormGroupID, 'tawasulStudentEnrolmentID' => $tawasulStudentEnrolmentID);
        $sql = "DELETE tawasulCourseClassPerson
                FROM tawasulCourseClassPerson
                JOIN tawasulStudentEnrolment ON (tawasulCourseClassPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulCourseClassMap ON (tawasulCourseClassMap.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
                WHERE tawasulStudentEnrolment.tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID
                AND tawasulCourseClassMap.tawasulFormGroupID=:tawasulFormGroupIDOriginal";

        return $this->db()->update($sql, $data);
    }

    public function updateAutomaticCourseEnrolments($tawasulFormGroupID, $tawasulStudentEnrolmentID, $date = null)
    {
        $data = array('tawasulFormGroupID' => $tawasulFormGroupID, 'tawasulStudentEnrolmentID' => $tawasulStudentEnrolmentID, 'dateEnrolled' => $date ?? date('Y-m-d'));
        $sql = "UPDATE tawasulCourseClassPerson
                JOIN tawasulStudentEnrolment ON (tawasulCourseClassPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulCourseClassMap ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClassMap.tawasulCourseClassID
                    AND tawasulCourseClassMap.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID)
                SET tawasulCourseClassPerson.role='Student', tawasulCourseClassPerson.dateEnrolled=:dateEnrolled, tawasulCourseClassPerson.dateUnenrolled=NULL, reportable='Y'
                WHERE tawasulStudentEnrolment.tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID
                AND tawasulStudentEnrolment.tawasulFormGroupID=:tawasulFormGroupID
                AND tawasulCourseClassPerson.tawasulCourseClassPersonID IS NOT NULL";

        return $this->db()->update($sql, $data);
    }

    public function insertAutomaticCourseEnrolments($tawasulFormGroupID, $tawasulPersonID, $date = null)
    {
        $data = array('tawasulFormGroupID' => $tawasulFormGroupID, 'tawasulPersonID' => $tawasulPersonID, 'dateEnrolled' => $date ?? date('Y-m-d'));
        $sql = "INSERT INTO tawasulCourseClassPerson (`tawasulCourseClassID`, `tawasulPersonID`, `role`, `dateEnrolled`, `reportable`)
                SELECT tawasulCourseClassMap.tawasulCourseClassID, :tawasulPersonID, 'Student', :dateEnrolled, 'Y'
                FROM tawasulCourseClassMap
                LEFT JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClassMap.tawasulCourseClassID AND tawasulCourseClassPerson.role='Student')
                WHERE tawasulCourseClassMap.tawasulFormGroupID=:tawasulFormGroupID
                AND tawasulCourseClassPerson.tawasulCourseClassPersonID IS NULL";

        return $this->db()->insert($sql, $data);
    }
}
