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
 * @version v21
 * @since   v21
 */
class ExternalAssessmentStudentEntryGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulExternalAssessmentStudentEntry';
    private static $primaryKey = 'tawasulExternalAssessmentStudentEntryID';

    private static $searchableColumns = [];

    public function selectGCSEGradesByStudentID($tawasulExternalAssessmentStudentID)
    {
        $data = ['category' => '%GCSE Target Grades', 'tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID];
        $sql = 'SELECT * FROM tawasulExternalAssessmentStudentEntry JOIN tawasulExternalAssessmentField ON (tawasulExternalAssessmentStudentEntry.tawasulExternalAssessmentFieldID=tawasulExternalAssessmentField.tawasulExternalAssessmentFieldID) WHERE category LIKE :category AND tawasulExternalAssessmentStudentID=:tawasulExternalAssessmentStudentID AND NOT (tawasulScaleGradeID IS NULL) ORDER BY name';

        return $this->db()->select($sql, $data);
    }

    public function selectTargetGradesByExternalAssessmentStudentID($tawasulExternalAssessmentStudentID)
    {
        $data = ['tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID];
        $sql = "SELECT * FROM tawasulExternalAssessmentStudentEntry JOIN tawasulExternalAssessmentField ON (tawasulExternalAssessmentStudentEntry.tawasulExternalAssessmentFieldID=tawasulExternalAssessmentField.tawasulExternalAssessmentFieldID) JOIN tawasulScaleGrade ON (tawasulExternalAssessmentStudentEntry.tawasulScaleGradeID=tawasulScaleGrade.tawasulScaleGradeID) WHERE category LIKE '%Target Grade' AND tawasulExternalAssessmentStudentID=:tawasulExternalAssessmentStudentID AND NOT (tawasulExternalAssessmentStudentEntry.tawasulScaleGradeID IS NULL) ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    public function selectFinalGradesByExternalAssessmentStudentID($tawasulExternalAssessmentStudentID)
    {
        $data = ['tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID];
        $sql = "SELECT * FROM tawasulExternalAssessmentStudentEntry JOIN tawasulExternalAssessmentField ON (tawasulExternalAssessmentStudentEntry.tawasulExternalAssessmentFieldID=tawasulExternalAssessmentField.tawasulExternalAssessmentFieldID) JOIN tawasulScaleGrade ON (tawasulExternalAssessmentStudentEntry.tawasulScaleGradeID=tawasulScaleGrade.tawasulScaleGradeID) WHERE category LIKE '%Final Grade' AND tawasulExternalAssessmentStudentID=:tawasulExternalAssessmentStudentID AND NOT (tawasulExternalAssessmentStudentEntry.tawasulScaleGradeID IS NULL) ORDER BY name";

        return $this->db()->select($sql, $data);
    }
}
