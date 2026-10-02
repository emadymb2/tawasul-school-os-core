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
 * Staff Duty Person Gateway
 *
 * @version v25
 * @since   v25
 */
class StaffDutyPersonGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulStaffDutyPerson';
    private static $primaryKey = 'tawasulStaffDutyPersonID';

    private static $searchableColumns = [''];

    /**
     * Queries the duty roster.
     *
     * @return DataSet
     */
    public function selectDutyRoster() {
        $query = $this
            ->newSelect()
            ->cols([
                'tawasulStaffDuty.tawasulStaffDutyID as groupBy', 'tawasulStaffDuty.tawasulStaffDutyID', 'tawasulStaffDutyPerson.tawasulStaffDutyPersonID', 'tawasulPerson.tawasulPersonID', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.title', 'tawasulPerson.image_240', 'tawasulDaysOfWeek.tawasulDaysOfWeekID', 'tawasulDaysOfWeek.name as weekdayName'
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulStaffDuty', 'tawasulStaffDuty.tawasulStaffDutyID=tawasulStaffDutyPerson.tawasulStaffDutyID')
            ->innerJoin('tawasulDaysOfWeek', 'tawasulDaysOfWeek.tawasulDaysOfWeekID=tawasulStaffDutyPerson.tawasulDaysOfWeekID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulStaffDutyPerson.tawasulPersonID')
            ->where('tawasulPerson.status="Full"');

        return $this->runSelect($query);
    }

    public function selectDutyByPerson($tawasulPersonID)
    {
        $query = $this
            ->newSelect()
            ->cols([
                'tawasulStaffDuty.tawasulStaffDutyID as groupBy', 'tawasulStaffDutyPerson.tawasulStaffDutyPersonID', 'tawasulStaffDuty.tawasulStaffDutyID', 'tawasulStaffDuty.name', 'tawasulStaffDuty.nameShort', 'tawasulStaffDuty.timeStart', 'tawasulStaffDuty.timeEnd', 'tawasulDaysOfWeek.tawasulDaysOfWeekID', 'tawasulDaysOfWeek.name as dayOfWeek'
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulStaffDuty', 'tawasulStaffDuty.tawasulStaffDutyID=tawasulStaffDutyPerson.tawasulStaffDutyID')
            ->innerJoin('tawasulDaysOfWeek', 'tawasulDaysOfWeek.tawasulDaysOfWeekID=tawasulStaffDutyPerson.tawasulDaysOfWeekID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulStaffDutyPerson.tawasulPersonID')
            ->where('tawasulStaffDutyPerson.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulPerson.status="Full"')
            ->orderBy(['tawasulStaffDuty.sequenceNumber']);

        return $this->runSelect($query);
    }

    public function selectDutyByWeekday($weekday)
    {
        $query = $this
            ->newSelect()
            ->cols([
                'tawasulStaffDuty.tawasulStaffDutyID as groupBy', 'tawasulStaffDuty.name as context', '"Staff Duty" as contextName', 'tawasulStaffDuty.tawasulStaffDutyID', 'tawasulStaffDuty.name', 'tawasulStaffDuty.nameShort', 'tawasulStaffDuty.timeStart', 'tawasulStaffDuty.timeEnd', 'tawasulDaysOfWeek.tawasulDaysOfWeekID', 'tawasulDaysOfWeek.name as dayOfWeek', 'tawasulPerson.tawasulPersonID', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.title'
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulStaffDuty', 'tawasulStaffDuty.tawasulStaffDutyID=tawasulStaffDutyPerson.tawasulStaffDutyID')
            ->innerJoin('tawasulDaysOfWeek', 'tawasulDaysOfWeek.tawasulDaysOfWeekID=tawasulStaffDutyPerson.tawasulDaysOfWeekID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulStaffDutyPerson.tawasulPersonID')
            ->where('tawasulDaysOfWeek.name=:weekday')
            ->bindValue('weekday', $weekday)
            ->where('tawasulPerson.status="Full"')
            ->orderBy(['tawasulStaffDuty.sequenceNumber']);

        return $this->runSelect($query);
    }

    public function getDutyDetailsByID($tawasulStaffDutyPersonID)
    {
        $data = ['tawasulStaffDutyPersonID' => $tawasulStaffDutyPersonID];
        $sql = "SELECT tawasulStaffDuty.* 
                FROM tawasulStaffDuty 
                JOIN tawasulStaffDutyPerson ON (tawasulStaffDuty.tawasulStaffDutyID=tawasulStaffDutyPerson.tawasulStaffDutyID)
                WHERE tawasulStaffDutyPerson.tawasulStaffDutyPersonID=:tawasulStaffDutyPersonID";

        return $this->db()->selectOne($sql, $data);
    }
}
