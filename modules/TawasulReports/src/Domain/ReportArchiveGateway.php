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

class ReportArchiveGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulReportArchive';
    private static $primaryKey = 'tawasulReportArchiveID';
    private static $searchableColumns = ['name'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryArchives(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulReportArchive.tawasulReportArchiveID', 'name', 'path', 'readonly']);

        return $this->runQuery($query, $criteria);
    }

    public function selectWriteableArchives()
    {
        $sql = "SELECT tawasulReportArchiveID as value, name FROM tawasulReportArchive WHERE readonly='N' ORDER BY name";

        return $this->db()->select($sql);
    }
}
