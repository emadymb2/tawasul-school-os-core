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

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;

/**
 * Notification Gateway
 *
 * Provides a data access layer for the tawasulNotification table
 *
 * @version v25
 * @since   v14
 */
class NotificationGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulNotification';
    private static $primaryKey = 'tawasulNotificationID';

    private static $searchableColumns = [];

    public function queryNotificationsByPerson(QueryCriteria $criteria, $tawasulPersonID, $status = 'New')
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulNotification.*', "(CASE WHEN tawasulModule.tawasulModuleID IS NOT NULL THEN tawasulModule.name ELSE 'System' END) AS source"
            ])
            ->leftJoin('tawasulModule', 'tawasulNotification.tawasulModuleID=tawasulModule.tawasulModuleID')
            ->where('tawasulNotification.status=:status')
            ->bindValue('status', $status)
            ->where('tawasulNotification.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID);

        return $this->runQuery($query, $criteria);
    }

    /* NOTIFICATIONS */
    public function selectNotification($tawasulNotificationID)
    {
        $data = array('tawasulNotificationID' => $tawasulNotificationID);
        $sql = "SELECT * FROM tawasulNotification WHERE tawasulNotificationID=:tawasulNotificationID";

        return $this->db()->select($sql, $data);
    }

    public function selectNotificationByStatus($values, $status = 'New')
    {
        $data = [
            'tawasulPersonID' => $values['tawasulPersonID'],
            'text'           => $values['text'],
            'moduleName'     => $values['moduleName'],
            'actionLink'     => $values['actionLink'],
            'status'         => $status,
        ];
        $sql = "SELECT * FROM tawasulNotification WHERE tawasulPersonID=:tawasulPersonID AND text=:text AND actionLink=:actionLink AND tawasulModuleID=(SELECT tawasulModuleID FROM tawasulModule WHERE name=:moduleName) AND status=:status";

        return $this->db()->select($sql, $data);
    }

    public function updateNotificationCount($tawasulNotificationID, $count)
    {
        $data = array('tawasulNotificationID' => $tawasulNotificationID, 'count' => $count);
        $sql = "UPDATE tawasulNotification SET count=:count, timestamp=now() WHERE tawasulNotificationID=:tawasulNotificationID";

        return $this->db()->update($sql, $data);
    }

    public function insertNotification($values)
    {
        $data = [
            'tawasulPersonID' => $values['tawasulPersonID'],
            'text'           => $values['text'],
            'moduleName'     => $values['moduleName'],
            'actionLink'     => $values['actionLink'],
        ];

        $sql = 'INSERT INTO tawasulNotification SET tawasulPersonID=:tawasulPersonID, tawasulModuleID=(SELECT tawasulModuleID FROM tawasulModule WHERE name=:moduleName), text=:text, actionLink=:actionLink, timestamp=now()';

        return $this->db()->insert($sql, $data);
    }

    /* NOTIFICATION EVENTS */
    public function selectNotificationEventByID($tawasulNotificationEventID)
    {
        $data = array('tawasulNotificationEventID' => $tawasulNotificationEventID);
        $sql = "SELECT * FROM tawasulNotificationEvent WHERE tawasulNotificationEventID=:tawasulNotificationEventID";

        return $this->db()->select($sql, $data);
    }

    public function selectNotificationEventByName($moduleName, $event)
    {
        $data = array('moduleName' => $moduleName, 'event' => $event);
        $sql = "SELECT tawasulNotificationEvent.*
                FROM tawasulNotificationEvent
                JOIN tawasulModule ON (tawasulNotificationEvent.moduleName=tawasulModule.name)
                WHERE tawasulNotificationEvent.moduleName=:moduleName
                AND tawasulNotificationEvent.event=:event
                AND tawasulModule.active='Y'";

        return $this->db()->select($sql, $data);
    }

    public function selectAllNotificationEvents()
    {
        $sql = "SELECT tawasulNotificationEvent.*, COUNT(tawasulNotificationListenerID) as listenerCount FROM tawasulNotificationEvent JOIN tawasulModule ON (tawasulNotificationEvent.moduleName=tawasulModule.name) LEFT JOIN tawasulNotificationListener ON (tawasulNotificationEvent.tawasulNotificationEventID=tawasulNotificationListener.tawasulNotificationEventID) WHERE tawasulModule.active='Y' GROUP BY tawasulNotificationEvent.tawasulNotificationEventID ORDER BY tawasulModule.name, tawasulNotificationEvent.event";

        return $this->db()->select($sql);
    }

    public function updateNotificationEvent($update)
    {
        $data = array('tawasulNotificationEventID' => $update['tawasulNotificationEventID'], 'active' => $update['active']);
        $sql = "UPDATE tawasulNotificationEvent SET active=:active WHERE tawasulNotificationEventID=:tawasulNotificationEventID";

        return $this->db()->update($sql, $data);
    }

    /* NOTIFICATION LISTENERS */
    public function selectNotificationListener($tawasulNotificationListenerID)
    {
        $data = array('tawasulNotificationListenerID' => $tawasulNotificationListenerID);
        $sql = "SELECT * FROM tawasulNotificationListener WHERE tawasulNotificationListenerID=:tawasulNotificationListenerID";

        return $this->db()->select($sql, $data);
    }

    public function selectAllNotificationListeners($tawasulNotificationEventID, $groupByPerson = true)
    {
        $data = array('tawasulNotificationEventID' => $tawasulNotificationEventID);
        $sql = "SELECT tawasulNotificationListener.*, tawasulPerson.surname, tawasulPerson.preferredName, tawasulPerson.title, tawasulPerson.receiveNotificationEmails, tawasulPerson.status
                FROM tawasulNotificationListener
                JOIN tawasulNotificationEvent ON (tawasulNotificationListener.tawasulNotificationEventID=tawasulNotificationEvent.tawasulNotificationEventID)
                JOIN tawasulPerson ON (tawasulNotificationListener.tawasulPersonID=tawasulPerson.tawasulPersonID)
                JOIN tawasulRole ON (tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID OR FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll))
                JOIN tawasulPermission ON (tawasulRole.tawasulRoleID=tawasulPermission.tawasulRoleID)
                JOIN tawasulAction ON (tawasulPermission.tawasulActionID=tawasulAction.tawasulActionID)
                WHERE tawasulNotificationListener.tawasulNotificationEventID=:tawasulNotificationEventID
                AND (tawasulNotificationEvent.actionName=tawasulAction.name OR tawasulAction.name LIKE CONCAT(tawasulNotificationEvent.actionName, '_%'))";

        if ($groupByPerson) {
            $sql .= " GROUP BY tawasulNotificationListener.tawasulPersonID";
        } else {
            $sql .= " GROUP BY tawasulNotificationListener.tawasulNotificationListenerID";
        }

        return $this->db()->select($sql, $data);
    }

    public function selectNotificationListenersByScope($tawasulNotificationEventID, $scopes = array())
    {
        $data = array('tawasulNotificationEventID' => $tawasulNotificationEventID, 'today' => date('Y-m-d'));
        $sql = "SELECT DISTINCT tawasulPerson.tawasulPersonID FROM tawasulNotificationListener
                JOIN tawasulPerson ON (tawasulNotificationListener.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE tawasulNotificationEventID=:tawasulNotificationEventID
                AND tawasulPerson.status='Full'
                AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:today)
                AND (tawasulPerson.dateEnd IS NULL  OR tawasulPerson.dateEnd>=:today)";

        if (is_array($scopes) && count($scopes) > 0) {
            $sql .= " AND (scopeType='All' ";
            $i = 0;
            foreach ($scopes as $scope) {
                $data['scopeType'.$i] = $scope['type'];
                $data['scopeTypeID'.$i] = $scope['id'];
                $data['scopeContext'.$i] = $scope['id'];
                $sql .= " OR (scopeType=:scopeType{$i} AND scopeID=:scopeTypeID{$i} AND scopeID<>0)";
                $sql .= " OR (scopeType='context' AND scopeContext=:scopeContext{$i} AND scopeID=0)";
                $i++;
            }
            $sql .= ")";
        } else {
            $sql .= " AND scopeType='All'";
        }

        return $this->db()->select($sql, $data);
    }

    public function insertNotificationListener($data)
    {
        $sql = 'INSERT INTO tawasulNotificationListener SET tawasulNotificationEventID=:tawasulNotificationEventID, tawasulPersonID=:tawasulPersonID, scopeType=:scopeType, scopeID=:scopeID, scopeContext=:scopeContext';

        return $this->db()->insert($sql, $data);
    }

    /**
     * Archives one or more notifications, based on partial match of actionLink
     * and total match of tawasulPersonID.
     *
     * @version v25
     * @since   v25
     *
     * @param int     $tawasulPersonID  The TawasulOS person ID.
     * @param string  $actionLinkPart  The partial string in an action link.
     *
     * @return bool Whether the database update was successful.
     */
    public function archiveNotificationForPersonAction($tawasulPersonID, string $actionLinkPart): bool
    {
        $sql = 'UPDATE tawasulNotification SET status="Archived" WHERE tawasulPersonID=:tawasulPersonID AND actionLink LIKE :actionLink AND status="New"';
        return $this->db()->update($sql, [
            'tawasulPersonID' => $tawasulPersonID,
            'actionLink' => '%' . $actionLinkPart . '%',
        ]);
    }

    public function deleteNotificationListener($tawasulNotificationListenerID)
    {
        $data = array('tawasulNotificationListenerID' => $tawasulNotificationListenerID);
        $sql = 'DELETE FROM tawasulNotificationListener WHERE tawasulNotificationListenerID=:tawasulNotificationListenerID';

        return $this->db()->delete($sql, $data);
    }

    /* NOTIFICATION MODULES */
    public function deleteCascadeNotificationByModuleName($moduleName)
    {
        $data = array('moduleName' => $moduleName);
        $sql = 'DELETE tawasulNotificationEvent, tawasulNotificationListener FROM tawasulNotificationEvent LEFT JOIN tawasulNotificationListener ON (tawasulNotificationEvent.tawasulNotificationEventID=tawasulNotificationListener.tawasulNotificationEventID) WHERE tawasulNotificationEvent.moduleName=:moduleName';

        return $this->db()->delete($sql, $data);
    }

    /* NOTIFICATION PREFERENCES */
    public function getNotificationPreference($tawasulPersonID)
    {
        $data = array('tawasulPersonID' => $tawasulPersonID);
        $sql = "SELECT email, (CASE WHEN status='Full' THEN receiveNotificationEmails ELSE 'N' END) as receiveNotificationEmails FROM tawasulPerson WHERE tawasulPersonID=:tawasulPersonID AND receiveNotificationEmails='Y' AND NOT email=''";

        return $this->db()->selectOne($sql, $data);
    }

    public function deleteStaleNotifications()
    {
        $sql = 'DELETE FROM tawasulNotification WHERE timestamp <= NOW() - INTERVAL 3 MONTH';

        return $this->db()->delete($sql);
    }

    
}
