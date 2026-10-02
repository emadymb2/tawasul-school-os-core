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
 * ResourceGateway
 *
 * @version v21
 * @since   v21
 */
class ResourceGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulResource';
    private static $primaryKey = 'tawasulResourceID';
    private static $searchableColumns = ['tawasulResource.name'];
    
    public function queryResources($criteria, $tawasulPersonID = null)
    {
        $query = $this
            ->newQuery()
            ->cols([
                'tawasulResource.*',
                'tawasulPerson.surname',
                'tawasulPerson.preferredName',
                'tawasulPerson.title',
                "GROUP_CONCAT(tawasulYearGroup.nameShort ORDER BY sequenceNumber SEPARATOR ', ') as yearGroupList",
                "COUNT(tawasulYearGroup.tawasulYearGroupID) as yearGroups",
                "(SELECT COUNT(*) FROM tawasulYearGroup) as totalYearGroups",
                ])
            ->from($this->getTableName())
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulResource.tawasulPersonID')
            ->leftJoin('tawasulYearGroup', 'FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulResource.tawasulYearGroupIDList)')
            ->groupBy(['tawasulResource.tawasulResourceID']);
          
        if (!empty($tawasulPersonID)) {
            $query
                ->where('tawasulResource.tawasulPersonID=:tawasulPersonID')
                ->bindValue('tawasulPersonID', $tawasulPersonID);
        }
        
        $criteria->addFilterRules([
            'tags' => function ($query, $tags) {
                $tagCount = 0;
                $tagArray = explode(',', $tags);
                foreach ($tagArray as $atag) {
                    return $query
                        ->where('concat(",", tags, ",") LIKE :tag'.$tagCount)
                        ->bindValue('tag'.$tagCount, "%,".$atag.",%");
                    ++$tagCount;
                }     
            },
            'category' => function ($query, $category) {
                return $query
                    ->where('category=:category')
                     ->bindValue('category', $category);
            },
            'purpose' => function ($query, $purpose) {
                return $query
                    ->where('purpose=:purpose')
                     ->bindValue('purpose', $purpose);
            },
            'tawasulYearGroupID' => function ($query, $tawasulYearGroupID) {
                return $query
                    ->where('FIND_IN_SET(:tawasulYearGroupID, tawasulResource.tawasulYearGroupIDList)')
                     ->bindValue('tawasulYearGroupID', $tawasulYearGroupID);
            }
        ]);

        return $this->runQuery($query, $criteria);
    }
}
