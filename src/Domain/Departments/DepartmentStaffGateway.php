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
 * @version v27
 * @since   v27
 */
class DepartmentStaffGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulDepartmentStaff';
    private static $primaryKey = 'tawasulDepartmentStaffID';

    private static $searchableColumns = ['role'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */


     public function seletStaffListByDepartment($tawasulDepartmentID)
     {
        $data = ['tawasulDepartmentID' => $tawasulDepartmentID];
        $sql = "SELECT tawasulPerson.tawasulPersonID, tawasulDepartmentStaff.role, title, surname, preferredName, image_240, tawasulStaff.jobTitle, FIND_IN_SET(role, 'Manager,Assistant Coordinator,Coordinator,Director') as roleOrder
            FROM tawasulDepartmentStaff 
            JOIN tawasulPerson ON (tawasulDepartmentStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) 
            JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) 
            WHERE status='Full' AND tawasulDepartmentID=:tawasulDepartmentID 
            ORDER BY roleOrder DESC, surname, preferredName";
            
        return $this->db()->select($sql, $data);
     }

}