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

namespace TawasulOS\Domain\Timetable;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v16
 * @since   v16
 */
class FacilityBookingGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulTTSpaceBooking';
    private static $primaryKey = 'tawasulTTSpaceBookingID';

    private static $searchableColumns = ['name', 'tawasulPerson.surname', 'tawasulPerson.preferredName'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryFacilityBookings(QueryCriteria $criteria, $tawasulPersonID = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulTTSpaceBookingID', 'date', 'timeStart', 'timeEnd', 'reason', 'tawasulSpace.name', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'foreignKey', 'foreignKeyID'
            ])
            ->innerJoin('tawasulSpace', 'tawasulTTSpaceBooking.foreignKeyID=tawasulSpace.tawasulSpaceID')
            ->innerJoin('tawasulPerson', 'tawasulTTSpaceBooking.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->where("foreignKey='tawasulSpaceID'")
            ->where('date >= :today')
            ->bindValue('today', date('Y-m-d'));

        if (!empty($tawasulPersonID)) {
            $query->where('tawasulTTSpaceBooking.tawasulPersonID = :tawasulPersonID')
                  ->bindValue('tawasulPersonID', $tawasulPersonID);
        }

        $this->unionAllWithCriteria($query, $criteria)
            ->from($this->getTableName())
            ->cols([
                'tawasulTTSpaceBookingID', 'date', 'timeStart', 'timeEnd', 'reason', 'tawasulLibraryItem.name', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'foreignKey', 'foreignKeyID'
            ])
            ->innerJoin('tawasulLibraryItem', 'tawasulTTSpaceBooking.foreignKeyID=tawasulLibraryItem.tawasulLibraryItemID')
            ->innerJoin('tawasulPerson', 'tawasulTTSpaceBooking.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->where("foreignKey='tawasulLibraryItemID'")
            ->where('date >= :today')
            ->bindValue('today', date('Y-m-d'));

        if (!empty($tawasulPersonID)) {
            $query->where('tawasulTTSpaceBooking.tawasulPersonID = :tawasulPersonID')
                  ->bindValue('tawasulPersonID', $tawasulPersonID);
        }

        return $this->runQuery($query, $criteria);
    }

    public function selectFacilityBookingsByDateRange($dateStart, $dateEnd, $tawasulPersonID = null, $tawasulSpaceID = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulTTSpaceBookingID', 'date', 'timeStart', 'timeEnd', 'reason', 'tawasulSpace.name', 'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'foreignKey', 'foreignKeyID'
            ])
            ->innerJoin('tawasulSpace', 'tawasulTTSpaceBooking.foreignKeyID=tawasulSpace.tawasulSpaceID')
            ->innerJoin('tawasulPerson', 'tawasulTTSpaceBooking.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->where("foreignKey='tawasulSpaceID'")
            ->where('date BETWEEN :dateStart AND :dateEnd')
            ->bindValue('dateStart', $dateStart)
            ->bindValue('dateEnd', $dateEnd);

        if (!empty($tawasulPersonID)) {
            $query->where('tawasulTTSpaceBooking.tawasulPersonID = :tawasulPersonID')
                  ->bindValue('tawasulPersonID', $tawasulPersonID);
        }
        if (!empty($tawasulSpaceID)) {
            $query->where('tawasulSpace.tawasulSpaceID = :tawasulSpaceID')
                  ->bindValue('tawasulSpaceID', $tawasulSpaceID);
        }

        if (empty($tawasulSpaceID)) {
            $query->unionAll()
                ->from($this->getTableName())
                ->cols([
                    'tawasulTTSpaceBookingID', 'date', 'timeStart', 'timeEnd', 'reason', 'tawasulLibraryItem.name', 'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'foreignKey', 'foreignKeyID'
                ])
                ->innerJoin('tawasulLibraryItem', 'tawasulTTSpaceBooking.foreignKeyID=tawasulLibraryItem.tawasulLibraryItemID')
                ->innerJoin('tawasulPerson', 'tawasulTTSpaceBooking.tawasulPersonID=tawasulPerson.tawasulPersonID')
                ->where("foreignKey='tawasulLibraryItemID'")
                ->where('date BETWEEN :dateStart AND :dateEnd')
                ->bindValue('dateStart', $dateStart)
                ->bindValue('dateEnd', $dateEnd);


            if (!empty($tawasulPersonID)) {
                $query->where('tawasulTTSpaceBooking.tawasulPersonID = :tawasulPersonID')
                    ->bindValue('tawasulPersonID', $tawasulPersonID);
            }
        }


        return $this->runSelect($query);
    }

    public function queryFacilityBookingsByDate($startDate, $endDate)
    {
        $data = array('startDate' => $startDate, 'endDate' => $endDate);
        $sql = "SELECT tawasulTTSpaceBookingID, date, timeStart, timeEnd, reason, tawasulSpace.name, tawasulSpace.tawasulSpaceID
            FROM tawasulTTSpaceBooking
                INNER JOIN tawasulSpace ON tawasulTTSpaceBooking.foreignKeyID=tawasulSpace.tawasulSpaceID
            WHERE
                foreignKey='tawasulSpaceID'
                AND date>=:startDate
                AND date<=:endDate";

        return $this->db()->select($sql, $data);

    }
}
