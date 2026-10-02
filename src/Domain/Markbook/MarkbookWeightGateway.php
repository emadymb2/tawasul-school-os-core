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

namespace TawasulOS\Domain\Markbook;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * Markbook Weight Gateway
 *
 * @version v20
 * @since   v20
 */
class MarkbookWeightGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulMarkbookWeight';
    private static $primaryKey = 'tawasulMarkbookWeightID';
    private static $searchableColumns = [];

    public function selectMarkbookWeightingsByClass($tawasulCourseClassID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID];
        $sql = "SELECT tawasulMarkbookColumn.type, tawasulMarkbookWeight.* 
                FROM tawasulMarkbookColumn
                LEFT JOIN tawasulMarkbookWeight ON (tawasulMarkbookWeight.type=tawasulMarkbookColumn.type AND tawasulMarkbookWeight.tawasulCourseClassID=tawasulMarkbookColumn.tawasulCourseClassID)
                WHERE tawasulMarkbookColumn.tawasulCourseClassID=:tawasulCourseClassID 
                ORDER BY tawasulMarkbookWeight.calculate, tawasulMarkbookWeight.weighting DESC";

        return $this->db()->select($sql, $data);
    }
}
