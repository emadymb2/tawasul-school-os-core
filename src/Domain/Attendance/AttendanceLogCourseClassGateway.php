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

namespace TawasulOS\Domain\Attendance;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v21
 * @since   v21
 */
class AttendanceLogCourseClassGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulAttendanceLogCourseClass';
    private static $primaryKey = 'tawasulAttendanceLogCourseClassID';

    private static $searchableColumns = [''];

    public function selectClassAttendanceLogsByDate($tawasulCourseClassID, $date)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $date];
        $sql = "SELECT * 
                FROM tawasulAttendanceLogCourseClass, tawasulPerson
                WHERE tawasulAttendanceLogCourseClass.tawasulPersonIDTaker=tawasulPerson.tawasulPersonID 
                AND tawasulCourseClassID=:tawasulCourseClassID 
                AND date=:date 
                ORDER BY timestampTaken";

        return $this->db()->select($sql, $data);
    }

    public function selectAttendanceLogByClassAndDate($tawasulCourseClassID, $date, $tawasulTTDayRowClassID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID, 'date' => $date, 'tawasulTTDayRowClassID' => $tawasulTTDayRowClassID];
        $sql = "SELECT * FROM tawasulAttendanceLogCourseClass, tawasulPerson WHERE tawasulAttendanceLogCourseClass.tawasulPersonIDTaker=tawasulPerson.tawasulPersonID AND tawasulCourseClassID=:tawasulCourseClassID AND date LIKE :date AND (tawasulTTDayRowClassID=:tawasulTTDayRowClassID OR tawasulTTDayRowClassID IS NULL) ORDER BY timestampTaken";

        return $this->db()->select($sql, $data);
    }
}
