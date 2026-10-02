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

namespace TawasulOS\Domain\FormalAssessment;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v28
 * @since   v28
 */
class InternalAssessmentColumnGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulInternalAssessmentColumn';
    private static $primaryKey = 'tawasulInternalAssessmentColumnID';

    private static $searchableColumns = [];
    
    public function selectColumnsByClass($tawasulCourseClassID, $limit = null, $columnsPerPage = null)
    {
        $query = $this
            ->newQuery()
            ->cols(['*'])
            ->from($this->getTableName())
            ->where('tawasulCourseClassID = :tawasulCourseClassID')
            ->bindValue('tawasulCourseClassID', $tawasulCourseClassID)
            ->orderBy(['complete', 'completeDate DESC', 'name']);

        if ($columnsPerPage !== null && $limit !== null) { 
            $query->limit($limit)->offset($columnsPerPage);
        }

        return $this->runSelect($query);
    }
    
    public function getScaleByInternalAssessmentColumn($tawasulInternalAssessmentColumnID)
    {
        $data = ['tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID];
        $sql = "SELECT tawasulInternalAssessmentColumn.*, attainmentScale.name as scaleNameAttainment, attainmentScale.usage as usageAttainment, attainmentScale.lowestAcceptable as lowestAcceptableAttainment, effortScale.name as scaleNameEffort, effortScale.usage as usageEffort, effortScale.lowestAcceptable as lowestAcceptableEffort FROM tawasulInternalAssessmentColumn LEFT JOIN tawasulScale as attainmentScale ON (attainmentScale.tawasulScaleID=tawasulInternalAssessmentColumn.tawasulScaleIDAttainment) LEFT JOIN tawasulScale as effortScale ON (effortScale.tawasulScaleID=tawasulInternalAssessmentColumn.tawasulScaleIDEffort) WHERE tawasulInternalAssessmentColumnID=:tawasulInternalAssessmentColumnID";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectStudentsByCourseClassAndInternalAssessmentColumn($tawasulCourseClassID, $tawasulInternalAssessmentColumnID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID, 'today' => date('Y-m-d')];
        $sql = "SELECT tawasulPerson.tawasulPersonID, tawasulPerson.title, tawasulPerson.surname, tawasulPerson.preferredName, tawasulPerson.dateStart, tawasulInternalAssessmentEntry.*
            FROM tawasulCourseClassPerson JOIN tawasulPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID) LEFT JOIN tawasulInternalAssessmentEntry ON (tawasulInternalAssessmentEntry.tawasulPersonIDStudent=tawasulPerson.tawasulPersonID AND tawasulInternalAssessmentEntry.tawasulInternalAssessmentColumnID=:tawasulInternalAssessmentColumnID) WHERE tawasulCourseClassPerson.tawasulCourseClassID=:tawasulCourseClassID AND tawasulCourseClassPerson.reportable='Y' AND tawasulCourseClassPerson.role='Student' AND tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL  OR dateEnd>=:today) ORDER BY tawasulPerson.surname, tawasulPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectInternalAssessmentEntry($tawasulInternalAssessmentColumnID, $tawasulPersonIDStudent)
    {
        $data = ['tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID, 'tawasulPersonIDStudent' => $tawasulPersonIDStudent];
        $sql = 'SELECT * FROM tawasulInternalAssessmentEntry WHERE tawasulInternalAssessmentColumnID=:tawasulInternalAssessmentColumnID AND tawasulPersonIDStudent=:tawasulPersonIDStudent';

        return $this->db()->select($sql, $data);
    }

    public function getInternalAssessmentEntryByStudent($tawasulInternalAssessmentColumnID, $tawasulPersonIDStudent)
    {
        $data = ['tawasulInternalAssessmentColumnID' => $tawasulInternalAssessmentColumnID, 'tawasulPersonIDStudent' => $tawasulPersonIDStudent];
        $sql = 'SELECT * FROM tawasulInternalAssessmentEntry WHERE tawasulInternalAssessmentColumnID=:tawasulInternalAssessmentColumnID AND tawasulPersonIDStudent=:tawasulPersonIDStudent';

        return $this->db()->selectOne($sql, $data);
    }
}
