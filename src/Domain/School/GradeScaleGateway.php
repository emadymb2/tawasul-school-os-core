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

namespace TawasulOS\Domain\School;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v17
 * @since   v17
 */
class GradeScaleGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulScale';
    private static $primaryKey = 'tawasulScaleID';

    private static $searchableColumns = ['name'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryGradeScales(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulScaleID', 'name', 'nameShort', 'tawasulScale.usage', 'tawasulScale.active', 'tawasulScale.numeric'
            ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryGradeScaleGrades(QueryCriteria $criteria, $tawasulScaleID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulScaleGrade')
            ->cols([
                'tawasulScaleGradeID', 'tawasulScaleID', 'value', 'descriptor', 'sequenceNumber', 'isDefault'
            ])
            ->where('tawasulScaleGrade.tawasulScaleID = :tawasulScaleID')
            ->bindValue('tawasulScaleID', $tawasulScaleID);

        return $this->runQuery($query, $criteria);
    }
  
    public function getScaleGradeByScaleAttainmentAndValue($attainmentValue, $scaleAttainment)
    {
        $data = ['attainmentValue' => $attainmentValue, 'scaleAttainment' => $scaleAttainment];
        $sql = 'SELECT * FROM tawasulScaleGrade JOIN tawasulScale ON (tawasulScaleGrade.tawasulScaleID=tawasulScale.tawasulScaleID) WHERE value=:attainmentValue AND tawasulScaleGrade.tawasulScaleID=:scaleAttainment';

        return $this->db()->selectOne($sql, $data);
    }

    public function getScaleGradeByScaleEffortAndValue($effortValue, $scaleEffort)
    {
        $data = ['effortValue' => $effortValue, 'scaleEffort' => $scaleEffort];
        $sql = 'SELECT * FROM tawasulScaleGrade JOIN tawasulScale ON (tawasulScaleGrade.tawasulScaleID=tawasulScale.tawasulScaleID) WHERE value=:effortValue AND tawasulScaleGrade.tawasulScaleID=:scaleEffort';

        return $this->db()->selectOne($sql, $data);
    }

    public function selectAllGradeScales()
    {
        $sql = "SELECT tawasulScaleID as value, name FROM tawasulScale ORDER BY name";
    
        return $this->db()->select($sql);
    }

    public function selectActiveGradeScales()
    {
        $sql = "SELECT tawasulScaleID as value, name FROM tawasulScale WHERE (active='Y') ORDER BY name";
    
        return $this->db()->select($sql);
    }

    public function selectGradesByScale($tawasulScaleID)
    {
        $data = ['tawasulScaleID' => $tawasulScaleID];
		    $sql = "SELECT tawasulScaleGradeID as value, CONCAT(value, ' - ', descriptor) as name FROM tawasulScaleGrade WHERE tawasulScaleID=:tawasulScaleID AND NOT value='Incomplete' ORDER BY sequenceNumber";

        return $this->db()->select($sql, $data);
    }
  
    public function getDefaultGrade($tawasulScaleID)
    {
        $select = $this
            ->newSelect()
            ->cols(['tawasulScaleGrade.value'])
            ->from($this->getTableName())
            ->innerJoin('tawasulScaleGrade', "tawasulScaleGrade.tawasulScaleID=tawasulScale.tawasulScaleID AND tawasulScaleGrade.isDefault='Y'")
            ->where('tawasulScale.tawasulScaleID = :tawasulScaleID')
            ->bindValue('tawasulScaleID', $tawasulScaleID);

        return $this->runSelect($select)->fetchColumn(0);
    }
}
