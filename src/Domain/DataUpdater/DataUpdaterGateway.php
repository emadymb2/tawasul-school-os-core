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

namespace TawasulOS\Domain\DataUpdater;

use TawasulOS\Domain\Gateway;
use TawasulOS\Domain\System\SettingGateway;

/**
 * Data Updater Gateway
 *
 * @version v16
 * @since   v16
 */
class DataUpdaterGateway extends Gateway
{
    /**
     * Gets a list of users this person can update data for, checking by family. Always returns the user themself even if not in a family.
     *
     * @param string $tawasulPersonID
     * @return \PDOStatement
     */
    public function selectUpdatableUsersByPerson($tawasulPersonID)
    {
        $data = array('tawasulPersonID' => $tawasulPersonID);
        $sql = "
        (SELECT GROUP_CONCAT(tawasulFamily.tawasulFamilyID ORDER BY tawasulFamily.name SEPARATOR ',') as tawasulFamilyID, tawasulPerson.surname, tawasulPerson.preferredName, tawasulPerson.image_240, tawasulPerson.tawasulPersonID, tawasulPerson.dateStart, 0 as sequenceNumber
            FROM tawasulPerson
            LEFT JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID)
            LEFT JOIN tawasulFamily ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
            WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID AND tawasulPerson.status='Full' GROUP BY tawasulPerson.tawasulPersonID)
        UNION ALL
        (SELECT tawasulFamilyAdult.tawasulFamilyID, child.surname, child.preferredName, child.image_240, child.tawasulPersonID, child.dateStart, 1 as sequenceNumber
            FROM tawasulFamilyAdult
            JOIN tawasulFamily ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
            JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
            JOIN tawasulPerson as child ON (tawasulFamilyChild.tawasulPersonID=child.tawasulPersonID)
            WHERE tawasulFamilyAdult.tawasulPersonID=:tawasulPersonID
            AND tawasulFamilyAdult.childDataAccess='Y' AND child.status='Full')
        UNION ALL
        (SELECT tawasulFamily.tawasulFamilyID, adult.surname, adult.preferredName, adult.image_240, adult.tawasulPersonID, adult.dateStart, 2 as sequenceNumber
            FROM tawasulFamilyAdult
            JOIN tawasulFamily ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
            JOIN tawasulFamilyAdult as familyAdult ON (familyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID AND familyAdult.tawasulPersonID<>:tawasulPersonID)
            JOIN tawasulPerson as adult ON (familyAdult.tawasulPersonID=adult.tawasulPersonID)
            WHERE tawasulFamilyAdult.tawasulPersonID=:tawasulPersonID AND adult.status='Full')
        ORDER BY sequenceNumber, surname, preferredName
        ";

