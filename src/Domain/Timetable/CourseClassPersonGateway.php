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

namespace TawasulOS\Domain\Timetable;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v27
 * @since   v27
 */
class CourseClassPersonGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulCourseClassPerson';
    private static $primaryKey = 'tawasulCourseClassPersonID';

    private static $searchableColumns = ['tawasulCourseClassPerson.tawasulPersonID', 'tawasulCourseClassPerson.tawasulCourseClassID'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */


    public function selectStudentsByClass($tawasulCourseClassID, $date = null)
    {
        $today = $date ?? date('Y-m-d');
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'today' => $today];
        $sql = "SELECT tawasulCourseClassPerson.role, tawasulPerson.tawasulPersonID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulPerson.image_240, tawasulPerson.dob, tawasulPerson.email, tawasulPerson.studentID, tawasulFormGroup.nameShort as formGroup FROM tawasulCourseClassPerson JOIN tawasulPerson ON tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)";

        if (!empty($date)) {
            $sql .= " LEFT JOIN (SELECT tawasulTTDayRowClass.tawasulCourseClassID, tawasulTTDayRowClass.tawasulTTDayRowClassID FROM tawasulTTDayDate JOIN tawasulTTDayRowClass ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID) WHERE tawasulTTDayDate.date=:today) AS tawasulTTDayRowClassSubset ON (tawasulTTDayRowClassSubset.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID) LEFT JOIN tawasulTTDayRowClassException ON (tawasulTTDayRowClassException.tawasulTTDayRowClassID=tawasulTTDayRowClassSubset.tawasulTTDayRowClassID AND tawasulTTDayRowClassException.tawasulPersonID=tawasulCourseClassPerson.tawasulPersonID)";
        }

        $sql .= " WHERE tawasulCourseClassPerson.tawasulCourseClassID=:tawasulCourseClassID AND status='Full' AND tawasulCourseClassPerson.role='Student' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL OR dateEnd>=:today)";

        if (!empty($date)) {
            $sql .= " GROUP BY tawasulCourseClassPerson.tawasulPersonID HAVING COUNT(tawasulTTDayRowClassExceptionID) = 0";
        }

        $sql .= " ORDER BY surname, preferredName, role DESC";

        return $this->db()->select($sql, $data);
    }

    public function selectTeachersByClass($tawasulCourseClassID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'today' => date('Y-m-d')];
        $sql = "SELECT tawasulPerson.tawasulPersonID as groupBy, tawasulCourseClassPerson.role, tawasulPerson.tawasulPersonID, tawasulPerson.title, tawasulPerson.surname, tawasulPerson.preferredName, tawasulPerson.image_240, tawasulPerson.email
            FROM tawasulCourseClassPerson 
            JOIN tawasulPerson ON tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID
            WHERE tawasulCourseClassPerson.tawasulCourseClassID=:tawasulCourseClassID 
            AND tawasulPerson.status='Full'
            AND tawasulCourseClassPerson.role='Teacher'
            AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:today) AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today)
            ORDER BY surname, preferredName, role DESC";

        return $this->db()->select($sql, $data);
    }
}
