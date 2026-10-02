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

/**
 * Hook Gateway
 *
 * @version v20
 * @since   v20
 */
class HookGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulHook';
    private static $primaryKey = 'tawasulHookID';

    public function selectHooksByType($type)
    {
        $data = ['type' => $type];
        $sql = "SELECT tawasulHook.name as groupBy, tawasulHook.* 
                FROM tawasulHook 
                JOIN tawasulModule ON (tawasulModule.tawasulModuleID=tawasulHook.tawasulModuleID)
                WHERE tawasulHook.type=:type 
                AND tawasulModule.active='Y'
                ORDER BY tawasulHook.name";

        return $this->db()->select($sql, $data);
    }

    public function getHookPermission($tawasulHookID, $tawasulRoleIDCurrent, $moduleName, $actionName)
    {
        $data = ['tawasulHookID' => $tawasulHookID, 'tawasulRoleIDCurrent' => $tawasulRoleIDCurrent, 'sourceModuleName' => $moduleName, 'sourceModuleAction' => $actionName];
        $sql = "SELECT tawasulHook.name, tawasulModule.name AS module, tawasulAction.name AS action
            FROM tawasulHook
            JOIN tawasulModule ON (tawasulHook.tawasulModuleID=tawasulModule.tawasulModuleID)
            JOIN tawasulAction ON (tawasulAction.tawasulModuleID=tawasulModule.tawasulModuleID)
            JOIN tawasulPermission ON (tawasulPermission.tawasulActionID=tawasulAction.tawasulActionID)
            WHERE tawasulModule.name=:sourceModuleName
            AND FIND_IN_SET(tawasulAction.name, :sourceModuleAction)
            AND tawasulPermission.tawasulRoleID=:tawasulRoleIDCurrent
            AND tawasulAction.tawasulModuleID=(SELECT tawasulModuleID FROM tawasulModule WHERE name=:sourceModuleName)
            AND tawasulHook.tawasulHookID=:tawasulHookID 
            ORDER BY name";

        return $this->db()->selectOne($sql, $data);
    }

    public function getAccessibleHooksByType(string $type, string $tawasulRoleIDCurrent)
    {
        $hooksAvailable = $this->selectHooksByType($type)->fetchAll();
        $hooks = [];

        foreach ($hooksAvailable as $hook) {
            $options = unserialize($hook['options']);

            //Check for permission to hook
            $hookPermission = $this->getHookPermission($hook['tawasulHookID'], $tawasulRoleIDCurrent, $options['sourceModuleName'] ?? '', $options['sourceModuleAction'] ?? '');

            if (!empty($options) && !empty($hookPermission)) {
                $hooks[] = [
                    'name'                => $hook['name'],
                    'sourceModuleName'    => $hookPermission['module'],
                    'sourceModuleInclude' => $options['sourceModuleInclude'],
                ];
            }
        }

        return $hooks;
    }
}
