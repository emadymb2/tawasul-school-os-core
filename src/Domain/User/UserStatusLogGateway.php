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

namespace TawasulOS\Domain\User;

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\Traits\TableAware;

/**
 * User Status Log Gateway
 *
 * @version v23
 * @since   v23
 */
class UserStatusLogGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulPersonStatusLog';
    private static $primaryKey = 'tawasulPersonStatusLogID';

    private static $searchableColumns = [''];


    public function queryStatusLogByPerson(QueryCriteria $criteria, $tawasulPersonID) {
        $query = $this
            ->newQuery()
            ->cols(['tawasulPersonStatusLog.tawasulPersonStatusLogID', 'tawasulPersonStatusLog.tawasulPersonID', 'tawasulPersonStatusLog.statusOld', 'tawasulPersonStatusLog.statusNew', 'tawasulPersonStatusLog.reason', 'tawasulPersonStatusLog.timestamp', 'tawasulPersonStatusLog.tawasulPersonIDModified', 'modified.surname', 'modified.preferredName'])
            ->from('tawasulPersonStatusLog')
            ->leftJoin('tawasulPerson as modified', 'modified.tawasulPersonID=tawasulPersonStatusLog.tawasulPersonIDModified')
            ->where('tawasulPersonStatusLog.tawasulPersonID = :tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID);
            
        return $this->runQuery($query, $criteria);
    }
}
