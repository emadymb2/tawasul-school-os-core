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

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\Traits\TableAware;

/**
 * Session Gateway
 *
 * @version v23
 * @since   v23
 */
class SessionGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulSession';
    private static $primaryKey = 'tawasulSessionID';

    /**
     * Queries the list of sessions.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryActiveSessions(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulSessionID', 'tawasulSession.timestampCreated', 'tawasulSession.sessionStatus', 'tawasulSession.timestampModified', 'tawasulPerson.tawasulPersonID', 'tawasulPerson.title', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.username',  'tawasulPerson.lastIPAddress', 'tawasulRole.category AS roleCategory', "CONCAT(tawasulModule.name, ': ', SUBSTRING_INDEX(tawasulAction.name, '_', 1)) AS actionName"
            ])
            ->leftJoin('tawasulPerson', 'tawasulSession.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulRole', 'tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary')
            ->leftJoin('tawasulAction', 'tawasulAction.tawasulActionID=tawasulSession.tawasulActionID')
            ->leftJoin('tawasulModule', 'tawasulModule.tawasulModuleID=tawasulAction.tawasulModuleID')
            ->where('tawasulSession.tawasulPersonID IS NOT NULL');

        return $this->runQuery($query, $criteria);
    }

    public function updateSessionAction($tawasulSessionID, $actionName, $moduleName, $tawasulPersonID)
    {
        $data = ['tawasulSessionID' => $tawasulSessionID, 'tawasulPersonID' => $tawasulPersonID, 'actionName' => $actionName, 'moduleName' => $moduleName, 'timestampCreated' => date('Y-m-d H:i:s'), 'timestampModified' => date('Y-m-d H:i:s')];
        $sql = "INSERT INTO tawasulSession (tawasulSessionID, tawasulPersonID, tawasulActionID, timestampCreated, timestampModified) VALUES (:tawasulSessionID, :tawasulPersonID, (SELECT tawasulActionID FROM tawasulAction JOIN tawasulModule ON (tawasulModule.tawasulModuleID=tawasulAction.tawasulModuleID) WHERE tawasulModule.name = :moduleName AND FIND_IN_SET(:actionName, tawasulAction.URLList) AND :actionName <> '' LIMIT 1), :timestampCreated, :timestampModified) ON DUPLICATE KEY UPDATE tawasulActionID=VALUES(tawasulActionID), timestampModified=:timestampModified";

        return $this->db()->update($sql, $data);
    }

    public function updateSessionStatus($tawasulSessionID, $tawasulPersonID, $sessionStatus)
    {
        $data = ['tawasulSessionID' => $tawasulSessionID, 'tawasulPersonID' => $tawasulPersonID, 'sessionStatus' => $sessionStatus, 'timestampCreated' => date('Y-m-d H:i:s'), 'timestampModified' => date('Y-m-d H:i:s')];
        $sql = "INSERT INTO tawasulSession (tawasulSessionID, tawasulPersonID, sessionStatus, timestampCreated, timestampModified) VALUES (:tawasulSessionID, :tawasulPersonID, :sessionStatus, :timestampCreated, :timestampModified) ON DUPLICATE KEY UPDATE sessionStatus=:sessionStatus, timestampModified=:timestampModified";

        return $this->db()->update($sql, $data);
    }

    public function updateSessionData($tawasulSessionID, $sessionData)
    {
        $data = ['tawasulSessionID' => $tawasulSessionID, 'sessionData' => $sessionData, 'timestampCreated' => date('Y-m-d H:i:s'), 'timestampModified' => date('Y-m-d H:i:s')];
        $sql = "INSERT INTO tawasulSession (tawasulSessionID, sessionData, timestampCreated, timestampModified) VALUES (:tawasulSessionID, :sessionData, :timestampCreated, :timestampModified) ON DUPLICATE KEY UPDATE sessionData=:sessionData";

        return $this->db()->update($sql, $data);
    }

    public function deleteExpiredSessions($maxLifetime)
    {
        $data = ['maxLifetime' => $maxLifetime];
        $sql = "DELETE FROM tawasulSession WHERE TIMESTAMPDIFF(SECOND, timestampModified, CURRENT_TIMESTAMP) > :maxLifetime";

        return $this->db()->delete($sql, $data);
    }

    public function logoutAllNonAdministratorUsers()
    {
        $sql = "UPDATE tawasulSession 
                JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulSession.tawasulPersonID)
                JOIN tawasulRole ON (tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary)
                SET tawasulSession.tawasulPersonID=NULL, tawasulSession.tawasulActionID=NULL, tawasulSession.sessionStatus=NULL
                WHERE tawasulRole.name <> 'Administrator'";

        return $this->db()->update($sql);
    }
}
