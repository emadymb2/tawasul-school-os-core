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
 * OutcomeGateway
 *
 * @version v24
 * @since   v24
 */
class OutcomeGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulOutcome';
    private static $primaryKey = 'tawasulOutcomeID';
    private static $searchableColumns = ['tawasulOutcome.name'];

    public function queryOutcomes($criteria, $tawasulDepartmentID = null)
    {
        $query = $this
            ->newQuery()
            ->cols([
                'tawasulOutcome.*',
                "GROUP_CONCAT(tawasulYearGroup.nameShort ORDER BY tawasulYearGroup.sequenceNumber SEPARATOR ', ') AS yearGroupList",
                "COUNT(tawasulYearGroup.tawasulYearGroupID) AS yearGroups",
                "(SELECT COUNT(*) FROM tawasulYearGroup) AS totalYearGroups",
                "tawasulDepartment.name AS department"
                ])
            ->from($this->getTableName())
            ->leftJoin('tawasulDepartment', 'tawasulDepartment.tawasulDepartmentID=tawasulOutcome.tawasulDepartmentID')
            ->leftJoin('tawasulYearGroup', 'FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulOutcome.tawasulYearGroupIDList)')
            ->groupBy(['tawasulOutcome.tawasulOutcomeID']);

        if (!empty($tawasulDepartmentID)) {
            $query
                ->where('tawasulOutcome.tawasulDepartmentID=:tawasulDepartmentID')
                ->bindValue('tawasulDepartmentID', $tawasulDepartmentID);
        }

        return $this->runQuery($query, $criteria);
    }

    public function selectOutcomesByYearGroup($tawasulYearGroupIDList)
    {
        $data = ['tawasulYearGroupIDList' => $tawasulYearGroupIDList];
		$sql = "SELECT tawasulOutcome.tawasulOutcomeID, tawasulOutcome.scope, tawasulOutcome.category, tawasulOutcome.name FROM tawasulOutcome LEFT JOIN tawasulYearGroup ON (FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulOutcome.tawasulYearGroupIDList)) WHERE tawasulOutcome.active='Y' AND FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, :tawasulYearGroupIDList) GROUP BY tawasulOutcome.tawasulOutcomeID ORDER BY tawasulOutcome.category, tawasulOutcome.name";

        return $this->db()->select($sql, $data);
    }
}
