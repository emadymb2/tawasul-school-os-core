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
 * @version v16
 * @since   v16
 */
class FacilityGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulSpace';
    private static $primaryKey = 'tawasulSpaceID';

    private static $searchableColumns = ['name', 'type'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryFacilities(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulSpaceID', 'name', 'type', 'active', 'capacity', 'computer', 'computerStudent', 'projector', 'tv', 'dvd', 'hifi', 'speakers', 'iwb', 'phoneInternal', 'phoneExternal'
            ]);
        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('tawasulSpace.active = :active')
                    ->bindValue('active', $active);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectFacilityInfoByName($tawasulSpaceNameList)
    {
        $tawasulSpaceNameList = is_array($tawasulSpaceNameList) ? implode(',', $tawasulSpaceNameList) : $tawasulSpaceNameList;

        $data = ['tawasulSpaceNameList' => $tawasulSpaceNameList];
        $sql = "SELECT * FROM tawasulSpace 
                WHERE FIND_IN_SET(name, :tawasulSpaceNameList) 
                ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    public function selectFacilityInUseByDateAndTime($tawasulSpaceID, $date, $timeStart, $timeEnd)
    {
        $data = ['tawasulSpaceID' => $tawasulSpaceID, 'date' => $date, 'timeStart' => $timeStart, 'timeEnd' => $timeEnd];
        $sql = "(
                SELECT CONCAT(tawasulPerson.preferredName, ' ', tawasulPerson.surname) AS name
                FROM tawasulTTSpaceBooking 
                JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID = tawasulTTSpaceBooking.tawasulPersonID )
                WHERE foreignKey='tawasulSpaceID' 
                AND foreignKeyID=:tawasulSpaceID 
                AND date=:date 
                AND (
                    (tawasulTTSpaceBooking.timeStart >= :timeStart AND tawasulTTSpaceBooking.timeStart < :timeEnd)
                    OR (:timeStart >= tawasulTTSpaceBooking.timeStart AND :timeStart < tawasulTTSpaceBooking.timeEnd)
                    OR (:timeStart = tawasulTTSpaceBooking.timeStart AND :timeEnd = tawasulTTSpaceBooking.timeEnd)
                )
            )
            UNION ALL
            (
                SELECT CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as name
                FROM tawasulTTSpaceChange
                JOIN tawasulTTDayRowClass ON (tawasulTTDayRowClass.tawasulTTDayRowClassID=tawasulTTSpaceChange.tawasulTTDayRowClassID)
                JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID)
                JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulTTDayRowClass.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                WHERE tawasulTTSpaceChange.date=:date
                AND tawasulTTSpaceChange.tawasulSpaceID=:tawasulSpaceID
                AND (
                    (tawasulTTColumnRow.timeStart >= :timeStart AND tawasulTTColumnRow.timeStart < :timeEnd)
                    OR (:timeStart >= tawasulTTColumnRow.timeStart AND :timeStart < tawasulTTColumnRow.timeEnd)
                    OR (:timeStart = tawasulTTColumnRow.timeStart AND :timeEnd = tawasulTTColumnRow.timeEnd)
                )
            )
            UNION ALL
            (
                SELECT CONCAT(tawasulCourse.nameShort, '.', tawasulCourseClass.nameShort) as name
                FROM tawasulTTDayRowClass
                JOIN tawasulTTColumnRow ON (tawasulTTColumnRow.tawasulTTColumnRowID=tawasulTTDayRowClass.tawasulTTColumnRowID)
                JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayRowClass.tawasulTTDayID)
                JOIN tawasulTTDayDate ON (tawasulTTDayDate.tawasulTTDayID=tawasulTTDay.tawasulTTDayID)
                JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulTTDayRowClass.tawasulCourseClassID)
                JOIN tawasulCourse ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID)
                WHERE tawasulTTDayDate.date=:date
                AND tawasulTTDayRowClass.tawasulSpaceID=:tawasulSpaceID
                AND (
                    (tawasulTTColumnRow.timeStart >= :timeStart AND tawasulTTColumnRow.timeStart < :timeEnd)
                    OR (:timeStart >= tawasulTTColumnRow.timeStart AND :timeStart < tawasulTTColumnRow.timeEnd)
                    OR (:timeStart = tawasulTTColumnRow.timeStart AND :timeEnd = tawasulTTColumnRow.timeEnd)
                )
                AND (SELECT tawasulTTSpaceChangeID FROM tawasulTTSpaceChange AS roomReleased JOIN tawasulTTDayRowClass AS roomTT ON (roomTT.tawasulTTDayRowClassID=roomReleased.tawasulTTDayRowClassID) WHERE roomReleased.date=:date AND roomReleased.tawasulTTDayRowClassID=tawasulTTDayRowClass.tawasulTTDayRowClassID AND roomTT.tawasulSpaceID=:tawasulSpaceID LIMIT 1) IS NULL
            )";

        return $this->db()->select($sql, $data);
    }
}
