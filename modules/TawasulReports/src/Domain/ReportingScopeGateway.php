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

class ReportingScopeGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulReportingScope';
    private static $primaryKey = 'tawasulReportingScopeID';
    private static $searchableColumns = ['tawasulReportingScope.name'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryReportingScopesByCycle(QueryCriteria $criteria, $tawasulReportingCycleID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulReportingScope.tawasulReportingScopeID', 'tawasulReportingScope.name', 'tawasulReportingScope.scopeType', "GROUP_CONCAT(DISTINCT tawasulRole.name ORDER BY tawasulRole.name SEPARATOR ', ') as accessRoles"])
            ->leftJoin('tawasulReportingAccess', 'FIND_IN_SET(tawasulReportingScope.tawasulReportingScopeID, tawasulReportingAccess.tawasulReportingScopeIDList)')
            ->leftJoin('tawasulRole', 'FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulReportingAccess.tawasulRoleIDList)')
            ->where('tawasulReportingScope.tawasulReportingCycleID=:tawasulReportingCycleID')
            ->bindValue('tawasulReportingCycleID', $tawasulReportingCycleID)
            ->groupBy(['tawasulReportingScope.tawasulReportingScopeID']);

        return $this->runQuery($query, $criteria);
    }

    public function selectReportingScopesBySchoolYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulReportingScope.tawasulReportingCycleID as chained, tawasulReportingScope.tawasulReportingScopeID as value, tawasulReportingScope.name 
                FROM tawasulReportingScope 
                JOIN tawasulReportingCycle ON (tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingScope.tawasulReportingCycleID)
                WHERE tawasulReportingCycle.tawasulSchoolYearID=:tawasulSchoolYearID
                ORDER BY tawasulReportingCycle.sequenceNumber, tawasulReportingScope.sequenceNumber";

        return $this->db()->select($sql, $data);
    }

    public function selectRelatedReportingScopesByID($tawasulReportingScopeID, $scopeType, $scopeTypeID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulReportingScope as currentScope')
            ->cols(['tawasulReportingCycle.tawasulReportingCycleID', 'tawasulReportingScope.tawasulReportingScopeID', 'tawasulReportingCycle.name', 'tawasulReportingCycle.nameShort'])
            ->innerJoin('tawasulReportingCycle as currentCycle', 'currentScope.tawasulReportingCycleID=currentCycle.tawasulReportingCycleID')
            ->innerJoin('tawasulYearGroup', 'FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, currentCycle.tawasulYearGroupIDList)')
            ->innerJoin('tawasulReportingCycle', 'FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulReportingCycle.tawasulYearGroupIDList)')
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID')
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
            ->where('currentScope.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->where('currentScope.scopeType=tawasulReportingScope.scopeType')
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
            ->groupBy(['tawasulReportingCycle.tawasulReportingCycleID'])
            ->orderBy(['tawasulReportingCycle.sequenceNumber']);

        if ($scopeType == 'Year Group') {
            $query->where('tawasulReportingCriteria.tawasulYearGroupID=:scopeTypeID')
                  ->bindValue('scopeTypeID', $scopeTypeID);
        } elseif ($scopeType == 'Form Group') {
            $query->where('tawasulReportingCriteria.tawasulFormGroupID=:scopeTypeID')
                  ->bindValue('scopeTypeID', $scopeTypeID);
        } elseif ($scopeType == 'Course') {
            $query->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseID=tawasulReportingCriteria.tawasulCourseID')
                  ->where('tawasulCourseClass.tawasulCourseClassID=:scopeTypeID')
                  ->bindValue('scopeTypeID', $scopeTypeID);
        }

        return $this->runSelect($query);
    }
}
