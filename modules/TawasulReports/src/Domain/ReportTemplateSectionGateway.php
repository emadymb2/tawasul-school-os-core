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

class ReportTemplateSectionGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulReportTemplateSection';
    private static $primaryKey = 'tawasulReportTemplateSectionID';
    private static $searchableColumns = ['tawasulReportTemplateSection.name'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function querySectionsByType(QueryCriteria $criteria, $tawasulReportTemplateID, $type = '')
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulReportTemplateSection.tawasulReportTemplateSectionID', 'tawasulReportTemplateSection.name', 'tawasulReportTemplateSection.type', 'tawasulReportPrototypeSection.templateFile', 'tawasulReportPrototypeSection.dataSources', 'tawasulReportPrototypeSection.fonts', 'tawasulReportTemplateSection.flags', 'tawasulReportTemplateSection.page', 'tawasulReportTemplateSection.templateParams', 'tawasulReportTemplateSection.config'])
            ->leftJoin('tawasulReportPrototypeSection', 'tawasulReportPrototypeSection.tawasulReportPrototypeSectionID=tawasulReportTemplateSection.tawasulReportPrototypeSectionID')
            ->where('tawasulReportTemplateSection.tawasulReportTemplateID=:tawasulReportTemplateID')
            ->bindValue('tawasulReportTemplateID', $tawasulReportTemplateID);

        if (!empty($type)) {
            $query->where('tawasulReportTemplateSection.type=:type')
                  ->bindValue('type', $type);
        }

        return $this->runQuery($query, $criteria);
    }
}
