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

class CourseCriteriaByCycle extends DataSource
{
    public function getSchema()
    {
        return [
            0 => [
                'courseName' => 'Example Course',
                'courseNameShort' => 'EXAMPLE',
                'className' => 'Class 1',
                'classNameShort' => '1',
                'teachers'  => $this->getFactory()->get('ClassTeachers')->getSchema(),
                'perGroup' => [
                    0 => [
                        'scopeName'           => 'Course',
                        'criteriaName'        => 'Course Comment',
                        'criteriaDescription' => ['sentence'],
                        'comment'             => ['paragraph', 6],
                        'valueType'           => 'Comment',
                        'values' => [
                            'Cycle 1' => '',
                            'Cycle 2' => '',
                            'Cycle 3' => '',
                        ]
                    ],
                ],
                'perStudent' => [

                    0 => [
                        'scopeName'           => 'Course',
                        'criteriaName'        => 'Student Comment',
                        'criteriaDescription' => ['sentence'],
                        'comment'             => ['paragraph', 6],
                        'valueType'           => 'Comment',
                        'values' => [
                            'Cycle 1' => '',
                            'Cycle 2' => '',
                            'Cycle 3' => '',
                        ]
                    ],
                    1 => [
                        'scopeName'           => 'Course',
                        'criteriaName'        => 'Student Grade',
                        'criteriaDescription' => ['sentence'],
                        'comment'             => '',
                        'valueType'           => 'Grade Scale',
                        'values' => [
                            'Cycle 1' => [
                                'value' => ['randomElement', ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D']],
                                'descriptor' => ['sameAs', 'value']
                            ],
                            'Cycle 2' => [
                                'value' => ['randomElement', ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D']],
                                'descriptor' => ['sameAs', 'value']
                            ],
                            'Cycle 3' => [
                                'value' => ['randomElement', ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D']],
                                'descriptor' => ['sameAs', 'value']
                            ],
                        ]
                    ],
                ],
            ],
        ];
    }

    public function getData($ids = [])
    {
        $data = ['tawasulStudentEnrolmentID' => $ids['tawasulStudentEnrolmentID'], 'tawasulReportID' => $ids['tawasulReportID']];
        $sql = "SELECT DISTINCT tawasulCourse.tawasulCourseID, tawasulCourseClass.tawasulCourseClassID,
                    (CASE WHEN tawasulReportingCriteria.target = 'Per Group' THEN 'perGroup' ELSE 'perStudent' END) AS groupBy, 
                    tawasulReportingCycle.nameShort as cycleName,
                    tawasulReportingScope.name as scopeName,
                    tawasulReportingCriteria.name as criteriaName,
                    tawasulReportingCriteria.description as criteriaDescription, 
                    tawasulReportingCriteria.tawasulReportingCriteriaID as criteriaID,
                    tawasulReportingValue.value, 
                    tawasulReportingValue.comment, 
                    tawasulScaleGrade.descriptor,
                    tawasulReportingCriteriaType.valueType, 
                    tawasulCourse.name as courseName, 
                    tawasulCourse.nameShort as courseNameShort,
                    tawasulCourseClass.name as className, 
                    tawasulCourseClass.nameShort as classNameShort
                FROM tawasulReport
                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReport.tawasulSchoolYearID)
                JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                LEFT JOIN tawasulReportingCriteria ON (tawasulReportingCriteria.tawasulCourseID=tawasulCourse.tawasulCourseID)
                LEFT JOIN tawasulReportingCriteriaType ON (tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID)
                LEFT JOIN tawasulReportingScope ON (tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID)
                LEFT JOIN tawasulReportingCycle ON (tawasulReportingCycle.tawasulReportingCycleID=tawasulReportingScope.tawasulReportingCycleID)
                LEFT JOIN tawasulReportingValue ON (
                    tawasulReportingValue.tawasulReportingCriteriaID=tawasulReportingCriteria.tawasulReportingCriteriaID
                    AND
                    (
                        (tawasulReportingCriteria.target='Per Student' AND tawasulReportingValue.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID) 
                        OR (tawasulReportingCriteria.target='Per Group' AND tawasulReportingValue.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                    ))
                LEFT JOIN tawasulReportingProgress ON (tawasulReportingProgress.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND (tawasulReportingProgress.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID OR tawasulReportingProgress.tawasulPersonIDStudent=0))
                LEFT JOIN tawasulScaleGrade ON (tawasulScaleGrade.tawasulScaleID=tawasulReportingCriteriaType.tawasulScaleID AND tawasulScaleGrade.tawasulScaleGradeID=tawasulReportingValue.tawasulScaleGradeID)
                WHERE tawasulReport.tawasulReportID=:tawasulReportID
                AND tawasulStudentEnrolment.tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID
                AND tawasulCourse.tawasulSchoolYearID=tawasulStudentEnrolment.tawasulSchoolYearID
                AND FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, tawasulReport.tawasulYearGroupIDList)
                AND FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, tawasulReportingCycle.tawasulYearGroupIDList)
                AND tawasulCourseClass.reportable='Y'
                AND tawasulCourseClassPerson.role='Student'
                AND tawasulCourseClassPerson.reportable='Y'
                AND tawasulReportingScope.scopeType='Course'
                AND ((tawasulReportingProgress.status='Complete' AND tawasulReportingCriteria.target = 'Per Student') 
                    OR tawasulReportingCriteria.target = 'Per Group') 
                AND (valueType IS NULL OR valueType <> 'Comment' OR (valueType = 'Comment' AND tawasulReportingCriteria.tawasulReportingCycleID=tawasulReport.tawasulReportingCycleID))
                ORDER BY tawasulReportingScope.sequenceNumber, tawasulReportingCriteria.sequenceNumber, tawasulCourse.orderBy, tawasulCourse.nameShort, tawasulReportingCriteria.sequenceNumber";

        $courses = $this->db()->select($sql, $data)->fetchAll();

        $courses = array_reduce($courses, function ($group, $item) {
            $cycle = $item['cycleName'];
            $courseID = $item['tawasulCourseID'];

            $group[$courseID]['tawasulCourseID'] = $item['tawasulCourseID'];
            $group[$courseID]['tawasulCourseClassID'] = $item['tawasulCourseClassID'];
            $group[$courseID]['courseName'] = $item['courseName'];
            $group[$courseID]['courseNameShort'] = $item['courseNameShort'];
            $group[$courseID]['className'] = $item['className'];
            $group[$courseID]['classNameShort'] = $item['classNameShort'];

            $group[$courseID][$item['groupBy']][$item['criteriaName']]['scopeName'] = $item['scopeName'];
            $group[$courseID][$item['groupBy']][$item['criteriaName']]['criteriaName'] = $item['criteriaName'];
            $group[$courseID][$item['groupBy']][$item['criteriaName']]['criteriaDescription'] = $item['criteriaDescription'];
            $group[$courseID][$item['groupBy']][$item['criteriaName']]['comment'] = $item['comment'];
            $group[$courseID][$item['groupBy']][$item['criteriaName']]['valueType'] = $item['valueType'];

            $values = $group[$courseID][$item['groupBy']][$item['criteriaName']]['values'] ?? [];
            $values[$cycle] = [
                'value'      => $item['value'],
                'descriptor' => $item['descriptor'],
            ];
            $group[$courseID][$item['groupBy']][$item['criteriaName']]['values'] = $values;

            if ($item['valueType'] == 'Comment') {
                $group[$courseID]['hasComments'] = true;
            }
            
            return $group;
        }, []);

        $courses = array_map(function ($course) use (&$ids) {
            $ids['tawasulCourseID'] = $course['tawasulCourseID'];
            $ids['tawasulCourseClassID'] = $course['tawasulCourseClassID'];

            $course['teachers'] = $this->getFactory()->get('ClassTeachers')->getData($ids);

            return $course;
        }, $courses);

        return $courses;
    }
}
