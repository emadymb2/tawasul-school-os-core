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
 * @since   v25
 */
class CourseClassGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulCourseClass';
    private static $primaryKey = 'tawasulCourseClassID';

    private static $searchableColumns = ['tawasulCourseClass.name', 'tawasulCourseClass.nameShort'];

    public function getCourseClass($tawasulCourseClassID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID];
        $sql = 'SELECT tawasulCourse.nameShort AS course, tawasulCourse.name AS courseName, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulDepartmentID, tawasulYearGroupIDList FROM tawasulCourse, tawasulCourseClass WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID ORDER BY course, class';

        return $this->db()->selectOne($sql, $data);
    }

    public function getCourseClassByPerson($tawasulCourseClassID, $tawasulPersonID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourse.name AS courseName, tawasulCourseClass.nameShort AS class, tawasulYearGroupIDList FROM tawasulCourse JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID AND tawasulPersonID=:tawasulPersonID AND role='Teacher'";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectClassesByYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulYearGroup.name as groupBy, tawasulCourseClassID as value, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS name FROM tawasulCourseClass JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulYearGroup ON (tawasulCourse.tawasulYearGroupIDList LIKE concat( '%', tawasulYearGroup.tawasulYearGroupID, '%' )) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourseClass.reportable='Y' ORDER BY tawasulYearGroup.sequenceNumber, name";

        return $this->db()->select($sql, $data);
    }

    public function selectClassesByPerson($tawasulPersonID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID FROM tawasulCourse, tawasulCourseClass, tawasulCourseClassPerson WHERE tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND tawasulCourse.tawasulSchoolYearID=(SELECT tawasulSchoolYearID FROM tawasulSchoolYear WHERE status='Current') ORDER BY course, class";

        return $this->db()->select($sql, $data);
    }

    public function selectClassesByYearAndPerson($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = 'SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID FROM tawasulCourse, tawasulCourseClass, tawasulCourseClassPerson WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID ORDER BY course, class';

        return $this->db()->select($sql, $data);
    }

    public function selectTeacherListByClass($tawasulCourseClassID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID];
        $sql = "SELECT tawasulPerson.tawasulPersonID, title, surname, preferredName, tawasulCourseClassPerson.reportable FROM tawasulCourseClassPerson JOIN tawasulPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE role='Teacher' AND tawasulCourseClassID=:tawasulCourseClassID ORDER BY surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectStudentListByClass($tawasulCourseClassID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'today' => date('Y-m-d')];
        $sql = "SELECT title, surname, preferredName, tawasulPerson.tawasulPersonID, dateStart FROM tawasulCourseClassPerson JOIN tawasulPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE role='Student' AND tawasulCourseClassID=:tawasulCourseClassID AND status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL  OR dateEnd>=:today) AND tawasulCourseClassPerson.reportable='Y' ORDER BY surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectAttendanceClassesByStudent($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulCourseClass.tawasulCourseClassID, tawasulSchoolYear.firstDay, tawasulSchoolYear.lastDay FROM tawasulCourse JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulPersonID=:tawasulPersonID AND tawasulCourseClass.attendance='Y' AND tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID";

        return $this->db()->select($sql, $data);
    }
    
    public function selectStudentsByClassAndPeriod($tawasulCourseClassID, $date, $tawasulTTDayRowClassID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $date, 'tawasulTTDayRowClassID' => $tawasulTTDayRowClassID];
        $sql = "SELECT tawasulPerson.surname, tawasulPerson.preferredName, tawasulPerson.tawasulPersonID, tawasulPerson.image_240, tawasulPerson.dob FROM tawasulCourseClassPerson INNER JOIN tawasulPerson ON tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID LEFT JOIN (SELECT tawasulTTDayRowClass.tawasulCourseClassID, tawasulTTDayRowClass.tawasulTTDayRowClassID FROM tawasulTTDayDate JOIN tawasulTTDayRowClass ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID) WHERE tawasulTTDayDate.date=:date) AS tawasulTTDayRowClassSubset ON (tawasulTTDayRowClassSubset.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND tawasulTTDayRowClassSubset.tawasulTTDayRowClassID=:tawasulTTDayRowClassID) LEFT JOIN tawasulTTDayRowClassException ON (tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClassSubset.tawasulTTDayRowClassID AND tawasulTTDayRowClassException.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID) WHERE tawasulCourseClassPerson.tawasulCourseClassID=:tawasulCourseClassID AND status='Full' AND role='Student' AND (dateStart IS NULL OR dateStart<=:date) AND (dateEnd IS NULL OR dateEnd>=:date) GROUP BY tawasulCourseClassPerson.tawasulPersonID HAVING COUNT(tawasulTTDayRowClassExceptionID) = 0 ORDER BY surname, preferredName";

        return $this->db()->select($sql, $data);
    }
  
    public function selectClassesByCourseID($tawasulCourseID)
    {
        $data = array('tawasulCourseID' => $tawasulCourseID, 'today' => date('Y-m-d'));
        $sql = "SELECT tawasulCourseClass.*, tawasulCourseClass.nameShort as class, tawasulCourse.nameShort as course, COUNT(CASE WHEN tawasulPerson.status='Full' AND tawasulCourseClassPerson.role='Student' AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<:today) AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today) THEN tawasulPerson.status END) as studentsActive, COUNT(CASE WHEN (tawasulPerson.status='Expected' OR tawasulPerson.dateStart>=:today) AND tawasulCourseClassPerson.role='Student' THEN tawasulPerson.status END) as studentsExpected, COUNT(DISTINCT CASE WHEN (tawasulPerson.status='Full' OR tawasulPerson.status='Expected') AND tawasulCourseClassPerson.role='Student' AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today) THEN tawasulPerson.tawasulPersonID END) as studentsTotal, COUNT(DISTINCT CASE WHEN tawasulCourseClassPerson.role='Teacher' AND (tawasulPerson.status='Full' OR tawasulPerson.status='Expected') THEN tawasulPerson.tawasulPersonID END) as teachersTotal
            FROM tawasulCourseClass
            JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
            LEFT JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND (tawasulCourseClassPerson.role='Student' OR tawasulCourseClassPerson.role='Teacher'))
            LEFT JOIN tawasulPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID AND (tawasulPerson.status='Full' OR tawasulPerson.status='Expected'))
            WHERE tawasulCourseClass.tawasulCourseID=:tawasulCourseID
            GROUP BY tawasulCourseClass.tawasulCourseClassID
            ORDER BY tawasulCourseClass.nameShort";

        return $this->db()->select($sql, $data);
    }
    
    public function getCourseClassByID($tawasulCourseClassID)
    {
        $data = array('tawasulCourseClassID' => $tawasulCourseClassID);
        $sql = "SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourseClass.name, tawasulCourseClass.nameShort, tawasulCourse.tawasulCourseID, tawasulCourse.name AS courseName, tawasulCourse.nameShort as courseNameShort, tawasulCourse.description AS courseDescription, tawasulCourse.tawasulDepartmentID, tawasulCourse.tawasulSchoolYearID, tawasulSchoolYear.name as yearName, tawasulYearGroupIDList, enrolmentMin, enrolmentMax, COUNT(DISTINCT tawasulCourseClassPerson.tawasulPersonID) as studentsTotal
                FROM tawasulCourseClass
                JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                LEFT JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND tawasulCourseClassPerson.role='Student')
                WHERE tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID
                GROUP BY tawasulCourseClass.tawasulCourseClassID";

        return $this->db()->selectOne($sql, $data);
    }
}
