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

namespace TawasulOS\Domain\School;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * School Year Special Day Gateway
 *
 * @version v25
 * @since   v25
 */
class SchoolYearSpecialDayGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulSchoolYearSpecialDay';
    private static $primaryKey = 'tawasulSchoolYearSpecialDayID';

    public function getSpecialDayByDate($date)
    {
        $data = ['date' => $date];
        $sql = "SELECT * FROM tawasulSchoolYearSpecialDay WHERE date=:date";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectSpecialDaysByDateRange($dateStart, $dateEnd)
    {
        $data = ['dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT date as groupBy, tawasulSchoolYearSpecialDay.* FROM tawasulSchoolYearSpecialDay WHERE date BETWEEN :dateStart AND :dateEnd";

        return $this->db()->select($sql, $data);
    }

    public function getIsStudentOffTimetableByDate($tawasulSchoolYearID, $tawasulPersonID, $date)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID, 'date' => $date];
        $sql = "SELECT tawasulSchoolYearSpecialDay.tawasulSchoolYearSpecialDayID as offTimetable, tawasulSchoolYearSpecialDay.name
            FROM tawasulPerson AS student
            JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=student.tawasulPersonID ) 
            JOIN tawasulSchoolYearSpecialDay ON (tawasulSchoolYearSpecialDay.date=:date AND tawasulSchoolYearSpecialDay.type='Off Timetable')
            WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
            AND student.tawasulPersonID=:tawasulPersonID 
            AND student.status='Full' 
            AND (student.dateStart IS NULL OR student.dateStart<=:date) 
            AND (student.dateEnd IS NULL OR student.dateEnd>=:date)
            AND (FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, tawasulSchoolYearSpecialDay.tawasulYearGroupIDList)
            OR FIND_IN_SET(tawasulStudentEnrolment.tawasulFormGroupID, tawasulSchoolYearSpecialDay.tawasulFormGroupIDList))";

        return $this->db()->selectOne($sql, $data);
    }

    public function getIsFormGroupOffTimetableByDate($tawasulSchoolYearID, $tawasulFormGroupID, $date)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulFormGroupID' => $tawasulFormGroupID, 'date' => $date];
        $sql = "SELECT (CASE WHEN count(*) = 0 THEN 1 ELSE 0 END) as offTimetable 
            FROM tawasulPerson AS student
            JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=student.tawasulPersonID ) 
            LEFT JOIN tawasulSchoolYearSpecialDay ON (tawasulSchoolYearSpecialDay.date=:date AND tawasulSchoolYearSpecialDay.type='Off Timetable')
            WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
            AND tawasulStudentEnrolment.tawasulFormGroupID=:tawasulFormGroupID 
            AND student.status='Full' 
            AND (student.dateStart IS NULL OR student.dateStart<=:date) 
            AND (student.dateEnd IS NULL OR student.dateEnd>=:date) 
            AND (tawasulSchoolYearSpecialDayID IS NULL OR NOT FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, tawasulSchoolYearSpecialDay.tawasulYearGroupIDList) )
            AND (tawasulSchoolYearSpecialDayID IS NULL OR NOT FIND_IN_SET(tawasulStudentEnrolment.tawasulFormGroupID, tawasulSchoolYearSpecialDay.tawasulFormGroupIDList))";

        return $this->db()->selectOne($sql, $data);
    }

    public function getIsClassOffTimetableByDate($tawasulSchoolYearID, $tawasulCourseClassID, $date)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $date];
        $sql = "SELECT COUNT(*) as studentTotal, COUNT(CASE WHEN (tawasulSchoolYearSpecialDayID IS NULL OR NOT FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, tawasulSchoolYearSpecialDay.tawasulYearGroupIDList) ) AND (tawasulSchoolYearSpecialDayID IS NULL OR NOT FIND_IN_SET(tawasulStudentEnrolment.tawasulFormGroupID, tawasulSchoolYearSpecialDay.tawasulFormGroupIDList)) THEN student.tawasulPersonID ELSE NULL END) as studentCount
            FROM tawasulCourseClassPerson 
            JOIN tawasulPerson AS student ON (tawasulCourseClassPerson.tawasulPersonID=student.tawasulPersonID) 
            JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=student.tawasulPersonID) 
            LEFT JOIN tawasulSchoolYearSpecialDay ON (tawasulSchoolYearSpecialDay.date=:date AND tawasulSchoolYearSpecialDay.type='Off Timetable')
            WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
            AND tawasulCourseClassPerson.role='Student' 
            AND student.status='Full' 
            AND tawasulCourseClassPerson.tawasulCourseClassID=:tawasulCourseClassID 
            AND (student.dateStart IS NULL OR student.dateStart<=:date) 
            AND (student.dateEnd IS NULL OR student.dateEnd>=:date)";

        $result = $this->db()->selectOne($sql, $data);

        return !empty($result) && ($result['studentTotal'] > 0 && $result['studentCount'] <= 0);
    }

    public function selectOffTimetableStudentsByClass($tawasulSchoolYearID, $tawasulCourseClassID, $date)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $date];
        $sql = "SELECT tawasulCourseClassPerson.tawasulPersonID, tawasulSchoolYearSpecialDay.name
            FROM tawasulCourseClassPerson 
            JOIN tawasulPerson AS student ON (tawasulCourseClassPerson.tawasulPersonID=student.tawasulPersonID) 
            JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=student.tawasulPersonID) 
            JOIN tawasulSchoolYearSpecialDay ON (tawasulSchoolYearSpecialDay.date=:date AND tawasulSchoolYearSpecialDay.type='Off Timetable')
            WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
            AND tawasulCourseClassPerson.role='Student' 
            AND student.status='Full' 
            AND tawasulCourseClassPerson.tawasulCourseClassID=:tawasulCourseClassID 
            AND (student.dateStart IS NULL OR student.dateStart<=:date) 
            AND (student.dateEnd IS NULL OR student.dateEnd>=:date)
            AND (FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, tawasulSchoolYearSpecialDay.tawasulYearGroupIDList) OR FIND_IN_SET(tawasulStudentEnrolment.tawasulFormGroupID, tawasulSchoolYearSpecialDay.tawasulFormGroupIDList))";

        return $this->db()->select($sql, $data);
    }
}
