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

namespace TawasulOS\Domain\Staff;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * Staff Duty Gateway
 *
 * @version v25
 * @since   v25
 */
class StaffDutyGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulStaffDuty';
    private static $primaryKey = 'tawasulStaffDutyID';

    private static $searchableColumns = ['tawasulStaffDutyID', 'name'];

    /**
     * Queries the duty schedule.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryDuty(QueryCriteria $criteria) {
        $query = $this
            ->newQuery()
            ->cols([
                'tawasulStaffDuty.tawasulStaffDutyID', 'tawasulStaffDuty.name', 'tawasulStaffDuty.nameShort', 'tawasulStaffDuty.timeStart', 'tawasulStaffDuty.timeEnd', 'tawasulStaffDuty.sequenceNumber', 'tawasulStaffDuty.tawasulDaysOfWeekIDList', 'tawasulStaffDuty.type',
            ])
            ->from($this->getTableName());

        return $this->runQuery($query, $criteria);
    }

    /**
     * Gers the duty roster.
     *
     * @return Result
     */
    public function selectDutyTimeSlots() {
        $query = $this
            ->newSelect()
            ->cols([
                'tawasulDaysOfWeek.name as groupBy', 'tawasulStaffDuty.tawasulStaffDutyID', 'tawasulDaysOfWeek.tawasulDaysOfWeekID', 'tawasulDaysOfWeek.name as weekdayName', 'tawasulStaffDuty.name', 'tawasulStaffDuty.timeStart', 'tawasulStaffDuty.timeEnd',
                'tawasulStaffDuty.type'
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulDaysOfWeek', 'FIND_IN_SET(tawasulDaysOfWeek.tawasulDaysOfWeekID, tawasulStaffDuty.tawasulDaysOfWeekIDList)')
            ->groupBy(['tawasulStaffDuty.tawasulStaffDutyID', 'tawasulDaysOfWeek.tawasulDaysOfWeekID'])
            ->orderBy(['tawasulDaysOfWeek.sequenceNumber', 'tawasulStaffDuty.sequenceNumber']);

        return $this->runSelect($query);
    }

    public function selectDutyTimeSlotsByWeekday($tawasulDaysOfWeekID)
    {
        $data = ['tawasulDaysOfWeekID' => $tawasulDaysOfWeekID];
        $sql = "SELECT tawasulStaffDutyID as value, name FROM tawasulStaffDuty WHERE FIND_IN_SET(:tawasulDaysOfWeekID, tawasulDaysOfWeekIDList) ORDER BY tawasulStaffDuty.sequenceNumber";

        return $this->db()->select($sql, $data);
    }

    public function deleteDutyNotInList($tawasulStaffDutyIDList)
    {
        $tawasulStaffDutyIDList = is_array($tawasulStaffDutyIDList) ? implode(',', $tawasulStaffDutyIDList) : $tawasulStaffDutyIDList;

        $data = ['tawasulStaffDutyIDList' => $tawasulStaffDutyIDList];
        $sql = "DELETE FROM tawasulStaffDuty WHERE NOT FIND_IN_SET(tawasulStaffDutyID, :tawasulStaffDutyIDList)";

        return $this->db()->delete($sql, $data);
    }
}
