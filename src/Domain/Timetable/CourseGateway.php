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
class CourseGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulCourse';
    private static $primaryKey = 'tawasulCourseID';

    private static $searchableColumns = ['tawasulCourse.name', 'tawasulCourse.nameShort'];

    /**
     * @param QueryCriteria $criteria
     * @param string $branchCond Optional condition from
     *                         tosBranchScope() scoping the query to one branch.
     * @param array  $branchParams Bind parameters for $branchCond.
     * @return DataSet
     */
    public function queryCoursesBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID, $branchCond = '', $branchParams = [])
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulCourse.tawasulCourseID', 'tawasulCourse.name', 'tawasulCourse.nameShort', 'tawasulDepartment.name as department', 'COUNT(DISTINCT tawasulCourseClassID) as classCount'
            ])
            ->leftJoin('tawasulDepartment', 'tawasulDepartment.tawasulDepartmentID=tawasulCourse.tawasulDepartmentID')
            ->leftJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->where('tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->groupBy(['tawasulCourse.tawasulCourseID']);

        // Scoped on the class rather than the course: a course is a subject
        // offered across the whole school group, while its classes belong to a
        // campus. tawasulCourseClass is left-joined, so a course with no classes
        // yet yields NULL here and stays visible — which is what we want, since
        // a course nobody has timetabled is not campus-specific.
        if ($branchCond !== '') {
            $query->where($branchCond, $branchParams);
        }

        $criteria->addFilterRules([
            'yearGroup' => function ($query, $tawasulYearGroupID) {
                return $query
                    ->where('FIND_IN_SET(:tawasulYearGroupID, tawasulCourse.tawasulYearGroupIDList)')
                    ->bindValue('tawasulYearGroupID', $tawasulYearGroupID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryCoursesByDepartmentStaff(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulCourse.tawasulCourseID', 'tawasulCourse.name', 'tawasulCourse.nameShort', 'tawasulDepartment.name as department', 'COUNT(DISTINCT tawasulCourseClassID) as classCount'
            ])
            ->innerJoin('tawasulDepartment', 'tawasulDepartment.tawasulDepartmentID=tawasulCourse.tawasulDepartmentID')
            ->innerJoin('tawasulDepartmentStaff', 'tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID')
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->where("(tawasulDepartmentStaff.role='Coordinator' OR tawasulDepartmentStaff.role='Assistant Coordinator')")
            ->where('tawasulCourse.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulDepartmentStaff.tawasulPersonID = :tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->groupBy(['tawasulCourse.tawasulCourseID']);

        $criteria->addFilterRules([
            'yearGroup' => function ($query, $tawasulYearGroupID) {
                return $query
                    ->where('FIND_IN_SET(:tawasulYearGroupID, tawasulCourse.tawasulYearGroupIDList)')
                    ->bindValue('tawasulYearGroupID', $tawasulYearGroupID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectClassesBySchoolYear($tawasulSchoolYearID)
    {
        $data= ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.name as courseName, tawasulCourse.nameShort as course, tawasulCourseClass.nameShort as class
                FROM tawasulCourse
                JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID
                ORDER BY tawasulCourse.nameShort, tawasulCourseClass.nameShort";

        return $this->db()->select($sql, $data);
    }

    public function selectCoursesBySchoolYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulCourseID, CONCAT(tawasulCourse.nameShort, ' - ', tawasulCourse.name) AS course
                FROM tawasulCourse
                JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                WHERE tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID
                ORDER BY nameShort";

        return $this->db()->select($sql, $data);
    }

    public function selectActiveAndUpcomingCourses($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulSchoolYear.name as groupBy, tawasulCourseID as value, CONCAT(tawasulCourse.nameShort, ' - ', tawasulCourse.name) AS name
                FROM tawasulCourse
                JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                WHERE tawasulCourse.tawasulSchoolYearID>=:tawasulSchoolYearID
                ORDER BY tawasulSchoolYear.sequenceNumber, tawasulCourse.nameShort";

        return $this->db()->select($sql, $data);
    }

    public function selectCoursesByPerson($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulCourseID, CONCAT(tawasulCourse.nameShort, ' - ', tawasulCourse.name) AS course
                FROM tawasulCourse
                JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID)
                JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID)
                WHERE tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID
                AND tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID
                AND (role='Coordinator' OR role='Assistant Coordinator' OR role='Teacher (Curriculum)')
                ORDER BY tawasulCourse.nameShort";

        return $this->db()->select($sql, $data);
    }

    public function selectCourseDetailsByCourse($tawasulCourseID)
    {
        $data = ['tawasulCourseID' => $tawasulCourseID];
        $sql = 'SELECT *, tawasulSchoolYear.name AS schoolYear, tawasulCourse.nameShort AS course
                FROM tawasulCourse
                JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                WHERE tawasulCourse.tawasulCourseID=:tawasulCourseID';

        return $this->db()->select($sql, $data);
    }

    public function selectCourseDetailsByCourseAndPerson($tawasulCourseID, $tawasulPersonID)
    {
        $data = ['tawasulCourseID' => $tawasulCourseID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulCourseID, tawasulCourse.name, tawasulCourse.nameShort, tawasulCourse.tawasulYearGroupIDList, tawasulCourse.tawasulDepartmentID, tawasulSchoolYear.name AS schoolYear
            FROM tawasulCourse
            JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
            JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID)
            JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID)
            WHERE tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID
            AND (role='Coordinator' OR role='Assistant Coordinator' OR role='Teacher (Curriculum)')
            AND tawasulCourseID=:tawasulCourseID
            ORDER BY tawasulCourse.nameShort";

        return $this->db()->select($sql, $data);
    }

    public function selectCourseDetailsByClass($tawasulCourseClassID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID];
        $sql = 'SELECT *, tawasulSchoolYear.name AS schoolYear, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class
                FROM tawasulCourse
                JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                WHERE tawasulCourseClassID=:tawasulCourseClassID';

        return $this->db()->select($sql, $data);
    }

    public function selectCourseDetailsByClassAndPerson($tawasulCourseClassID, $tawasulPersonID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulCourse.tawasulCourseID, tawasulCourse.name, tawasulCourse.nameShort, tawasulSchoolYear.name AS schoolYear, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class
                FROM tawasulCourse
                JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                JOIN tawasulDepartment ON (tawasulCourse.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID)
                JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID)
                WHERE tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID
                AND (role='Coordinator' OR role='Assistant Coordinator' OR role='Teacher (Curriculum)')
                AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID
                ORDER BY tawasulCourse.nameShort";

        return $this->db()->select($sql, $data);
    }    

    public function getCourseClassDetails($tawasulCourseClassID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID];
        $sql = "SELECT tawasulCourseClass.tawasulCourseClassID, tawasulCourse.tawasulSchoolYearID, tawasulDepartment.name AS department, tawasulCourse.name AS courseLong, tawasulCourse.nameShort AS course, tawasulCourseClass.name AS classLong, tawasulCourseClass.nameShort AS class, tawasulCourse.tawasulCourseID, tawasulSchoolYear.name AS year, tawasulCourseClass.attendance, tawasulCourseClass.fields, tawasulSchoolYear.firstDay, tawasulSchoolYear.lastDay
                FROM tawasulCourse
                JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                LEFT JOIN tawasulDepartment ON (tawasulDepartment.tawasulDepartmentID=tawasulCourse.tawasulDepartmentID)
                WHERE tawasulCourseClassID=:tawasulCourseClassID";
        
        return $this->db()->selectOne($sql, $data);
    }

    // SELECT tawasulCourseClass.*, firstDay, lastDay,

    public function getCourseClassInfoByID($tawasulCourseClassID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID];
        $sql = "SELECT tawasulCourse.tawasulSchoolYearID, tawasulCourse.name AS courseLong, tawasulCourse.nameShort AS course, tawasulCourseClass.name AS classLong, tawasulCourseClass.nameShort AS class, tawasulCourse.tawasulCourseID, tawasulSchoolYear.name AS year, tawasulCourseClass.attendance, tawasulCourseClass.fields
                    FROM tawasulCourse
                    JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                    JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                    WHERE tawasulCourseClassID=:tawasulCourseClassID";
        
        return $this->db()->selectOne($sql, $data);
    }

    public function selectCoursesAndClassesBySchoolYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulCourseClassID as value, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as name
                FROM tawasulCourse
                JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                WHERE tawasulSchoolYearID=:tawasulSchoolYearID
                ORDER BY tawasulCourse.nameShort, tawasulCourseClass.nameShort";
        
        return $this->db()->select($sql, $data);
    }
    public function selectCourseListBySchoolYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulCourseID as value, nameShort as name FROM tawasulCourse WHERE tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY name";

        return $this->db()->select($sql, $data);
    }
      
    public function selectCourseListBySchoolYearAndPerson($tawasulSchoolYearID, $tawasulPersonID )
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulCourse.tawasulCourseID as value, tawasulCourse.nameShort as name FROM tawasulCourse JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulPersonID=:tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID AND NOT role LIKE '%- Left' GROUP BY tawasulCourse.tawasulCourseID ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    public function getCourseDetails($tawasulCourseID)
    {
        $data = ['tawasulCourseID' => $tawasulCourseID];
        $sql = 'SELECT tawasulSchoolYear.name AS year, tawasulDepartment.name AS department, tawasulCourse.name AS course, description, tawasulCourse.tawasulSchoolYearID FROM tawasulCourse JOIN tawasulDepartment ON (tawasulDepartment.tawasulDepartmentID=tawasulCourse.tawasulDepartmentID) JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) WHERE tawasulCourseID=:tawasulCourseID';

        return $this->db()->selectOne($sql, $data);
    }

    public function selectClassesByCourse($tawasulCourseID)
    {
        $data = ['tawasulCourseID' => $tawasulCourseID];
        $sql = 'SELECT tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourse JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulCourse.tawasulCourseID=:tawasulCourseID ORDER BY class';

        return $this->db()->select($sql, $data);
    }

    public function selectCurrentCoursesByDepartment($tawasulDepartmentID)
    {
        $data = ['tawasulDepartmentID' => $tawasulDepartmentID];
        $sql = "SELECT tawasulCourse.* FROM tawasulCourse
            JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
            WHERE tawasulDepartmentID=:tawasulDepartmentID
            AND tawasulYearGroupIDList <> ''
            AND tawasulSchoolYearID=(SELECT tawasulSchoolYearID FROM tawasulSchoolYear WHERE status='Current')
            GROUP BY tawasulCourse.tawasulCourseID
            ORDER BY nameShort, name";

        return $this->db()->select($sql, $data);
    }

    public function selectPastCoursesByDepartment($tawasulDepartmentID, $tawasulSchoolYearID)
    {
        $data = ['tawasulDepartmentID' => $tawasulDepartmentID, 'tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulSchoolYear.name AS year, tawasulCourse.tawasulCourseID as value, tawasulCourse.name AS name
                        FROM tawasulCourse
                        JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                        WHERE tawasulDepartmentID=:tawasulDepartmentID
                        AND NOT tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID
                        ORDER BY sequenceNumber, tawasulCourse.nameShort, name";
        return $this->db()->select($sql, $data);
    }
    
    public function selectClassListBySchoolYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulCourseClassID as value, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as name FROM tawasulCourse JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY name";
        
        return $this->db()->select($sql, $data);
    }

    public function selectClassesByStaff($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = 'SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulCourseClass.tawasulCourseClassID FROM tawasulCourse, tawasulCourseClass, tawasulCourseClassPerson WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID AND tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID AND tawasulCourseClassPerson.tawasulPersonID=:tawasulPersonID AND NOT role LIKE \'% - Left%\' ORDER BY course, class';
        
        return $this->db()->select($sql, $data);
    }
        
    public function selectClassListBySchoolYearAndPerson($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulCourseClass.tawasulCourseClassID as value, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as name FROM tawasulCourse JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) WHERE tawasulPersonID=:tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID AND NOT role LIKE '%- Left' ORDER BY tawasulCourseClass.name";
        
        return $this->db()->select($sql, $data);
    }
}
