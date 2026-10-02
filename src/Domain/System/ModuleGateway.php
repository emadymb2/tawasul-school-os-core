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

use TawasulOS\Contracts\Database\Result;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * Module Gateway
 *
 * @version v25
 * @since   v16
 */
class ModuleGateway extends QueryableGateway
{
    use TableAware;

    /**
     * Table name used by TableAware trait.
     *
     * @var string
     */
    private static $tableName = 'tawasulModule';

    /**
     * Table primary key used by TableAware trait.
     *
     * @var string
     */
    private static $primaryKey = 'tawasulModuleID';

    /**
     * Searchable columns used by TableAware trait.
     *
     * @var string
     */
    private static $searchableColumns = ['name'];

    /**
     * Queries the list for the Manage Modules page.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryModules(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulModuleID', 'name', 'description', 'type', 'author', 'url', 'active', 'version'
            ]);

        $criteria->addFilterRules([
            'type' => function ($query, $type) {
                return $query
                    ->where('tawasulModule.type = :type')
                    ->bindValue('type', ucfirst($type));
            },

            'active' => function ($query, $active) {
                return $query
                    ->where('tawasulModule.active = :active')
                    ->bindValue('active', ucfirst($active));
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Gets an unfiltered list of all modules.
     *
     * @version v16
     * @since   v16
     *
     * @return string[]
     */
    public function getAllModuleNames()
    {
        $sql = "SELECT name FROM tawasulModule";

        return $this->db()->select($sql)->fetchAll(\PDO::FETCH_COLUMN);
    }

   /**
     * The modules by role.
     *
     * @version v16
     * @since   v16
     *
     * @param string $tawasulRoleID
     *
     * @return Result
     */
    public function selectModulesByRole($tawasulRoleID)
    {
        $mainMenuCategoryOrder = $this->db()->selectOne("SELECT value FROM tawasulSetting WHERE scope='System' AND name='mainMenuCategoryOrder'");

        $data = array('tawasulRoleID' => $tawasulRoleID, 'menuOrder' => $mainMenuCategoryOrder);
        $sql = "SELECT tawasulModule.category, tawasulModule.name, tawasulModule.type, tawasulModule.entryURL, tawasulAction.entryURL as alternateEntryURL, (CASE WHEN tawasulModule.type <> 'Core' THEN tawasulModule.name ELSE NULL END) as textDomain
                FROM tawasulModule
                JOIN tawasulAction ON (tawasulAction.tawasulModuleID=tawasulModule.tawasulModuleID)
                JOIN tawasulPermission ON (tawasulPermission.tawasulActionID=tawasulAction.tawasulActionID)
                WHERE tawasulModule.active='Y'
                AND tawasulAction.menuShow='Y'
                AND tawasulPermission.tawasulRoleID=:tawasulRoleID
                GROUP BY tawasulModule.name
                ORDER BY FIND_IN_SET(tawasulModule.category, :menuOrder), tawasulModule.category, tawasulModule.name, tawasulAction.name";

        return $this->db()->select($sql, $data);
    }

    /**
     * The module actions by role.
     *
     * @version v16
     * @since   v16
     *
     * @param string $tawasulRoleID
     * @param string $tawasulModuleID
     *
     * @return Result
     */
    public function selectModuleActionsByRole($tawasulRoleID, $tawasulModuleID)
    {
        $data = array('tawasulModuleID' => $tawasulRoleID, 'tawasulRoleID' => $tawasulModuleID);
        $sql = "SELECT tawasulAction.category, tawasulModule.entryURL AS moduleEntry, tawasulModule.name AS moduleName, tawasulAction.name as actionName, tawasulModule.type, tawasulAction.precedence, tawasulAction.entryURL, URLList, SUBSTRING_INDEX(tawasulAction.name, '_', 1) as name, (CASE WHEN tawasulModule.type <> 'Core' THEN tawasulModule.name ELSE NULL END) AS textDomain
                FROM tawasulModule
                JOIN tawasulAction ON (tawasulModule.tawasulModuleID=tawasulAction.tawasulModuleID)
                JOIN tawasulPermission ON (tawasulAction.tawasulActionID=tawasulPermission.tawasulActionID)
                WHERE (tawasulModule.tawasulModuleID=:tawasulModuleID)
                AND (tawasulPermission.tawasulRoleID=:tawasulRoleID)
                AND NOT tawasulAction.entryURL=''
                AND tawasulAction.menuShow='Y'
                GROUP BY name
                ORDER BY tawasulModule.name, tawasulAction.category, tawasulAction.name, precedence DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * A list of additional (non-core) modules.
     *
     * @version v25
     * @since   v25
     *
     * @param string $tawasulRoleID
     * @param string $tawasulModuleID
     * @return array
     */
    public function getActiveAdditional(): array
    {
        $select = $this->newSelect()
            ->from($this->getTableName())
            ->cols($this->getSearchableColumns())
            ->where('active="Y"')
            ->where('type="Additional"');
        return $this->runSelect($select)->fetchAll();
    }
}
