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
 * Staff Gateway
 *
 * @version v20
 * @since   v20
 */
class StaffContractGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulStaffContract';
    private static $primaryKey = 'tawasulStaffContractID';

    private static $searchableColumns = ['tawasulStaffID', 'dateStart'];

    /**
     * Queries the list of contracts by a Staff's ID.
     *
     * @param QueryCriteria $criteria
     * @param $tawasulStaffID
     * @return DataSet
     */
    public function queryContractsByStaff(QueryCriteria $criteria, $tawasulStaffID) {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulStaffContract.tawasulStaffContractID', 'tawasulStaffContract.tawasulStaffID', 'tawasulStaffContract.title', 'tawasulStaffContract.status', 'tawasulStaffContract.dateStart', 'tawasulStaffContract.dateEnd'
            ])
            ->where('tawasulStaffContract.tawasulStaffID = :tawasulStaffID')
            ->bindValue('tawasulStaffID', $tawasulStaffID);

        return $this->runQuery($query, $criteria);
    }
}
