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

class ReportingCriteriaGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulReportingCriteria';
    private static $primaryKey = 'tawasulReportingCriteriaID';
    private static $searchableColumns = ['tawasulReportingCriteria.description'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryReportingCriteriaGroupsByScope(QueryCriteria $criteria, $tawasulReportingScopeID, $scopeType)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulReportingCriteria.tawasulReportingCriteriaID', 'tawasulReportingCriteria.description', 'tawasulReportingCriteria.target', 'tawasulReportingCriteria.category', 'tawasulReportingCriteriaType.name as criteriaType', 'tawasulReportingCriteria.tawasulYearGroupID', 'tawasulReportingCriteria.tawasulFormGroupID', 'tawasulReportingCriteria.tawasulCourseID'])
            ->leftJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
            ->where('tawasulReportingCriteria.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID);

        if ($scopeType == 'Year Group') {
            $query->cols(['tawasulYearGroup.tawasulYearGroupID AS scopeTypeID', 'tawasulYearGroup.nameShort as nameShort', 'tawasulYearGroup.name as name', 'COUNT(tawasulReportingCriteria.tawasulReportingCriteriaID) AS count'])
                  ->leftJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulReportingCriteria.tawasulYearGroupID')
                  ->groupBy(['tawasulYearGroup.tawasulYearGroupID']);
        } elseif ($scopeType == 'Form Group') {
            $query->cols(['tawasulFormGroup.tawasulFormGroupID AS scopeTypeID', 'tawasulFormGroup.nameShort as nameShort', 'tawasulFormGroup.name as name', 'COUNT(tawasulReportingCriteria.tawasulReportingCriteriaID) AS count'])
                  ->leftJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulReportingCriteria.tawasulFormGroupID')
                  ->groupBy(['tawasulFormGroup.tawasulFormGroupID']);
        } elseif ($scopeType == 'Course') {
            $query->cols(['tawasulCourse.tawasulCourseID AS scopeTypeID', 'tawasulCourse.nameShort as nameShort', 'tawasulCourse.name as name', 'COUNT(tawasulReportingCriteria.tawasulReportingCriteriaID) AS count'])
                  ->leftJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulReportingCriteria.tawasulCourseID')
                  ->groupBy(['tawasulCourse.tawasulCourseID']);
        }

        return $this->runQuery($query, $criteria);
    }

    public function queryReportingCriteriaGroupsByCycle(QueryCriteria $criteria, $tawasulReportingCycleID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulReportingCriteria')
            ->cols(['tawasulReportingScope.name as scopeName', 'tawasulReportingScope.sequenceNumber', "CONCAT(tawasulReportingScope.tawasulReportingScopeID, '-', tawasulYearGroup.tawasulYearGroupID) as value", 'tawasulYearGroup.nameShort as name', 'tawasulYearGroup.sequenceNumber as nameOrder'])
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulReportingCriteria.tawasulYearGroupID')
            ->where('tawasulReportingScope.tawasulReportingCycleID=:tawasulReportingCycleID')
            ->where("tawasulReportingScope.scopeType = 'Year Group'")
            ->bindValue('tawasulReportingCycleID', $tawasulReportingCycleID)
            ->groupBy(['tawasulReportingScope.tawasulReportingScopeID', 'tawasulYearGroup.tawasulYearGroupID']);

        $query->unionAll()
            ->from('tawasulReportingCriteria')
            ->cols(['tawasulReportingScope.name as scopeName', 'tawasulReportingScope.sequenceNumber', "CONCAT(tawasulReportingScope.tawasulReportingScopeID, '-', tawasulFormGroup.tawasulFormGroupID) as value", 'tawasulFormGroup.nameShort as name', 'tawasulFormGroup.nameShort as nameOrder'])
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulReportingCriteria.tawasulFormGroupID')
            ->where('tawasulReportingScope.tawasulReportingCycleID=:tawasulReportingCycleID')
            ->where("tawasulReportingScope.scopeType = 'Form Group'")
            ->bindValue('tawasulReportingCycleID', $tawasulReportingCycleID)
            ->groupBy(['tawasulReportingScope.tawasulReportingScopeID', 'tawasulFormGroup.tawasulFormGroupID']);

        $query->unionAll()
            ->from('tawasulReportingCriteria')
            ->cols(['tawasulReportingScope.name as scopeName', 'tawasulReportingScope.sequenceNumber', "CONCAT(tawasulReportingScope.tawasulReportingScopeID, '-', tawasulCourseClass.tawasulCourseClassID) as value", "CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as name", "CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as nameOrder"])
            ->innerJoin('tawasulReportingScope', 'tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID')
            ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulReportingCriteria.tawasulCourseID')
            ->innerJoin('tawasulCourseClass', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->where('tawasulReportingScope.tawasulReportingCycleID=:tawasulReportingCycleID')
            ->where("tawasulReportingScope.scopeType = 'Course'")
            ->bindValue('tawasulReportingCycleID', $tawasulReportingCycleID)
            ->groupBy(['tawasulReportingScope.tawasulReportingScopeID', 'tawasulCourseClass.tawasulCourseClassID']);

        return $this->runQuery($query, $criteria);
    }

    public function queryReportingCriteriaByScope(QueryCriteria $criteria, $tawasulReportingScopeID, $scopeType, $scopeTypeID = null)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulReportingCriteria.tawasulReportingCriteriaID', 'tawasulReportingCriteria.name', 'tawasulReportingCriteria.description', 'tawasulReportingCriteria.target', 'tawasulReportingCriteria.category', 'tawasulReportingCriteriaType.name as criteriaType', 'tawasulReportingCriteria.tawasulYearGroupID', 'tawasulReportingCriteria.tawasulFormGroupID', 'tawasulReportingCriteria.tawasulCourseID', "COUNT(DISTINCT CASE WHEN tawasulReportingValueID IS NOT NULL THEN tawasulReportingValueID END) as values"])
            ->leftJoin('tawasulReportingCriteriaType', 'tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID')
            ->leftJoin('tawasulReportingValue', 'tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID')
            ->where('tawasulReportingCriteria.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
            ->groupBy(['tawasulReportingCriteria.tawasulReportingCriteriaID']);

        if ($scopeType == 'Year Group') {
            $query->cols(['tawasulYearGroup.nameShort as scopeTypeName', 'tawasulYearGroup.sequenceNumber as scopeSequence'])
                ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulReportingCriteria.tawasulYearGroupID');

            if (!empty($scopeTypeID)) {
                $query->where('tawasulReportingCriteria.tawasulYearGroupID=:tawasulYearGroupID', ['tawasulYearGroupID' => $scopeTypeID]);
            }
        } else if ($scopeType == 'Form Group') {
            $query->cols(['tawasulFormGroup.nameShort as scopeTypeName', 'tawasulFormGroup.nameShort as scopeSequence'])
                ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulReportingCriteria.tawasulFormGroupID');

            if (!empty($scopeTypeID)) {
                $query->where('tawasulReportingCriteria.tawasulFormGroupID=:tawasulFormGroupID', ['tawasulFormGroupID' => $scopeTypeID]);
            }
        } else if ($scopeType == 'Course') {
            $query->cols(['tawasulCourse.nameShort as scopeTypeName', 'tawasulCourse.nameShort as scopeSequence'])
                  ->innerJoin('tawasulCourse', 'tawasulCourse.tawasulCourseID=tawasulReportingCriteria.tawasulCourseID');

            if (!empty($scopeTypeID)) {
                $query->where('tawasulReportingCriteria.tawasulCourseID=:tawasulCourseID', ['tawasulCourseID' => $scopeTypeID]);
            }
        }
        return $this->runQuery($query, $criteria);
    }

    public function getCriteriaTypeByID($tawasulReportingCriteriaID)
    {
        $data = ['tawasulReportingCriteriaID' => $tawasulReportingCriteriaID];
        $sql = "SELECT tawasulReportingCriteriaType.* 
                FROM tawasulReportingCriteriaType 
                JOIN tawasulReportingCriteria ON (tawasulReportingCriteria.tawasulReportingCriteriaTypeID=tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID)
                WHERE tawasulReportingCriteria.tawasulReportingCriteriaID=:tawasulReportingCriteriaID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getHighestSequenceNumberByScope($tawasulReportingScopeID, $scopeType, $scopeTypeID)
    {
        $query = $this
            ->newSelect()
            ->from('tawasulReportingScope')
            ->cols(['MAX(tawasulReportingCriteria.sequenceNumber) as sequenceNumber', 'COUNT(tawasulReportingCriteria.tawasulReportingCriteriaID) as count'])
            ->innerJoin('tawasulReportingCriteria', 'tawasulReportingCriteria.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID')
            ->where('tawasulReportingScope.tawasulReportingScopeID=:tawasulReportingScopeID')
            ->bindValue('tawasulReportingScopeID', $tawasulReportingScopeID)
            ->groupBy(['tawasulReportingScope.tawasulReportingScopeID']);

        if ($scopeType == 'Year Group') {
            $query->where('tawasulReportingCriteria.tawasulYearGroupID=:scopeTypeID')
                  ->bindValue('scopeTypeID', $scopeTypeID);
        } elseif ($scopeType == 'Form Group') {
            $query->where('tawasulReportingCriteria.tawasulFormGroupID=:scopeTypeID')
                  ->bindValue('scopeTypeID', $scopeTypeID);
        } elseif ($scopeType == 'Course') {
            $query->where('tawasulReportingCriteria.tawasulCourseID=:scopeTypeID')
                  ->bindValue('scopeTypeID', $scopeTypeID);
        }

        return $this->runSelect($query)->fetchColumn(0) ?? 0;
    }
}
