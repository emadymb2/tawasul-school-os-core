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

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v22
 * @since   v22
 */
class PersonalDocumentTypeGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulPersonalDocumentType';
    private static $primaryKey = 'tawasulPersonalDocumentTypeID';

    private static $searchableColumns = [];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryDocumentTypes(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulPersonalDocumentTypeID', 'name', 'description', 'active', 'required', 'document', 'type', 'activePersonStudent', 'activePersonParent', 'activePersonStaff', 'activePersonOther' 
            ]);

        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('tawasulPersonalDocumentType.active = :active')
                    ->bindValue('active', $active);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectDocumentTypes()
    {
        $sql = "SELECT tawasulPersonalDocumentTypeID as value, name FROM tawasulPersonalDocumentType WHERE active='Y' ORDER BY sequenceNumber, name";

        return $this->db()->select($sql);
    }

    public function selectDocumentTypesWithFileUpload()
    {
        $sql = "SELECT tawasulPersonalDocumentTypeID as value, name FROM tawasulPersonalDocumentType WHERE fields LIKE '%filePath%' ORDER BY sequenceNumber, name";

        return $this->db()->select($sql);
    }
}
