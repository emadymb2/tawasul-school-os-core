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

class InternalAssessmentByCourse extends DataSource
{
    public function getSchema()
    {
        return [
            'assessments' => [
                'Example Assessment' => [
                    'name'                 => 'Example Assessment',
                    'description'          => ['sentence'],
                    'type'                 => 'Type',
                    'attainmentActive'     => 'Y',
                    'effortActive'         => 'Y',
                    'completeDate'         => ['date', 'Y-m-d', '+1 year'],
                ],
                'Example Assessment 2' => [
                    'name'                 => 'Example Assessment 2',
                    'description'          => ['sentence'],
                    'type'                 => 'Type',
                    'attainmentActive'     => 'Y',
                    'effortActive'         => 'Y',
                    'completeDate'         => ['date', 'Y-m-d', '+1 year'],
                ]
            ],
            'courses' => [
                'Example Course' => [
                    'Example Assessment' => [
                        'attainmentValue'      => ['randomDigit'],
                        'attainmentDescriptor' => ['sameAs', 'attainmentValue'],
                        'effortValue'          => ['randomElement', ['Excellent', 'Very Good', 'Good', 'Satisfactory', 'Needs Improvement']],
                        'effortDescriptor'     => ['sameAs', 'effortValue'],
                        'commentActive'        => 'Y',
                        'comment'              => ['paragraph', 3],
                        'courseName'           => 'Example Course',
                        'courseNameShort'      => 'COURSE',
                        'className'            => 'Example Class',
                        'classNameShort'       => 'CLASS',
                    ],
                    'Example Assessment 2' => [
                        'attainmentValue'      => ['randomDigit'],
                        'attainmentDescriptor' => ['sameAs', 'attainmentValue'],
                        'effortValue'          => ['randomElement', ['Excellent', 'Very Good', 'Good', 'Satisfactory', 'Needs Improvement']],
                        'effortDescriptor'     => ['sameAs', 'effortValue'],
                        'commentActive'        => 'Y',
                        'comment'              => ['paragraph', 3],
                        'courseName'           => 'Example Course',
                        'courseNameShort'      => 'COURSE',
                        'className'            => 'Example Class',
                        'classNameShort'       => 'CLASS',
                    ],
                ],
                'Example Course 2' => [
                    'Example Assessment' => [
                        'attainmentValue'      => ['randomDigit'],
                        'attainmentDescriptor' => ['sameAs', 'attainmentValue'],
                        'effortValue'          => ['randomElement', ['Excellent', 'Very Good', 'Good', 'Satisfactory', 'Needs Improvement']],
                        'effortDescriptor'     => ['sameAs', 'effortValue'],
                        'commentActive'        => 'Y',
                        'comment'              => ['paragraph', 3],
                        'courseName'           => 'Example Course 2',
                        'courseNameShort'      => 'COURSE2',
                        'className'            => 'Example Class 2',
                        'classNameShort'       => 'CLASS2',
                    ],
                    'Example Assessment 2' => [
                        'attainmentValue'      => ['randomDigit'],
                        'attainmentDescriptor' => ['sameAs', 'attainmentValue'],
                        'effortValue'          => ['randomElement', ['Excellent', 'Very Good', 'Good', 'Satisfactory', 'Needs Improvement']],
                        'effortDescriptor'     => ['sameAs', 'effortValue'],
                        'commentActive'        => 'Y',
                        'comment'              => ['paragraph', 3],
                        'courseName'           => 'Example Course 2',
                        'courseNameShort'      => 'COURSE2',
                        'className'            => 'Example Class 2',
                        'classNameShort'       => 'CLASS2',
                    ],
                ],
            ]
        ];
    }

    public function getData($ids = [])
    {
        $data = array('tawasulStudentEnrolmentID' => $ids['tawasulStudentEnrolmentID'], 'tawasulReportID' => $ids['tawasulReportID'], 'today' => date('Y-m-d'));
        $sql = "SELECT tawasulCourse.name as groupBy,
                    tawasulInternalAssessmentColumn.name, 
                    tawasulInternalAssessmentColumn.description, 
                    tawasulInternalAssessmentColumn.type, 
                    tawasulInternalAssessmentColumn.attainment as attainmentActive, 
                    tawasulInternalAssessmentEntry.attainmentValue, 
                    tawasulInternalAssessmentEntry.attainmentDescriptor, 
                    tawasulInternalAssessmentColumn.effort as effortActive, 
                    tawasulInternalAssessmentEntry.effortValue, 
                    tawasulInternalAssessmentEntry.effortDescriptor, 
                    tawasulInternalAssessmentColumn.comment as commentActive, 
                    tawasulInternalAssessmentEntry.comment, 
                    tawasulCourse.name as courseName,
                    tawasulCourse.nameShort AS courseNameShort, 
                    tawasulCourseClass.name AS className, 
                    tawasulCourseClass.nameShort AS classNameShort,
                    tawasulInternalAssessmentColumn.completeDate
                FROM tawasulReport
                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulReport.tawasulSchoolYearID)
                JOIN tawasulInternalAssessmentEntry ON (tawasulInternalAssessmentEntry.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulInternalAssessmentColumn ON (tawasulInternalAssessmentEntry.tawasulInternalAssessmentColumnID=tawasulInternalAssessmentColumn.tawasulInternalAssessmentColumnID) 
                JOIN tawasulCourseClassPerson ON (tawasulInternalAssessmentColumn.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) 
                JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) 
                JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) 
                WHERE tawasulReport.tawasulReportID=:tawasulReportID
                AND tawasulStudentEnrolment.tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID
                AND FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, tawasulReport.tawasulYearGroupIDList)
                AND tawasulCourse.tawasulSchoolYearID=tawasulStudentEnrolment.tawasulSchoolYearID
                AND tawasulCourseClassPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID
                AND tawasulInternalAssessmentColumn.complete='Y'
                AND tawasulInternalAssessmentColumn.completeDate<=:today 
                ORDER BY tawasulInternalAssessmentColumn.completeDate DESC, tawasulCourse.nameShort, tawasulCourseClass.nameShort";

        $results = $this->db()->select($sql, $data)->fetchAll();
        $values = ['assessments' => [], 'courses' => []];

        foreach ($results as $result) {
            $values['assessments'][$result['name']] = [
                'name'             => $result['name'],
                'description'      => $result['description'],
                'attainmentActive' => $result['attainmentActive'],
                'effortActive'     => $result['effortActive'],
                'completeDate'     => $result['completeDate'],
            ];
            $values['courses'][$result['courseNameShort']][$result['name']] = $result;
        }

        return $values;
    }
}
