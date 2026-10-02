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
class HouseGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulHouse';
    private static $primaryKey = 'tawasulHouseID';

    private static $searchableColumns = ['name', 'nameShort'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryHouses(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulHouseID', 'name', 'nameShort', 'logo'
            ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryStudentHouseCountByYearGroup(QueryCriteria $criteria, $tawasulSchoolYearID, $includeUpcoming = false)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulYearGroup.tawasulYearGroupID',
                'tawasulYearGroup.name as yearGroupName',
                'tawasulHouse.name AS house',
                'tawasulHouse.tawasulHouseID',
                "count(tawasulStudentEnrolment.tawasulPersonID) AS total",
                "count(CASE WHEN tawasulPerson.gender='M' THEN tawasulStudentEnrolment.tawasulPersonID END) as totalMale",
                "count(CASE WHEN tawasulPerson.gender='F' THEN tawasulStudentEnrolment.tawasulPersonID END) as totalFemale",
            ]);

            if ($includeUpcoming == 'Y') {
                $query->leftJoin('tawasulPerson', "tawasulPerson.tawasulHouseID=tawasulHouse.tawasulHouseID
                    AND (tawasulPerson.status='Full' OR tawasulPerson.status='Expected')
                    AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today)");
            } else {
                $query->leftJoin('tawasulPerson', "tawasulPerson.tawasulHouseID=tawasulHouse.tawasulHouseID
                    AND tawasulPerson.status='Full'
                    AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:today)
                    AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today)");
            }

            $query
            ->leftJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID
                        AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->leftJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID')
            ->groupBy(['tawasulYearGroup.tawasulYearGroupID', 'tawasulHouse.tawasulHouseID'])
            ->having('total > 0')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->bindValue('today', date('Y-m-d'));

            

        return $this->runQuery($query, $criteria);
    }

    public function selectAssignedHouseByGender($tawasulSchoolYearID, $tawasulYearGroupID, $gender)
    {
        $select = $this
            ->newSelect()
            ->cols(['tawasulHouse.name AS house', 'tawasulHouse.tawasulHouseID', "count(DISTINCT tawasulStudentEnrolment.tawasulPersonID) AS count"])
            ->from($this->getTableName())
            ->leftJoin('tawasulPerson', "tawasulPerson.tawasulHouseID=tawasulHouse.tawasulHouseID AND gender=:gender AND status='Full'")
            ->leftJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID
                AND tawasulSchoolYearID>=:tawasulSchoolYearID
                AND tawasulYearGroupID=:tawasulYearGroupID')
            ->where('tawasulHouse.tawasulHouseID IS NOT NULL')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->bindValue('tawasulYearGroupID', $tawasulYearGroupID)
            ->bindValue('gender', $gender)
            ->groupBy(['house', 'tawasulHouse.tawasulHouseID'])
            ->orderBy(['count', 'RAND()', 'tawasulHouse.tawasulHouseID']);

        return $this->runSelect($select);
    }

    public function selectAllHouses()
    {
        $sql = "SELECT tawasulHouseID as value, name FROM tawasulHouse ORDER BY name";

        return $this->db()->select($sql);
    }

    public function selectHousesByPerson($tawasulPersonID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulHouse.tawasulHouseID as value, name FROM tawasulHouse JOIN tawasulPerson ON (tawasulHouse.tawasulHouseID=tawasulPerson.tawasulHouseID) WHERE tawasulPersonID=:tawasulPersonID ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    /**
     * Find an existing house assignment for a student by family
     *
     * @param int $tawasulFamilyID
     * @param int $tawasulPersonIDStudent Person to exclude when checking siblings
     * @return array|false
     */
    public function selectExistingHouseByFamilyID($tawasulFamilyID, $tawasulPersonIDStudent)
    {
        $data = ['tawasulFamilyID' => $tawasulFamilyID, 'tawasulPersonIDStudent' => $tawasulPersonIDStudent];

        $sql = "SELECT tawasulHouseID, house FROM (
                    SELECT tawasulHouse.tawasulHouseID, tawasulHouse.name AS house, 1 AS priority, tawasulFamilyAdult.contactPriority
                    FROM tawasulFamilyAdult
                    JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID)
                    JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID)
                    JOIN tawasulHouse ON (tawasulPerson.tawasulHouseID=tawasulHouse.tawasulHouseID)
                    WHERE tawasulFamilyAdult.tawasulFamilyID=:tawasulFamilyID
                    AND tawasulPerson.status='Full'
                    AND tawasulRole.category='Staff'
                    UNION ALL
                    SELECT tawasulHouse.tawasulHouseID, tawasulHouse.name AS house, 2 AS priority, 0 AS contactPriority
                    FROM tawasulFamilyChild
                    JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID)
                    JOIN tawasulHouse ON (tawasulPerson.tawasulHouseID=tawasulHouse.tawasulHouseID)
                    WHERE tawasulFamilyChild.tawasulFamilyID=:tawasulFamilyID
                    AND tawasulPerson.tawasulPersonID!=:tawasulPersonIDStudent
                    AND tawasulPerson.status='Full'
                ) AS familyHouse
                ORDER BY priority, contactPriority
                LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }
}
