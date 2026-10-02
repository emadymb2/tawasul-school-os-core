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
class ExternalAssessmentStudentGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulExternalAssessmentStudent';
    private static $primaryKey = 'tawasulExternalAssessmentStudentID';

    private static $searchableColumns = [];

    public function selectGCSEGradesByPersonID($tawasulPersonID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT * FROM tawasulExternalAssessment JOIN tawasulExternalAssessmentStudent ON (tawasulExternalAssessmentStudent.tawasulExternalAssessmentID=tawasulExternalAssessment.tawasulExternalAssessmentID) WHERE name='GCSE/iGCSE' AND tawasulPersonID=:tawasulPersonID ORDER BY date DESC";

        return $this->db()->select($sql, $data);
    }

    public function getStudentExternalAssessmentDetails($tawasulExternalAssessmentStudentID)
    {
        $data = ['tawasulExternalAssessmentStudentID' => $tawasulExternalAssessmentStudentID];
        $sql = 'SELECT tawasulExternalAssessmentStudent.*, tawasulExternalAssessment.name AS assessment, tawasulExternalAssessment.allowFileUpload FROM tawasulExternalAssessmentStudent JOIN tawasulExternalAssessment ON (tawasulExternalAssessmentStudent.tawasulExternalAssessmentID=tawasulExternalAssessment.tawasulExternalAssessmentID) WHERE tawasulExternalAssessmentStudentID=:tawasulExternalAssessmentStudentID';

        return $this->db()->selectOne($sql, $data);
    }

    public function selectStudentExternalAssessmentGrades($tawasulPersonID, $tawasulExternalAssessmentFieldID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'tawasulExternalAssessmentFieldID' => $tawasulExternalAssessmentFieldID];
        $sql = "SELECT tawasulScaleGrade.value, tawasulScaleGrade.descriptor, tawasulExternalAssessmentStudent.date FROM tawasulExternalAssessmentStudentEntry JOIN tawasulExternalAssessmentStudent ON (tawasulExternalAssessmentStudentEntry.tawasulExternalAssessmentStudentID=tawasulExternalAssessmentStudent.tawasulExternalAssessmentStudentID) JOIN tawasulScaleGrade ON (tawasulExternalAssessmentStudentEntry.tawasulScaleGradeID=tawasulScaleGrade.tawasulScaleGradeID) WHERE tawasulPersonID=:tawasulPersonID AND tawasulExternalAssessmentFieldID=:tawasulExternalAssessmentFieldID AND NOT tawasulExternalAssessmentStudentEntry.tawasulScaleGradeID='' ORDER BY date DESC";

        return $this->db()->select($sql, $data);
    }
}
