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

namespace TawasulOS\Domain\User;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v16
 * @since   v16
 */
class RoleGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulRole';
    private static $primaryKey = 'tawasulRoleID';

    private static $searchableColumns = ['name', 'nameShort'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryRoles(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulRoleID', 'name', 'nameShort', 'category', 'description', 'type', 'canLoginRole', 'futureYearsLogin', 'pastYearsLogin'
            ]);

        $criteria->addFilterRules([
            'category' => function ($query, $category) {
                return $query
                    ->where('tawasulRole.category = :category')
                    ->bindValue('category', $category);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryUsersByRole(QueryCriteria $criteria, $tawasulRoleID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulPerson.tawasulPersonID', "(CASE WHEN tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID THEN 'Y' ELSE 'N' END) AS primaryRole",
                'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.username', 'tawasulPerson.image_240', 'tawasulPerson.status', 'tawasulPerson.canLogin',
                "GROUP_CONCAT(allRoles.name ORDER BY allRoles.name SEPARATOR ',') as allRoles"
            ])
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID OR FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)')
            ->leftJoin('tawasulRole as allRoles', 'FIND_IN_SET(allRoles.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)')
            ->where('tawasulRole.tawasulRoleID=:tawasulRoleID')
            ->where("tawasulPerson.status='Full'")
            ->bindValue('tawasulRoleID', $tawasulRoleID)
            ->groupBy(['tawasulPerson.tawasulPersonID']);

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('tawasulPerson.status = :status')
                    ->bindValue('status', ucfirst($status));
            },
            'primaryRole' => function ($query, $primaryRole) {
                if (strtoupper($primaryRole) == 'Y') {
                    $query->where('tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID');
                } elseif (strtoupper($primaryRole) == 'N') {
                    $query->where('tawasulPerson.tawasulRoleIDPrimary<>tawasulRole.tawasulRoleID');
                }
                return $query;
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectUsersByAction($name)
    {
        $data = array('name' => $name);
        $sql = "SELECT DISTINCT tawasulPersonID, surname, preferredName
                FROM tawasulAction
                JOIN tawasulPermission ON (tawasulPermission.tawasulActionID=tawasulAction.tawasulActionID)
                JOIN tawasulRole ON (tawasulPermission.tawasulRoleID=tawasulRole.tawasulRoleID)
                JOIN tawasulPerson ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll))
                WHERE
                    (tawasulAction.name=:name OR (tawasulAction.name LIKE CONCAT(:name,'\_%')))
                    AND tawasulPerson.status='Full'
                ORDER BY surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectActionsByRole($tawasulRoleID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulModule.name as moduleName', 'tawasulRole.tawasulRoleID', 'tawasulAction.name', 'tawasulAction.description',
            ])
            ->innerJoin('tawasulPermission', 'tawasulPermission.tawasulRoleID=tawasulRole.tawasulRoleID')
            ->innerJoin('tawasulAction', 'tawasulAction.tawasulActionID=tawasulPermission.tawasulActionID')
            ->innerJoin('tawasulModule', 'tawasulModule.tawasulModuleID=tawasulAction.tawasulModuleID')
            ->where('tawasulRole.tawasulRoleID=:tawasulRoleID')
            ->bindValue('tawasulRoleID', $tawasulRoleID)
            ->orderBy(['tawasulModule.name', 'tawasulAction.name']);

        return $this->runSelect($query);
    }

    public function getRoleByID($tawasulRoleID)
    {
        $data = array('tawasulRoleID' => $tawasulRoleID);
        $sql = "SELECT * FROM tawasulRole WHERE tawasulRoleID=:tawasulRoleID";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectAllRolesByPerson($tawasulPersonID)
    {
        $data = array('tawasulPersonID' => $tawasulPersonID);
        $sql = "SELECT tawasulRoleID AS groupBy, tawasulRole.*
                FROM tawasulPerson
                JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll))
                WHERE tawasulPersonID=:tawasulPersonID";

        return $this->db()->select($sql, $data);
    }

    public function selectRoleListByIDs($tawasulRoleIDAll)
    {
        $data = ['tawasulRoleIDAll' => $tawasulRoleIDAll];
        $sql = "SELECT tawasulRoleID as `0`, name as `1`
                FROM tawasulRole
                WHERE FIND_IN_SET(tawasulRoleID, :tawasulRoleIDAll)";

        return $this->db()->select($sql, $data);
    }

    /**
     * Returns the category of the specified role
     *
     * @param int $tawasulRoleID
     *
     * @return string|false
     */
    public function getRoleCategory($tawasulRoleID)
    {
        $sql = 'SELECT category FROM tawasulRole WHERE tawasulRoleID=:tawasulRoleID';

        return $this->db()->selectOne($sql, ['tawasulRoleID' => $tawasulRoleID]);
    }

    public function getAvailableUserRoleByID($tawasulPersonID, $tawasulRoleID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'tawasulRoleID' => $tawasulRoleID];
        $sql = "SELECT tawasulRole.*
                FROM tawasulPerson 
                JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll))
                WHERE (tawasulPerson.tawasulPersonID=:tawasulPersonID) 
                AND tawasulRole.tawasulRoleID=:tawasulRoleID";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectAllRoleCategories() 
    {
        $sql = 'SELECT DISTINCT category AS value, category AS name FROM tawasulRole ORDER BY category';

        return $this->db()->select($sql);
    }
}
