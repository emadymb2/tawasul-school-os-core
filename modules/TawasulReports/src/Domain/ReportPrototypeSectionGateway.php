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

class ReportPrototypeSectionGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulReportPrototypeSection';
    private static $primaryKey = 'tawasulReportPrototypeSectionID';
    private static $searchableColumns = ['tawasulReportPrototypeSection.name', 'type', 'category'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryPrototypes(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulReportPrototypeSection.tawasulReportPrototypeSectionID', 'name', 'type', 'category', 'templateFile', 'active', 'fonts', 'tawasulPersonIDLastEdit']);

        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('tawasulReportPrototypeSection.active = :active')
                    ->bindValue('active', $active);
            },
            'type' => function ($query, $type) {
                return $query
                    ->where('tawasulReportPrototypeSection.type = :type')
                    ->bindValue('type', $type);
            },
        ]);
        
        return $this->runQuery($query, $criteria);
    }

    public function selectPrototypeSections($type)
    {
        $data = ['type' => $type];
        $sql = "SELECT category, tawasulReportPrototypeSectionID as value, name, icon
                FROM tawasulReportPrototypeSection 
                WHERE type=:type AND (types LIKE '%Head%' OR types LIKE '%Foot%' OR types LIKE '%Body%')
                ORDER BY type DESC, FIND_IN_SET(category, 'Headers & Footers,Miscellaneous'), category, name";

        return $this->db()->select($sql, $data);
    }

    public function selectPrototypeStylesheets()
    {
        $sql = "SELECT type, templateFile as value, name 
                FROM tawasulReportPrototypeSection 
                WHERE types LIKE '%Stylesheet%'
                ORDER BY type, name";

        return $this->db()->select($sql);
    }

    public function getPrototypeSectionByID($tawasulReportPrototypeSectionID)
    {
        $data = ['tawasulReportPrototypeSectionID' => $tawasulReportPrototypeSectionID];
        $sql = "SELECT *
                FROM tawasulReportPrototypeSection 
                WHERE tawasulReportPrototypeSectionID=:tawasulReportPrototypeSectionID";

        return $this->db()->selectOne($sql, $data);
    }

    public function updateActiveStatus($tawasulReportPrototypeSectionIDList, $active)
    {
        $data = ['tawasulReportPrototypeSectionIDList' => $tawasulReportPrototypeSectionIDList, 'active' => $active];
        $sql = "UPDATE tawasulReportPrototypeSection SET tawasulReportPrototypeSection.active=:active WHERE FIND_IN_SET(tawasulReportPrototypeSectionID, :tawasulReportPrototypeSectionIDList)";

        return $this->db()->update($sql, $data);
    }
}
