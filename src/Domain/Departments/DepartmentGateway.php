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

namespace TawasulOS\Domain\Departments;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v17
 * @since   v17
 */
class DepartmentGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulDepartment';
    private static $primaryKey = 'tawasulDepartmentID';

    private static $searchableColumns = ['name'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryDepartments(QueryCriteria $criteria, $type = null, $branchCond = '', $branchParams = [])
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulDepartmentID', 'name', 'nameShort', 'type', 'subjectListing', 'blurb', 'logo'
            ]);

        if ($branchCond !== '') {
            $query->where($branchCond, $branchParams);
        }

        if (!empty($type)) {
            $query->where('tawasulDepartment.type = :type')
                  ->bindValue('type', $type);
        }

        return $this->runQuery($query, $criteria);
    }

    public function selectStaffByDepartment($tawasulDepartmentID)
    {
        $data = array('tawasulDepartmentID' => $tawasulDepartmentID);
        $sql = "SELECT preferredName, surname, title
                FROM tawasulDepartmentStaff 
                JOIN tawasulPerson ON (tawasulDepartmentStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) 
                WHERE tawasulPerson.status='Full' AND tawasulDepartmentID=:tawasulDepartmentID 
                ORDER BY surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectMemberOfDepartmentByRole($tawasulDepartmentID, $tawasulPersonID, array $roles = ['Teacher'])
    {
        $data = array('tawasulDepartmentID' => $tawasulDepartmentID, 'tawasulPersonID' => $tawasulPersonID, 'roles' => implode(',', $roles));
        $sql = "SELECT tawasulDepartmentStaff.* 
                FROM tawasulDepartment 
                JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) 
                WHERE tawasulDepartment.tawasulDepartmentID=:tawasulDepartmentID 
                AND tawasulDepartmentStaff.tawasulPersonID=:tawasulPersonID 
                AND FIND_IN_SET(tawasulDepartmentStaff.role, :roles)";

        return $this->db()->select($sql, $data);
    }

    public function selectDepartmentsByPerson($tawasulPersonID, $role = '') {
        $select = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'tawasulDepartment.tawasulDepartmentID', 'name',  'nameShort', 'type', 'subjectListing', 'blurb', 'logo'
            ])
            ->innerJoin('tawasulDepartmentStaff', 'tawasulDepartmentStaff.tawasulDepartmentID = tawasulDepartment.tawasulDepartmentID')
            ->where('tawasulDepartmentStaff.tawasulPersonID = :tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID);

        if (!empty($role)) {
            $select->where('tawasulDepartmentStaff.role = :role')
                   ->bindValue('role', $role);
        }

        return $this->runSelect($select);
    }

    public function getCourseByDepartment($tawasulDepartmentID, $tawasulCourseID)
    {
        $data = ['tawasulDepartmentID' => $tawasulDepartmentID, 'tawasulCourseID' => $tawasulCourseID];
        $sql = 'SELECT tawasulDepartment.name AS department, tawasulCourse.name, tawasulCourse.description, tawasulSchoolYear.name AS year, tawasulCourse.tawasulSchoolYearID, tawasulCourse.fields FROM tawasulDepartment JOIN tawasulCourse ON (tawasulDepartment.tawasulDepartmentID=tawasulCourse.tawasulDepartmentID) JOIN tawasulSchoolYear ON (tawasulCourse.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) WHERE tawasulDepartment.tawasulDepartmentID=:tawasulDepartmentID AND tawasulCourseID=:tawasulCourseID';

        return $this->db()->selectOne($sql, $data);
    }
    
    public function selectDepartmentsOfTypeLearningArea()
    {
        $data = [];
        $sql = "SELECT tawasulDepartmentID as value, name FROM tawasulDepartment WHERE type='Learning Area' ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    public function selectDepartmentsOfTypeLearningAreaByStaff($tawasulPersonID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulDepartment.tawasulDepartmentID as value, tawasulDepartment.name FROM tawasulDepartment JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) WHERE tawasulPersonID=:tawasulPersonID AND (role='Coordinator' OR role='Teacher (Curriculum)') AND type='Learning Area' ORDER BY name";

        return $this->db()->select($sql, $data);
    }
}
