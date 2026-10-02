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

namespace Tos\Module\TawasulReports\Domain;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

class ReportingCycleGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulReportingCycle';
    private static $primaryKey = 'tawasulReportingCycleID';
    private static $searchableColumns = ['tawasulReportingCycle.name'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryReportingCyclesBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID, $currentOnly = false)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulReportingCycle.tawasulReportingCycleID', 'name', 'dateStart', 'dateEnd', 'cycleNumber', "(SELECT GROUP_CONCAT(tawasulYearGroup.nameShort ORDER BY tawasulYearGroup.sequenceNumber SEPARATOR ', ') FROM tawasulYearGroup WHERE FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulReportingCycle.tawasulYearGroupIDList)) as yearGroups"])
            ->where('tawasulReportingCycle.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        if ($currentOnly) {
            $query->where(':today BETWEEN tawasulReportingCycle.dateStart AND tawasulReportingCycle.dateEnd')
                  ->bindValue('today', date('Y-m-d'));
        }

        return $this->runQuery($query, $criteria);
    }

    public function selectReportingCyclesBySchoolYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulReportingCycleID as value, name 
                FROM tawasulReportingCycle 
                WHERE tawasulSchoolYearID=:tawasulSchoolYearID
                ORDER BY sequenceNumber, name";

        return $this->db()->select($sql, $data);
    }
}
