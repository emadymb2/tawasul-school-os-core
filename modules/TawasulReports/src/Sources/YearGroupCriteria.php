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

namespace Tos\Module\TawasulReports\Sources;

use Tos\Module\TawasulReports\DataSource;

class YearGroupCriteria extends DataSource
{
    public function getSchema()
    {
        $gender = rand(0, 99) > 50 ? 'female' : 'male';

        return [
            'perGroup' => [
                0 => [
                    'scopeName'           => 'Year Group',
                    'criteriaName'        => 'Year Group Comment',
                    'criteriaDescription' => ['sentence'],
                    'value'               => ['randomDigit'],
                    'comment'             => ['paragraph', 6],
                    'valueType'           => 'Comment',
                    'title'               => ['title', $gender],
                    'surname'             => ['lastName'],
                    'firstName'           => ['firstName', $gender],
                    'preferredName'       => ['sameAs', 'firstName'],
                    'officialName'        => ['sameAs', 'firstName surname'],
                ],
            ],

            'perStudent' => [
                0 => [
                    'scopeName'           => 'Year Group',
                    'criteriaName'        => 'Student Comment',
                    'criteriaDescription' => ['sentence'],
                    'value'               => ['randomDigit'],
                    'comment'             => ['paragraph', 6],
                    'valueType'           => 'Comment',
                    'title'               => ['title', $gender],
                    'surname'             => ['lastName'],
                    'firstName'           => ['firstName', $gender],
                    'preferredName'       => ['sameAs', 'firstName'],
                    'officialName'        => ['sameAs', 'firstName surname'],
                ],
            ],
        ];
    }

    public function getData($ids = [])
    {
        $data = ['tawasulStudentEnrolmentID' => $ids['tawasulStudentEnrolmentID'], 'tawasulReportingCycleID' => $ids['tawasulReportingCycleID']];
        $sql = "SELECT DISTINCT (CASE WHEN tawasulReportingCriteria.target = 'Per Group' THEN 'perGroup' ELSE 'perStudent' END) AS groupBy, 
                    tawasulReportingScope.name as scopeName,
                    tawasulReportingCriteria.name as criteriaName,
                    tawasulReportingCriteria.description as criteriaDescription, 
                    tawasulReportingCriteria.category as category,
                    tawasulReportingValue.value, 
                    tawasulReportingValue.comment, 
                    tawasulScaleGrade.descriptor,
                    tawasulReportingCriteriaType.valueType, 
                    tawasulYearGroup.name as yearGroupName, 
                    tawasulYearGroup.nameShort as yearGroupNameShort,
                    createdBy.title,
                    createdBy.surname,
                    createdBy.firstName,
                    createdBy.preferredName,
                    createdBy.officialName,
                    tawasulStaff.jobTitle
                FROM tawasulStudentEnrolment 
                JOIN tawasulReportingCriteria ON (tawasulReportingCriteria.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID)
                JOIN tawasulReportingValue ON (tawasulReportingCriteria.tawasulReportingCriteriaID=tawasulReportingValue.tawasulReportingCriteriaID AND (tawasulReportingValue.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID OR tawasulReportingValue.tawasulPersonIDStudent=0))
                JOIN tawasulReportingCriteriaType ON (tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID)
                JOIN tawasulReportingScope ON (tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID)
                JOIN tawasulYearGroup ON (tawasulYearGroup.tawasulYearGroupID=tawasulReportingCriteria.tawasulYearGroupID)
                LEFT JOIN tawasulReportingProgress ON (tawasulReportingProgress.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID AND tawasulReportingProgress.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID)
                LEFT JOIN tawasulScaleGrade ON (tawasulScaleGrade.tawasulScaleID=tawasulReportingCriteriaType.tawasulScaleID AND tawasulScaleGrade.tawasulScaleGradeID=tawasulReportingValue.tawasulScaleGradeID)
                LEFT JOIN tawasulPerson as createdBy ON (createdBy.tawasulPersonID=tawasulReportingValue.tawasulPersonIDCreated)
                LEFT JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=createdBy.tawasulPersonID)
                WHERE tawasulStudentEnrolment.tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID
                AND tawasulReportingCriteria.tawasulReportingCycleID=:tawasulReportingCycleID
                AND tawasulReportingScope.scopeType='Year Group'
                AND ((tawasulReportingProgress.status='Complete' AND tawasulReportingCriteria.target = 'Per Student') 
                    OR tawasulReportingCriteria.target = 'Per Group') 
                ORDER BY tawasulReportingScope.sequenceNumber, tawasulReportingCriteria.sequenceNumber, tawasulYearGroup.sequenceNumber";

        return $this->db()->select($sql, $data)->fetchGrouped();
    }
}
