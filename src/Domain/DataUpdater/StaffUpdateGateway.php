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

namespace TawasulOS\Domain\DataUpdater;

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\Traits\TableAware;

/**
 * @version v22
 * @since   v22
 */
class StaffUpdateGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulStaffUpdate';
    private static $primaryKey = 'tawasulStaffUpdateID';

    private static $searchableColumns = ['target.surname', 'target.preferredName', 'target.username'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryDataUpdates(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulStaffUpdateID', 'tawasulStaffUpdate.status', 'tawasulStaffUpdate.timestamp', 'target.title', 'target.preferredName', 'target.surname', 'target.tawasulPersonID as tawasulPersonIDTarget', 'updater.tawasulPersonID as tawasulPersonIDUpdater', 'updater.title as updaterTitle', 'updater.preferredName as updaterPreferredName', 'updater.surname as updaterSurname'
            ])
            ->leftJoin('tawasulStaff', 'tawasulStaff.tawasulStaffID=tawasulStaffUpdate.tawasulStaffID')
            ->leftJoin('tawasulPerson AS target', 'target.tawasulPersonID=tawasulStaff.tawasulPersonID')
            ->leftJoin('tawasulPerson AS updater', 'updater.tawasulPersonID=tawasulStaffUpdate.tawasulPersonIDUpdater')
            ->where('tawasulStaffUpdate.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        return $this->runQuery($query, $criteria);
    }
}
