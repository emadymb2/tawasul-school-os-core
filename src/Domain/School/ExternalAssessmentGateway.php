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
class ExternalAssessmentGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulExternalAssessment';
    private static $primaryKey = 'tawasulExternalAssessmentID';

    private static $searchableColumns = ['name'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryExternalAssessments(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulExternalAssessmentID', 'name', 'description', 'active', 'allowFileUpload'
            ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryExternalAssessmentFields(QueryCriteria $criteria, $tawasulExternalAssessmentID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulExternalAssessmentField')
            ->cols([
                'tawasulExternalAssessmentFieldID', 'tawasulExternalAssessmentID', 'name', 'category', 'tawasulExternalAssessmentField.order'
            ])
            ->where('tawasulExternalAssessmentField.tawasulExternalAssessmentID = :tawasulExternalAssessmentID')
            ->bindValue('tawasulExternalAssessmentID', $tawasulExternalAssessmentID);

        return $this->runQuery($query, $criteria);
    }

    public function selectActiveExternalAssessments()
    {
        $data = [];
        $sql = "SELECT tawasulExternalAssessmentID as value, name FROM tawasulExternalAssessment WHERE active='Y' ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    public function selectCATGradesByPersonID($tawasulPersonID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT * FROM tawasulExternalAssessment JOIN tawasulExternalAssessmentStudent ON (tawasulExternalAssessmentStudent.tawasulExternalAssessmentID=tawasulExternalAssessment.tawasulExternalAssessmentID) WHERE name='Cognitive Abilities Test' AND tawasulPersonID=:tawasulPersonID ORDER BY date DESC";

        return $this->db()->select($sql, $data);
    }
}
