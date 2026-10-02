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

namespace Tos\Module\TawasulReports\Domain;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

class ReportingValueGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulReportingValue';
    private static $primaryKey = 'tawasulReportingValueID';
    private static $searchableColumns = [''];


    public function getGradeScaleValueByID($tawasulScaleGradeID)
    {
        $data = ['tawasulScaleGradeID' => $tawasulScaleGradeID];
        $sql = "SELECT value FROM tawasulScaleGrade WHERE tawasulScaleGradeID=:tawasulScaleGradeID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getGradeScaleIDByValue($tawasulScaleID, $value)
    {
        $data = ['tawasulScaleID' => $tawasulScaleID, 'value' => $value];
        $sql = "SELECT tawasulScaleGradeID FROM tawasulScaleGrade WHERE tawasulScaleID=:tawasulScaleID AND value=:value";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectReportingCommentsByCycle($tawasulReportingCycleID)
    {
        $data = ['tawasulReportingCycleID' => $tawasulReportingCycleID];
        $sql = "SELECT tawasulReportingValue.tawasulReportingValueID, tawasulReportingValue.comment, tawasulPerson.tawasulPersonID, tawasulPerson.preferredName, tawasulPerson.surname, tawasulPerson.gender, tawasulReportingScope.scopeType, (CASE WHEN scopeType='Course' THEN tawasulReportingValue.tawasulCourseClassID WHEN scopeType='Form Group' THEN tawasulReportingCriteria.tawasulFormGroupID WHEN scopeType='Year Group' THEN tawasulReportingCriteria.tawasulYearGroupID END) as scopeTypeID, tawasulReportingScope.tawasulReportingScopeID, tawasulReportingCriteria.name as criteriaName, tawasulReportingProgress.tawasulReportingProgressID, tawasulReportingProgress.checked
                FROM tawasulReportingCycle
                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReportingCycle.tawasulSchoolYearID)
                JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulReportingValue ON (tawasulReportingValue.tawasulReportingCycleID=tawasulReportingCycle.tawasulReportingCycleID AND tawasulReportingValue.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulReportingCriteria ON (tawasulReportingCriteria.tawasulReportingCriteriaID=tawasulReportingValue.tawasulReportingCriteriaID)
                JOIN tawasulReportingCriteriaType ON (tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID)
                JOIN tawasulReportingScope ON (tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID)
                LEFT JOIN tawasulReportingProgress ON (tawasulReportingProgress.tawasulReportingScopeID=tawasulReportingScope.tawasulReportingScopeID  AND (
                    (tawasulReportingProgress.tawasulCourseClassID=tawasulReportingValue.tawasulCourseClassID AND scopeType='Course')
                    OR (tawasulReportingProgress.tawasulFormGroupID=tawasulReportingCriteria.tawasulFormGroupID AND scopeType='Form Group')
                    OR (tawasulReportingProgress.tawasulYearGroupID=tawasulReportingCriteria.tawasulYearGroupID AND scopeType='Year Group')
                    ) AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulReportingValue.tawasulPersonIDStudent
                )
                WHERE tawasulReportingCriteria.tawasulReportingCycleID=:tawasulReportingCycleID
                AND tawasulReportingCriteriaType.valueType='Comment'
                AND tawasulReportingCriteria.target = 'Per Student'
                AND (tawasulReportingValue.comment IS NOT NULL AND tawasulReportingValue.comment <> '')
                GROUP BY tawasulReportingValue.tawasulReportingValueID
                ORDER BY tawasulReportingProgress.checked, tawasulPerson.surname, tawasulPerson.preferredName, tawasulReportingScope.sequenceNumber, tawasulReportingCriteria.sequenceNumber";

        return $this->db()->select($sql, $data);
    }
}
