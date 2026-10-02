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

class InternalAssessment extends DataSource
{
    public function getSchema()
    {
        return [
            0 => [
                'name'                 => 'Example Assessment',
                'description'          => ['sentence'],
                'type'                 => 'Type',
                'attainmentActive'     => 'Y',
                'attainmentValue'      => ['randomDigit'],
                'attainmentDescriptor' => ['sameAs', 'attainmentValue'],
                'effortActive'         => 'Y',
                'effortValue'          => ['randomElement', ['Excellent', 'Very Good', 'Good', 'Satisfactory', 'Needs Improvement']],
                'effortDescriptor'     => ['sameAs', 'effortDescriptor'],
                'commentActive'        => 'Y',
                'comment'              => ['paragraph', 3],
                'courseName'           => 'Example Course',
                'courseNameShort'      => 'COURSE',
                'className'            => 'Example Class',
                'classNameShort'       => 'CLASS',
                'completeDate'         => ['date', 'Y-m-d'],
            ],
        ];
    }

    public function getData($ids = [])
    {
        $data = array('tawasulStudentEnrolmentID' => $ids['tawasulStudentEnrolmentID'], 'today' => date('Y-m-d'));
        $sql = "SELECT tawasulInternalAssessmentColumn.name, 
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
                FROM tawasulStudentEnrolment
                JOIN tawasulInternalAssessmentEntry ON (tawasulInternalAssessmentEntry.tawasulPersonIDStudent=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulInternalAssessmentColumn ON (tawasulInternalAssessmentEntry.tawasulInternalAssessmentColumnID=tawasulInternalAssessmentColumn.tawasulInternalAssessmentColumnID) 
                JOIN tawasulCourseClassPerson ON (tawasulInternalAssessmentColumn.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) 
                JOIN tawasulCourseClass ON (tawasulCourseClassPerson.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) 
                JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) 
                WHERE tawasulStudentEnrolment.tawasulStudentEnrolmentID=:tawasulStudentEnrolmentID
                AND tawasulCourse.tawasulSchoolYearID=tawasulStudentEnrolment.tawasulSchoolYearID
                AND tawasulCourseClassPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID
                AND tawasulInternalAssessmentColumn.complete='Y'
                AND tawasulInternalAssessmentColumn.completeDate<=:today 
                ORDER BY tawasulInternalAssessmentColumn.completeDate DESC, tawasulCourse.nameShort, tawasulCourseClass.nameShort";

        return $this->db()->select($sql, $data)->fetchAll();
    }
}
