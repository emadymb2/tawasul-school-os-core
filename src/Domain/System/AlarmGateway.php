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
 * Alarm Gateway
 *
 * @version v19
 * @since   v19
 */
class AlarmGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulAlarm';
    private static $primaryKey = 'tawasulAlarmID';

    private static $searchableColumns = ['type'];
    
    public function selectAlarmConfirmation($tawasulAlarmID)
    {
        $data = ['tawasulAlarmID' => $tawasulAlarmID, 'today' => date('Y-m-d')];
        $sql = "SELECT tawasulPerson.tawasulPersonID, status, surname, preferredName, tawasulAlarmConfirmID 
                FROM tawasulPerson 
                JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) 
                LEFT JOIN tawasulAlarmConfirm ON (tawasulAlarmConfirm.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulAlarmID=:tawasulAlarmID) 
                WHERE tawasulPerson.status='Full' 
                AND (dateStart IS NULL OR dateStart<=:today) 
                AND (dateEnd IS NULL  OR dateEnd>=:today) 
                ORDER BY surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function getAlarmConfirmationByPerson($tawasulAlarmID, $tawasulPersonID)
    {
        $data = ['tawasulAlarmID' => $tawasulAlarmID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT * FROM tawasulAlarmConfirm WHERE tawasulAlarmID=:tawasulAlarmID AND tawasulPersonID=:tawasulPersonID";

        return $this->db()->selectOne($sql, $data);
    }
    
    public function insertAlarmConfirm(array $data) {

        $query = $this
            ->newInsert()
            ->into('tawasulAlarmConfirm')
            ->cols($data);

        return $this->runInsert($query);
    }
    
}
