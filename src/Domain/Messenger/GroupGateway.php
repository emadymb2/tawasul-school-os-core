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

namespace TawasulOS\Domain\Messenger;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * Group Gateway
 *
 * @version v16
 * @since   v16
 */
class GroupGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulGroup';
    private static $primaryKey = 'tawasulGroupID';
    private static $searchableColumns = ['tawasulGroup.name'];
    
    /**
     * Queries the list of groups for the messenger Manage Groups page.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryGroups(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonIDOwner = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulGroup.tawasulGroupID', 'tawasulGroup.name', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'COUNT(DISTINCT tawasulGroupPersonID) as count', 'tawasulSchoolYear.name as schoolYear'
            ])
            ->innerJoin('tawasulSchoolYear', 'tawasulSchoolYear.tawasulSchoolYearID=tawasulGroup.tawasulSchoolYearID')
            ->leftJoin('tawasulGroupPerson', 'tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID')
            ->leftJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulGroup.tawasulPersonIDOwner')
            ->groupBy(['tawasulGroup.tawasulGroupID']);
        
        $query->where('tawasulGroup.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);
        if (!empty($tawasulPersonIDOwner)) {
            $query->where('tawasulGroup.tawasulPersonIDOwner = :tawasulPersonIDOwner')
                  ->bindValue('tawasulPersonIDOwner', $tawasulPersonIDOwner);
        }
        
        return $this->runQuery($query, $criteria);
    }

    /**
     * Queries the group members based on group ID.
     * @param QueryCriteria $criteria
     * @param string $tawasulGroupID
     * @return DataSet
     */
    public function queryGroupMembers(QueryCriteria $criteria, $tawasulGroupID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulGroupPerson')
            ->cols(['tawasulGroupPerson.tawasulGroupID', 'tawasulGroupPerson.tawasulPersonID', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.email', 'tawasulRole.category as roleCategory'])
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulGroupPerson.tawasulPersonID')
            ->innerJoin('tawasulRole', 'tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary')
            ->where('tawasulGroupPerson.tawasulGroupID = :tawasulGroupID')
            ->bindValue('tawasulGroupID', $tawasulGroupID);

        return $this->runQuery($query, $criteria);
    }

    public function selectGroupsBySchoolYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulGroup.tawasulGroupID as value, tawasulGroup.name 
                FROM tawasulGroup 
                WHERE tawasulSchoolYearID=:tawasulSchoolYearID 
                ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    public function selectGroupsByPersonAndOwner($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "(SELECT tawasulGroup.tawasulGroupID as value, tawasulGroup.name FROM tawasulGroup WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonIDOwner=:tawasulPersonID ORDER BY name) UNION (SELECT tawasulGroup.tawasulGroupID as value, tawasulGroup.name FROM tawasulGroup JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulGroupID=tawasulGroup.tawasulGroupID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulPersonID=:tawasulPersonID) ORDER BY name";
        
        return $this->db()->select($sql, $data);
    }

    public function selectGroupByID($tawasulGroupID)
    {
        $data = array('tawasulGroupID' => $tawasulGroupID);
        $sql = "SELECT * FROM tawasulGroup WHERE tawasulGroupID=:tawasulGroupID";

        return $this->db()->select($sql, $data);
    }

    public function selectGroupByIDAndOwner($tawasulGroupID, $tawasulPersonIDOwner)
    {
        $data = array('tawasulGroupID' => $tawasulGroupID, 'tawasulPersonIDOwner' => $tawasulPersonIDOwner);
        $sql = "SELECT * FROM tawasulGroup WHERE tawasulGroupID=:tawasulGroupID AND tawasulPersonIDOwner=:tawasulPersonIDOwner";

        return $this->db()->select($sql, $data);
    }

    public function selectGroupsByIDList($tawasulGroupID)
    {
        $tawasulGroupIDList = is_array($tawasulGroupID)? $tawasulGroupID : [$tawasulGroupID];

        $data = array('tawasulGroupIDList' => implode(',', $tawasulGroupIDList));
        $sql = "SELECT tawasulGroupID, name FROM tawasulGroup WHERE FIND_IN_SET(tawasulGroupID, :tawasulGroupIDList) ORDER BY FIND_IN_SET(tawasulGroupID, :tawasulGroupIDList)";

        return $this->db()->select($sql, $data);
    }

    public function selectGroupPersonByID($tawasulGroupID, $tawasulPersonID)
    {
        $data = array('tawasulGroupID' => $tawasulGroupID, 'tawasulPersonID' => $tawasulPersonID);
        $sql = "SELECT * FROM tawasulGroupPerson WHERE tawasulGroupID=:tawasulGroupID AND tawasulPersonID=:tawasulPersonID";

        return $this->db()->select($sql, $data);
    }

    public function selectPersonIDsByGroup($tawasulGroupID)
    {
        $data = array('tawasulGroupID' => $tawasulGroupID);
        $sql = "SELECT tawasulGroupPerson.tawasulPersonID FROM tawasulGroupPerson WHERE tawasulGroupID=:tawasulGroupID";

        return $this->db()->select($sql, $data);
    }

    public function insertGroup(array $data)
    {
        $sql = "INSERT INTO tawasulGroup SET tawasulPersonIDOwner=:tawasulPersonIDOwner, tawasulSchoolYearID=:tawasulSchoolYearID, name=:name, timestampCreated=NOW()";

        return $this->db()->insert($sql, $data);
    }

    public function insertGroupPerson(array $data)
    {
        $sql = "INSERT INTO tawasulGroupPerson SET tawasulGroupID=:tawasulGroupID, tawasulPersonID=:tawasulPersonID ON DUPLICATE KEY UPDATE tawasulPersonID=:tawasulPersonID";

        return $this->db()->insert($sql, $data);
    }

    public function updateGroup(array $data)
    {
        $sql = "UPDATE tawasulGroup SET name=:name WHERE tawasulGroupID=:tawasulGroupID";

        return $this->db()->update($sql, $data);
    }

    public function deleteGroup($tawasulGroupID)
    {
        $data = array('tawasulGroupID' => $tawasulGroupID);
        $sql = "DELETE FROM tawasulGroup WHERE tawasulGroupID=:tawasulGroupID";

        return $this->db()->delete($sql, $data);
    }

    public function deleteGroupPerson($tawasulGroupID, $tawasulPersonID)
    {
        $data = array('tawasulGroupID' => $tawasulGroupID, 'tawasulPersonID' => $tawasulPersonID);
        $sql = "DELETE FROM tawasulGroupPerson WHERE tawasulGroupID=:tawasulGroupID AND tawasulPersonID=:tawasulPersonID";

        return $this->db()->delete($sql, $data);
    }

    public function deletePeopleByGroupID($tawasulGroupID)
    {
        $data = array('tawasulGroupID' => $tawasulGroupID);
        $sql = "DELETE FROM tawasulGroupPerson WHERE tawasulGroupID=:tawasulGroupID";

        return $this->db()->delete($sql, $data);
    }
}
