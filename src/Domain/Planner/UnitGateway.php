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
 * UnitGateway
 *
 * @version v21
 * @since   v21
 */
class UnitGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulUnit';
    private static $primaryKey = 'tawasulUnitID';
    private static $searchableColumns = [];
    
    public function queryUnitsByCourse($criteria, $tawasulCourseID)
    {
        $query = $this
            ->newQuery()
            ->cols([
                'tawasulUnit.tawasulUnitID', 
                'tawasulUnit.name',
                'tawasulUnit.description',
                'tawasulUnit.active',
            ])
            ->from($this->getTableName())
            ->where('tawasulUnit.tawasulCourseID=:tawasulCourseID')
            ->bindValue('tawasulCourseID', $tawasulCourseID);

        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('tawasulUnit.active = :active')
                    ->bindValue('active', $active);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectActiveUnitsByCourse($tawasulCourseID)
    {
        $data = ['tawasulCourseID' => $tawasulCourseID];
        $sql = 'SELECT tawasulUnitID, tawasulUnit.name, tawasulUnit.description, attachment FROM tawasulUnit JOIN tawasulCourse ON (tawasulUnit.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulUnit.tawasulCourseID=:tawasulCourseID AND active=\'Y\' ORDER BY ordering, name';
       
        return $this->db()->select($sql, $data);
    }
  
    public function getUnitClassIDByUnit($tawasulUnitID, $tawasulCourseClassID)
    {
        $data = ['tawasulUnitID' => $tawasulUnitID, 'tawasulCourseClassID' => $tawasulCourseClassID];
        $sql = "SELECT tawasulUnitClassID FROM tawasulUnitClass WHERE tawasulUnitID=:tawasulUnitID AND tawasulCourseClassID=:tawasulCourseClassID";

        return $this->db()->selectOne($sql, $data);
    }

}