        return $this->db()->select($sql, $data);
    }

    /**
     * Gets a list of data updates and the last updated timestamp for a given user.
     *
     * @param string $tawasulPersonID
     * @return \PDOStatement
     */
    public function selectDataUpdatesByPerson($tawasulPersonID, $tawasulPersonIDSource = '')
    {
        $data = array('tawasulPersonID' => $tawasulPersonID, 'tawasulPersonIDSource' => $tawasulPersonIDSource);
        $sql = "
        (SELECT 'Personal' as type, tawasulPerson.tawasulPersonID as id, 'tawasulPersonID' as idType, IFNULL(timestamp, 0) as lastUpdated, '' as name
            FROM tawasulPerson
            LEFT JOIN tawasulPersonUpdate ON (tawasulPersonUpdate.tawasulPersonID=tawasulPerson.tawasulPersonID)
            WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID ORDER BY timestamp DESC LIMIT 1)
        UNION ALL
        (SELECT 'Medical' as type, tawasulPerson.tawasulPersonID as id, 'tawasulPersonID' as idType, IFNULL(timestamp, 0) as lastUpdated, '' as name
            FROM tawasulPerson
            JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll))
            LEFT JOIN tawasulPersonMedicalUpdate ON (tawasulPersonMedicalUpdate.tawasulPersonID=tawasulPerson.tawasulPersonID)
            WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID AND tawasulRole.category='Student'
            ORDER BY timestamp DESC LIMIT 1)
        UNION ALL
        (SELECT 'Finance' as type, tawasulFinanceInvoicee.tawasulFinanceInvoiceeID as id, 'tawasulFinanceInvoiceeID' as idType, IFNULL(timestamp, 0) as lastUpdated, '' as name
            FROM tawasulPerson
            JOIN tawasulFinanceInvoicee ON (tawasulFinanceInvoicee.tawasulPersonID=tawasulPerson.tawasulPersonID)
            LEFT JOIN tawasulFinanceInvoiceeUpdate ON (tawasulFinanceInvoiceeUpdate.tawasulFinanceInvoiceeID=tawasulFinanceInvoicee.tawasulFinanceInvoiceeID)
            WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID
            ORDER BY timestamp DESC LIMIT 1)
        UNION ALL
        (SELECT 'Family' as type, tawasulFamilyAdult.tawasulFamilyID as id, 'tawasulFamilyID' as idType, IFNULL(timestamp, 0) as lastUpdated, tawasulFamily.name
            FROM tawasulFamilyAdult
            JOIN tawasulFamily ON (tawasulFamily.tawasulFamilyID=tawasulFamilyAdult.tawasulFamilyID)
            LEFT JOIN tawasulFamilyUpdate ON (tawasulFamilyUpdate.tawasulFamilyID=tawasulFamilyAdult.tawasulFamilyID)
            WHERE tawasulFamilyAdult.tawasulPersonID=:tawasulPersonID ORDER BY timestamp DESC LIMIT 1)
        UNION ALL
        (SELECT 'Family' as type, tawasulFamilyChild.tawasulFamilyID as id, 'tawasulFamilyID' as idType, IFNULL(timestamp, 0) as lastUpdated, tawasulFamily.name
            FROM tawasulFamilyChild
            JOIN tawasulFamily ON (tawasulFamily.tawasulFamilyID=tawasulFamilyChild.tawasulFamilyID)
            JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
            LEFT JOIN tawasulFamilyUpdate ON (tawasulFamilyUpdate.tawasulFamilyID=tawasulFamilyChild.tawasulFamilyID)
            WHERE tawasulFamilyChild.tawasulPersonID=:tawasulPersonID AND tawasulFamilyAdult.tawasulPersonID=:tawasulPersonIDSource
            ORDER BY timestamp DESC LIMIT 1)
        UNION ALL
        (SELECT 'Staff' as type, tawasulPerson.tawasulPersonID as id, 'tawasulPersonID' as idType, IFNULL(tawasulStaffUpdate.timestamp, 0) as lastUpdated, '' as name
            FROM tawasulPerson
            JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll))
            LEFT JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID)
            LEFT JOIN tawasulStaffUpdate ON (tawasulStaffUpdate.tawasulStaffID=tawasulStaff.tawasulStaffID)
            WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID AND tawasulRole.category='Staff'
            ORDER BY timestamp DESC LIMIT 1)
        ";

        return $this->db()->select($sql, $data);
    }

    public function countAllRequiredUpdatesByPerson($tawasulPersonID)
    {
        $updatablePeople = $this->selectUpdatableUsersByPerson($tawasulPersonID);

        if ($updatablePeople->rowCount() == 0) return 0;

        global $container;

        $cutoffDate = $mainMenuCategoryOrder = $this->db()->selectOne("SELECT value FROM tawasulSetting WHERE scope='Data Updater' AND name='cutoffDate'");
        $requiredUpdatesByType = $this->db()->selectOne("SELECT value FROM tawasulSetting WHERE scope='Data Updater' AND name='requiredUpdatesByType'");
        $requiredUpdatesByType = explode(',', $requiredUpdatesByType);

        if (empty($requiredUpdatesByType) || empty($cutoffDate)) return 0;

        $count = 0;

        // Loop over each updatable person to look for required updates
        foreach ($updatablePeople as $person) {
            $dataUpdatesByType = $this->selectDataUpdatesByPerson($person['tawasulPersonID'], $tawasulPersonID);

            if (!$this->db()->getQuerySuccess()) return 0;

            $dataUpdatesByType = $dataUpdatesByType->fetchGrouped();

            foreach ($requiredUpdatesByType as $type) {
                // Skip data update types not applicable to this user
                if (empty($dataUpdatesByType[$type])) continue;

                // Loop over each type of data update and check the last update
                foreach ($dataUpdatesByType[$type] as $dataUpdate) {
                    if (empty($dataUpdate['lastUpdated']) || $dataUpdate['lastUpdated'] < $cutoffDate) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }
}
