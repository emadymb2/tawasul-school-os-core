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
 * @version v25
 * @since   v16
 */
class TimetableGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulTT';
    private static $primaryKey = 'tawasulTTID';

    public function selectTimetablesBySchoolYear($tawasulSchoolYearID) 
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT tawasulTTID, tawasulTT.tawasulSchoolYearID, tawasulTT.name, tawasulTT.nameShort, tawasulTT.active, GROUP_CONCAT(tawasulYearGroup.nameShort ORDER BY tawasulYearGroup.sequenceNumber SEPARATOR ', ') as yearGroups
                FROM tawasulTT 
                LEFT JOIN tawasulYearGroup ON (FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulTT.tawasulYearGroupIDList))
                WHERE tawasulTT.tawasulSchoolYearID=:tawasulSchoolYearID 
                GROUP BY tawasulTT.tawasulTTID
                ORDER BY tawasulTT.name";

        return $this->db()->select($sql, $data);
    }

    public function selectActiveTimetables($tawasulSchoolYearID) 
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT tawasulTT.tawasulTTID, tawasulTT.name
                FROM tawasulTT 
                WHERE tawasulTT.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulTT.active='Y'
                GROUP BY tawasulTT.tawasulTTID
                ORDER BY tawasulTT.name";

        return $this->db()->select($sql, $data);
    }

    public function selectClassesByTimetable($tawasulTTID)
    {
        $data = ['tawasulTTID' => $tawasulTTID];
        $sql = "SELECT tawasulCourseClass.tawasulCourseClassID AS value, CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) AS name 
                FROM tawasulTT
                JOIN tawasulCourse ON (tawasulCourse.tawasulSchoolYearID=tawasulTT.tawasulSchoolYearID)
                JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                JOIN tawasulYearGroup ON (FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulCourse.tawasulYearGroupIDList))
                WHERE tawasulTT.tawasulTTID=:tawasulTTID
                AND FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulTT.tawasulYearGroupIDList)
                GROUP BY tawasulCourseClass.tawasulCourseClassID
                ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    public function selectTimetablesByClass($tawasulCourseClassID)
    {
        $data = ['tawasulCourseClassID' => $tawasulCourseClassID];
        $sql = "SELECT DISTINCT tawasulTTDay.tawasulTTID
                FROM tawasulTTDayRowClass
                JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
                WHERE tawasulTTDayRowClass.tawasulCourseClassID=:tawasulCourseClassID
                GROUP BY tawasulTTDay.tawasulTTID";

        return $this->db()->select($sql, $data);
    }

    public function getNonTimetabledYearGroups($tawasulSchoolYearID, $tawasulTTID = null)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulTTID' => $tawasulTTID);
        $sql = "SELECT tawasulYearGroup.tawasulYearGroupID, tawasulYearGroup.name
                FROM tawasulYearGroup
                LEFT JOIN tawasulTT ON (FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulTT.tawasulYearGroupIDList) AND tawasulTT.tawasulSchoolYearID=:tawasulSchoolYearID AND (tawasulTT.active='Y' OR tawasulTT.tawasulTTID=:tawasulTTID))
                WHERE tawasulTT.tawasulTTID IS NULL OR tawasulTT.tawasulTTID=:tawasulTTID
                ORDER BY tawasulYearGroup.sequenceNumber";

        return $this->db()->select($sql, $data)->fetchKeyPair();
    }

    public function getTTByID($tawasulTTID)
    {
        $data = array('tawasulTTID' => $tawasulTTID);
        $sql = "SELECT tawasulTT.tawasulTTID, tawasulTT.name, tawasulTT.nameShort, tawasulTT.nameShortDisplay, tawasulTT.active, tawasulTT.tawasulYearGroupIDList, tawasulSchoolYear.name as schoolYear
                FROM tawasulTT 
                JOIN tawasulSchoolYear ON (tawasulSchoolYear.tawasulSchoolYearID=tawasulTT.tawasulSchoolYearID)
                WHERE tawasulTT.tawasulTTID=:tawasulTTID";

        return $this->db()->selectOne($sql, $data);
    }
}
