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

class CourseCriteria extends DataSource
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
                        'value'               => ['randomDigit'],
                        'comment'             => ['paragraph', 6],
                        'valueType'           => 'Comment',
                    ],
                ],
                'perStudent' => [
                    0 => [
                        'scopeName'           => 'Course',
                        'criteriaName'        => 'Student Comment',
                        'criteriaDescription' => ['sentence'],
                        'value'               => ['randomDigit'],
                        'comment'             => ['paragraph', 6],
                        'valueType'           => 'Comment',
                    ],
                    1 => [
                        'scopeName'           => 'Course',
                        'criteriaName'        => 'Student Grade',
                        'criteriaDescription' => ['sentence'],
                        'value'               => ['randomElement', ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D']],
                        'comment'             => '',
                        'valueType'           => 'Grade Scale',
                    ],
                ],
            ],
        ];
    }

    public function getData($ids = [])
    {
        $data = ['tawasulStudentEnrolmentID' => $ids['tawasulStudentEnrolmentID'], 'tawasulReportingCycleID' => $ids['tawasulReportingCycleID']];
        $sql = "SELECT DISTINCT tawasulCourse.tawasulCourseID, tawasulCourseClass.tawasulCourseClassID,
                    (CASE WHEN tawasulReportingCriteria.target = 'Per Group' THEN 'perGroup' ELSE 'perStudent' END) AS groupBy, 
                    tawasulReportingScope.name as scopeName,
                    tawasulReportingCriteria.name as criteriaName,
                    tawasulReportingCriteria.description as criteriaDescription, 
                    tawasulReportingValue.value, 
                    tawasulReportingValue.comment, 
                    tawasulScaleGrade.descriptor,
                    tawasulReportingCriteriaType.valueType, 
                    tawasulCourse.name as courseName, 
                    tawasulCourse.nameShort as courseNameShort,
                    tawasulCourseClass.name as className, 
                    tawasulCourseClass.nameShort as classNameShort,
                    author.title as authorTitle,
                    author.preferredName as authorPreferredName,
                    author.surname as authorSurname
                FROM tawasulStudentEnrolment 
                JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                JOIN tawasulReportingCriteria ON (tawasulReportingCriteria.tawasulCourseID=tawasulCourse.tawasulCourseID)
                JOIN tawasulReportingCriteriaType ON (tawasulReportingCriteriaType.tawasulReportingCriteriaTypeID=tawasulReportingCriteria.tawasulReportingCriteriaTypeID)
                JOIN tawasulReportingScope ON (tawasulReportingScope.tawasulReportingScopeID=tawasulReportingCriteria.tawasulReportingScopeID)
                LEFT JOIN tawasulReportingValue ON (tawasulReportingCriteria.tawasulReportingCriteriaID=tawasulReportingValue.tawasulReportingCriteriaID AND tawasulReportingValue.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND (tawasulReportingValue.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID OR tawasulReportingValue.tawasulPersonIDStudent=0))
                LEFT JOIN tawasulReportingProgress ON (tawasulReportingProgress.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID AND (tawasulReportingProgress.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID OR tawasulReportingProgress.tawasulPersonIDStudent=0))
                LEFT JOIN tawasulScaleGrade ON (tawasulScaleGrade.tawasulScaleID=tawasulReportingCriteriaType.tawasulScaleID AND tawasulScaleGrade.tawasulScaleGradeID=tawasulReportingValue.tawasulScaleGradeID)
                LEFT JOIN tawasulPerson as author ON (tawasulReportingValue.tawasulPersonIDCreated=author.tawasulPersonID)
                WHERE tawasulStudentEnrolment.tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID
                AND tawasulReportingCriteria.tawasulReportingCycleID=:tawasulReportingCycleID
                AND tawasulCourse.tawasulSchoolYearID=tawasulStudentEnrolment.tawasulSchoolYearID
                AND (tawasulCourseClassPerson.role='Student' OR tawasulCourseClassPerson.role='Student - Left')
                AND tawasulReportingScope.scopeType='Course'
                AND ((tawasulReportingProgress.status='Complete' AND tawasulReportingCriteria.target = 'Per Student') 
                    OR tawasulReportingCriteria.target = 'Per Group') 
                ORDER BY tawasulReportingScope.sequenceNumber, tawasulReportingCriteria.sequenceNumber, tawasulCourse.orderBy, tawasulCourse.name";

        $courses = $this->db()->select($sql, $data)->fetchAll();

        $courses = array_reduce($courses, function ($group, $item) {
            $courseID = $item['tawasulCourseID'];
            $group[$courseID][$item['groupBy']][] = $item;
            $group[$courseID]['tawasulCourseID'] = $item['tawasulCourseID'];
            $group[$courseID]['tawasulCourseClassID'] = $item['tawasulCourseClassID'];
            $group[$courseID]['courseName'] = $item['courseName'];
            $group[$courseID]['courseNameShort'] = $item['courseNameShort'];
            $group[$courseID]['className'] = $item['className'];
            $group[$courseID]['classNameShort'] = $item['classNameShort'];
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
