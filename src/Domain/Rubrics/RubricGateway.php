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

namespace TawasulOS\Domain\Rubrics;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v17
 * @since   v17
 */
class RubricGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulRubric';
    private static $primaryKey = 'tawasulRubricID';
    private static $searchableColumns = ['tawasulRubric.name', 'tawasulRubric.category'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryRubrics(QueryCriteria $criteria, $active = null, $tawasulYearGroupID = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulRubricID', 'tawasulRubric.scope', 'tawasulRubric.category', 'tawasulRubric.name', 'tawasulRubric.description', 'tawasulRubric.active', 'tawasulRubric.tawasulDepartmentID', 'tawasulDepartment.name AS learningArea', 
                "GROUP_CONCAT(DISTINCT tawasulYearGroup.nameShort ORDER BY tawasulYearGroup.sequenceNumber SEPARATOR ', ') as yearGroups",
                "COUNT(DISTINCT tawasulYearGroup.tawasulYearGroupID) as yearGroupCount",
            ])
            ->leftJoin('tawasulDepartment', "tawasulRubric.scope = 'Learning Area' AND tawasulDepartment.tawasulDepartmentID=tawasulRubric.tawasulDepartmentID")
            ->leftJoin('tawasulYearGroup', 'FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulRubric.tawasulYearGroupIDList)')
            ->groupBy(['tawasulRubric.tawasulRubricID']);
            
        if (!empty($active)) {
            $query->where('tawasulRubric.active = :active')
                ->bindValue('active', $active);
        }

        if (!empty($tawasulYearGroupID)) {
            $query->where('FIND_IN_SET(:tawasulYearGroupID, tawasulRubric.tawasulYearGroupIDList)')
                ->bindValue('tawasulYearGroupID', $tawasulYearGroupID);
        }

        $criteria->addFilterRules([
            'department' => function ($query, $tawasulDepartmentID) {
                return $query
                    ->where('tawasulRubric.tawasulDepartmentID = :tawasulDepartmentID')
                    ->bindValue('tawasulDepartmentID', $tawasulDepartmentID);
            },
        ]);
        
        return $this->runQuery($query, $criteria);
    }

    public function selectRowsByRubric($tawasulRubricID)
    {
        $data = ['tawasulRubricID' => $tawasulRubricID];
        $sql = "SELECT *, (CASE WHEN tawasulOutcome.name IS NOT NULL THEN tawasulOutcome.name ELSE title END) as title FROM tawasulRubricRow LEFT JOIN tawasulOutcome ON (tawasulRubricRow.tawasulOutcomeID=tawasulOutcome.tawasulOutcomeID) WHERE tawasulRubricID=:tawasulRubricID ORDER BY sequenceNumber";

        return $this->db()->select($sql, $data);
    }

    public function selectColumnsByRubric($tawasulRubricID)
    {
        $data = ['tawasulRubricID' => $tawasulRubricID];
        $sql = "SELECT * FROM tawasulRubricColumn WHERE tawasulRubricID=:tawasulRubricID ORDER BY sequenceNumber";

        return $this->db()->select($sql, $data);
    }

    public function selectRowsByRubricInSequence($tawasulRubricID)
    {
        $data = ['tawasulRubricID' => $tawasulRubricID];
        $sql = 'SELECT * FROM tawasulRubricRow WHERE tawasulRubricID=:tawasulRubricID ORDER BY sequenceNumber';

        return $this->db()->select($sql, $data);
    }

    public function selectCellsByRubric($tawasulRubricID)
    {
        $data = ['tawasulRubricID' => $tawasulRubricID];
        $sql = "SELECT * FROM tawasulRubricCell WHERE tawasulRubricID=:tawasulRubricID";

        return $this->db()->select($sql, $data);
    }

    public function selectGradeScalesByRubric($tawasulRubricID)
    {
        $data = ['tawasulRubricID' => $tawasulRubricID];
        $sql = "SELECT tawasulScaleGrade.tawasulScaleGradeID, tawasulScaleGrade.*, tawasulScale.name FROM tawasulRubricColumn
        JOIN tawasulScaleGrade ON (tawasulRubricColumn.tawasulScaleGradeID=tawasulScaleGrade.tawasulScaleGradeID)
        JOIN tawasulScale ON (tawasulScale.tawasulScaleID=tawasulScaleGrade.tawasulScaleID)
        WHERE tawasulRubricColumn.tawasulRubricID=:tawasulRubricID";

        return $this->db()->select($sql, $data);
    }
    
    public function selectDistinctRubricCategories()
    {
        $data = [];
        $sql = "SELECT DISTINCT category FROM tawasulRubric ORDER BY category";

        return $this->db()->select($sql, $data);
    }

    public function selectLARubricsByStaffAndDepartment($tawasulRubricID, $tawasulPersonID)
    {
        $data = ['tawasulRubricID' => $tawasulRubricID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT * FROM tawasulRubric JOIN tawasulDepartment ON (tawasulRubric.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) JOIN tawasulDepartmentStaff ON (tawasulDepartmentStaff.tawasulDepartmentID=tawasulDepartment.tawasulDepartmentID) AND NOT tawasulRubric.tawasulDepartmentID IS NULL WHERE tawasulRubricID=:tawasulRubricID AND (role='Coordinator' OR role='Teacher (Curriculum)') AND tawasulPersonID=:tawasulPersonID AND scope='Learning Area'";

        return $this->db()->select($sql, $data);
    }

    public function getColumnByRubricAndColumnID($tawasulRubricID, $tawasulRubricColumnID)
    {
        $data = ['tawasulRubricID' => $tawasulRubricID, 'tawasulRubricColumnID' => $tawasulRubricColumnID];
        $sql = 'SELECT * FROM tawasulRubric JOIN tawasulRubricColumn ON (tawasulRubricColumn.tawasulRubricID=tawasulRubric.tawasulRubricID) WHERE tawasulRubricColumn.tawasulRubricID=:tawasulRubricID AND tawasulRubricColumnID=:tawasulRubricColumnID';

        return $this->db()->selectOne($sql, $data);
    }

    public function getRowByRubricAndRowID($tawasulRubricID, $tawasulRubricRowID)
    {
        $data = ['tawasulRubricID' => $tawasulRubricID, 'tawasulRubricRowID' => $tawasulRubricRowID];
        $sql = 'SELECT * FROM tawasulRubric JOIN tawasulRubricRow ON (tawasulRubricRow.tawasulRubricID=tawasulRubric.tawasulRubricID) WHERE tawasulRubricRow.tawasulRubricID=:tawasulRubricID AND tawasulRubricRowID=:tawasulRubricRowID';

        return $this->db()->selectOne($sql, $data);
    }

    public function selectRowsInfoByRubric($tawasulRubricID)
    {
        $data = ['tawasulRubricID' => $tawasulRubricID];
		$sql = "SELECT tawasulRubricRowID, title, tawasulOutcomeID, backgroundColor FROM tawasulRubricRow WHERE tawasulRubricID=:tawasulRubricID ORDER BY sequenceNumber";
                    
        return $this->db()->select($sql, $data);
    }

    public function selectsColumnsInfoByRubric($tawasulRubricID)
    {
        $data = ['tawasulRubricID' => $tawasulRubricID];
		$sql = "SELECT tawasulRubricColumnID, title, tawasulScaleGradeID, visualise, backgroundColor FROM tawasulRubricColumn WHERE tawasulRubricID=:tawasulRubricID ORDER BY sequenceNumber";

        return $this->db()->select($sql, $data);
    }
}
