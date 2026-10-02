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

namespace TawasulOS\Domain\System;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Contracts\Database\Result;

/**
 * @version v16
 * @since   v16
 */
class ActionGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulAction';
    private static $primaryKey = 'tawasulActionID';

    private static $searchableColumns = ['name'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryActions(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                '*'
            ]);

        return $this->runQuery($query, $criteria);
    }

    public function getFastFinderActions($tawasulRoleIDCurrent)
    {
        $data = ['tawasulRoleID' => $tawasulRoleIDCurrent];
        $sql = "SELECT DISTINCT concat(tawasulModule.name, '/', tawasulAction.entryURL) AS id, SUBSTRING_INDEX(tawasulAction.name, '_', 1) AS name, tawasulModule.type, tawasulModule.name AS module
                FROM tawasulModule
                JOIN tawasulAction ON (tawasulAction.tawasulModuleID=tawasulModule.tawasulModuleID)
                JOIN tawasulPermission ON (tawasulPermission.tawasulActionID=tawasulAction.tawasulActionID)
                WHERE active='Y'
                AND menuShow='Y'
                AND tawasulPermission.tawasulRoleID=:tawasulRoleID
                ORDER BY name";

        $actions = $this->db()->select($sql, $data)->fetchAll();

        foreach ($actions as $index => $action) {
            $actions[$index]['name'] = __($action['name']);
        }

        $actions[] = ['name' => ''];

        return $actions;
    }

    /**
     * Check the specified role has access rights to the specified action.
     * Get the names of all actions available for the given role to the
     * specified module and route path.
     *
     * @param int          $tawasulRoleID      The role ID.
     * @param string       $moduleName        The module name.
     * @param string       $routePath         Route path of the entry point in the module.
     * @param string|null  $actionName        Specific action name string, or null if unspecified.
     *                                        Default: null.
     * @param bool         $activeModuleOnly  Only select the active modules or not.
     *                                        Default: true.
     *
     * @return Result
     */
    public function selectModuleActionsByRole(
        $tawasulRoleID,
        string $moduleName,
        string $routePath,
        ?string $actionName = null,
        bool $activeModuleOnly = true
    ): Result {
        $data = [
            'tawasulRoleID' => $tawasulRoleID,
            'moduleName' => $moduleName,
            'routePath' => '%'.$routePath.'.php%',
        ];
        $sql = 'SELECT tawasulAction.name as actionName
        FROM tawasulAction
        JOIN tawasulModule ON (tawasulModule.tawasulModuleID=tawasulAction.tawasulModuleID)
        JOIN tawasulPermission ON (tawasulAction.tawasulActionID=tawasulPermission.tawasulActionID)
        JOIN tawasulRole ON (tawasulPermission.tawasulRoleID=tawasulRole.tawasulRoleID)
        WHERE
            tawasulAction.URLList LIKE :routePath
            AND tawasulPermission.tawasulRoleID=:tawasulRoleID
            AND tawasulModule.name=:moduleName';

        if (!empty($actionName)) {
            $data['actionName'] = $actionName;
            $sql .= ' AND tawasulAction.name=:actionName';
        }

        if ($activeModuleOnly) {
            $data['moduleIsActive'] = 'Y';
            $sql .= ' AND tawasulModule.active=:moduleIsActive';
        }

        return $this->db()->select($sql, $data);
    }
    
    public function insertPermissionByAction($tawasulActionID, $tawasulRoleID)
    {
        $data = ['tawasulActionID' => $tawasulActionID, 'tawasulRoleID' => $tawasulRoleID];
        $sql = "INSERT INTO tawasulPermission SET tawasulActionID=:tawasulActionID, tawasulRoleID=:tawasulRoleID";
        
        return $this->db()->insert($sql, $data);
    }
    
    public function deletePermissionByAction($tawasulActionID)
    {
        $data = array('tawasulActionID' => $tawasulActionID);
        $sql = "DELETE FROM tawasulPermission WHERE tawasulActionID=:tawasulActionID";
        
        return $this->db()->delete($sql, $data);
    }
    
}
