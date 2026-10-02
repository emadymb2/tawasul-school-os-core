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
 * Staff Absence Type Gateway
 *
 * @version v18
 * @since   v18
 */
class StaffAbsenceTypeGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulStaffAbsenceType';
    private static $primaryKey = 'tawasulStaffAbsenceTypeID';

    private static $searchableColumns = ['name', 'nameShort'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAbsenceTypes(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulStaffAbsenceTypeID', 'name', 'nameShort', 'reasons', 'active', 'requiresApproval', 
            ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectAllTypes()
    {
        $sql = "SELECT * FROM tawasulStaffAbsenceType ORDER BY sequenceNumber, nameShort";

        return $this->db()->select($sql);
    }

    public function selectTypesRequiringApproval()
    {
        $sql = "SELECT * FROM tawasulStaffAbsenceType WHERE requiresApproval = 'Y' ORDER BY sequenceNumber, nameShort";

        return $this->db()->select($sql);
    }
}
