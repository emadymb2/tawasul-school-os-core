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

class ReportGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulReport';
    private static $primaryKey = 'tawasulReportID';
    private static $searchableColumns = ['tawasulReport.name'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryReportsBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulReport.tawasulReportID', 'tawasulReport.name', 'tawasulReport.active', 'tawasulReport.status', 'tawasulReport.timestampModified', 'tawasulReport.accessDate', 'tawasulReportTemplate.context',
            "(SELECT GROUP_CONCAT(tawasulYearGroup.nameShort separator ', ') FROM tawasulYearGroup WHERE FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulReport.tawasulYearGroupIDList)) as yearGroups", "(SELECT MAX(timestampModified) FROM tawasulReportArchiveEntry WHERE tawasulReportArchiveEntry.tawasulReportID=tawasulReport.tawasulReportID AND type='Batch') as timestampGenerated"])
            ->innerJoin('tawasulReportTemplate', 'tawasulReportTemplate.tawasulReportTemplateID=tawasulReport.tawasulReportTemplateID')
            ->leftJoin('tawasulReportingCycle', 'tawasulReportingCycle.tawasulReportingCycleID=tawasulReport.tawasulReportingCycleID')
            ->where('tawasulReport.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('tawasulReport.active = :active')
                    ->bindValue('active', $active);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryYearGroupsByReport(QueryCriteria $criteria, $tawasulReportID, $viewDraft = false)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulReport.tawasulReportID', 'tawasulYearGroup.tawasulYearGroupID', 'tawasulYearGroup.name', 'tawasulYearGroup.nameShort', 'tawasulYearGroup.sequenceNumber', 'tawasulReport.tawasulSchoolYearID', 'COUNT(DISTINCT tawasulReportArchiveEntry.tawasulPersonID) as count', "COUNT(DISTINCT CASE WHEN tawasulReportArchiveEntry.tawasulPersonIDAccessed IS NOT NULL THEN tawasulReportArchiveEntry.tawasulReportArchiveEntryID END) as readCount"])
            ->innerJoin('tawasulYearGroup', 'FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulReport.tawasulYearGroupIDList)')
            ->where('tawasulReport.tawasulReportID=:tawasulReportID')
            ->bindValue('tawasulReportID', $tawasulReportID)
            ->groupBy(['tawasulReport.tawasulReportID', 'tawasulYearGroup.tawasulYearGroupID']);

        if (!$viewDraft) {
            $query->leftJoin('tawasulReportArchiveEntry', "tawasulReportArchiveEntry.tawasulReportID=tawasulReport.tawasulReportID AND tawasulReportArchiveEntry.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID AND tawasulReportArchiveEntry.type='Single' AND tawasulReportArchiveEntry.status='Final'");
        } else {
            $query->leftJoin('tawasulReportArchiveEntry', "tawasulReportArchiveEntry.tawasulReportID=tawasulReport.tawasulReportID AND tawasulReportArchiveEntry.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID AND tawasulReportArchiveEntry.type='Single'");
        }

        return $this->runQuery($query, $criteria);
    }

    public function queryFormGroupsByReport(QueryCriteria $criteria, $tawasulReportID, $tawasulYearGroupID = '', $viewDraft = false)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulReport.tawasulReportID', 'tawasulYearGroup.tawasulYearGroupID', 'tawasulFormGroup.tawasulFormGroupID', 'tawasulFormGroup.name', 'tawasulYearGroup.nameShort', 'tawasulYearGroup.sequenceNumber', 'tawasulReport.tawasulSchoolYearID', 'COUNT(DISTINCT tawasulReportArchiveEntry.tawasulPersonID) as count', "COUNT(DISTINCT CASE WHEN tawasulReportArchiveEntry.tawasulPersonIDAccessed IS NOT NULL THEN tawasulReportArchiveEntry.tawasulReportArchiveEntryID END) as readCount"])
            ->innerJoin('tawasulReportArchiveEntry', 'tawasulReportArchiveEntry.tawasulReportID=tawasulReport.tawasulReportID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulReportArchiveEntry.tawasulFormGroupID')
            ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulReportArchiveEntry.tawasulYearGroupID')
            ->where("tawasulReportArchiveEntry.type='Single'")
            ->where('(tawasulReport.tawasulReportID=:tawasulReportID OR tawasulReportArchiveEntry.reportIdentifier=:tawasulReportID)')
            ->bindValue('tawasulReportID', $tawasulReportID)
            ->where('tawasulYearGroup.tawasulYearGroupID=:tawasulYearGroupID')
            ->bindValue('tawasulYearGroupID', $tawasulYearGroupID)
            ->groupBy(['tawasulReport.tawasulReportID', 'tawasulFormGroup.tawasulFormGroupID']);

        if (!$viewDraft) {
            $query->where("tawasulReportArchiveEntry.status='Final'");
        }

        return $this->runQuery($query, $criteria);
    }

    public function selectActiveReportsBySchoolYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulReportID as value, name FROM tawasulReport WHERE active='Y' AND tawasulSchoolYearID=:tawasulSchoolYearID";

        return $this->db()->select($sql, $data);
    }

    public function getRunningReports($tawasulReportID = null, $tawasulYearGroupID = null)
    {
        $query = $this
            ->newSelect()
            ->from('tawasulLog')
            ->cols(['tawasulLog.tawasulLogID', 'tawasulLog.serialisedArray'])
            ->where("tawasulLog.title='Background Process - GenerateReportProcess'")
            ->where("(tawasulLog.serialisedArray LIKE '%s:7:\"Running\";%' OR tawasulLog.serialisedArray LIKE '%s:7:\"Ready\";%')")
            ->orderBy(['tawasulLog.timestamp DESC']);

        $logs = $this->runSelect($query)->fetchAll();

        return array_filter(array_reduce($logs, function ($group, $item) {
            $item['data'] = unserialize($item['serialisedArray']) ?? [];
            $item['data']['processID'] = $item['tawasulLogID'];
            $item['tawasulReportID'] = $item['data']['data'][0];
            $item['tawasulYearGroupID'] = $item['data']['data'][1];

            $tawasulYearGroupIDList = is_array($item['tawasulYearGroupID'])? $item['tawasulYearGroupID'] : [$item['tawasulYearGroupID']];
            foreach ($tawasulYearGroupIDList as $tawasulYearGroupID) {
                $group[$item['tawasulReportID']][$tawasulYearGroupID] = $item['data'];
            }

            return $group;
        }, []));
    }
}
