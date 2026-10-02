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

namespace TawasulOS\Domain\Activities;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * Activity Staff Gateway
 *
 * @version v22
 * @since   v22
 */
class ActivityStaffGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulActivityStaff';
    private static $primaryKey = 'tawasulActivityStaffID';

    private static $searchableColumns = ['surname', 'preferredName'];

    public function selectActivityStaff($tawasulActivityID) {
        $select = $this
            ->newSelect()
            ->cols(['preferredName, surname, tawasulActivityStaff.*'])
            ->from($this->getTableName())
            ->leftJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulActivityStaff.tawasulPersonID')
            ->where('tawasulActivityStaff.tawasulActivityID = :tawasulActivityID')
            ->bindValue('tawasulActivityID', $tawasulActivityID)
            ->where('tawasulPerson.status="Full"')
            ->orderBy(['surname', 'preferredName']);

        return $this->runSelect($select);
    }
    
    public function queryUnassignedStaffByCategory($criteria, $tawasulActivityCategoryID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols([
                '0 as tawasulActivityID',
                'tawasulActivityCategory.tawasulActivityCategoryID',
                'tawasulActivityCategory.name as eventName',
                'tawasulActivityCategory.nameShort as eventNameShort',
                'tawasulPerson.tawasulPersonID',
                'tawasulPerson.surname',
                'tawasulPerson.preferredName',
                'tawasulPerson.title',
                'tawasulPerson.email',
                'tawasulPerson.image_240',
                'tawasulStaff.type',
                'tawasulStaff.jobTitle',
                'tawasulStaff.initials',
            ])
            ->innerJoin('tawasulStaff', 'tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulActivityCategory', 'tawasulActivityCategory.tawasulActivityCategoryID=:tawasulActivityCategoryID')
            ->leftJoin('tawasulActivity', 'tawasulActivity.tawasulActivityCategoryID=tawasulActivityCategory.tawasulActivityCategoryID AND tawasulActivity.active="Y"')
            ->leftJoin('tawasulActivityStaff', 'tawasulActivityStaff.tawasulActivityID=tawasulActivity.tawasulActivityID AND tawasulActivityStaff.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->bindValue('tawasulActivityCategoryID', $tawasulActivityCategoryID)
            ->where("tawasulPerson.status = 'Full'")
            ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)')
            ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
            ->bindValue('today', date('Y-m-d'))
            ->groupBy(['tawasulPerson.tawasulPersonID'])
            ->having('COUNT(DISTINCT tawasulActivityStaff.tawasulActivityStaffID) = 0');

        $criteria->addFilterRules([
            'type' => function ($query, $tawasulActivityCategoryID) {
                return $query
                    ->where('tawasulActivityCategory.tawasulActivityCategoryID = :tawasulActivityCategoryID')
                    ->bindValue('tawasulActivityCategoryID', $tawasulActivityCategoryID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectStaffByCategory($tawasulActivityCategoryID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID];
        $sql = "SELECT tawasulActivityStaff.tawasulActivityStaffID as groupBy,
                    tawasulActivity.tawasulActivityID,
                    tawasulActivityStaff.tawasulActivityStaffID as enrolmentID,
                    tawasulActivityStaff.tawasulActivityStaffID,
                    tawasulActivityStaff.role,
                    tawasulPerson.tawasulPersonID,
                    tawasulPerson.surname,
                    tawasulPerson.preferredName,
                    tawasulPerson.image_240,
                    tawasulStaff.type
                FROM tawasulActivityStaff
                JOIN tawasulActivity ON (tawasulActivity.tawasulActivityID=tawasulActivityStaff.tawasulActivityID)
                JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulActivityStaff.tawasulPersonID) 
                LEFT JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE tawasulActivity.tawasulActivityCategoryID=:tawasulActivityCategoryID
                AND tawasulPerson.status='Full'
                ORDER BY tawasulActivityStaff.role DESC, tawasulPerson.surname, tawasulPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectStaffByActivity($tawasulActivityID) {
        $tawasulActivityID = is_array($tawasulActivityID) ? $tawasulActivityID : [$tawasulActivityID];
        $data = ['tawasulActivityID' => $tawasulActivityID];
        $sql = "SELECT tawasulActivity.*, tawasulActivityStaff.tawasulPersonID, tawasulActivityStaff.role 
            FROM tawasulActivity 
            JOIN tawasulActivityStaff ON (tawasulActivity.tawasulActivityID=tawasulActivityStaff.tawasulActivityID) 
            WHERE tawasulActivity.tawasulActivityID=:tawasulActivityID 
            AND tawasulActivityStaff.role='Organiser' AND active='Y' 
            ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    public function selectActivityOrganiserByPerson($tawasulActivityID, $tawasulPersonID) {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'tawasulActivityID' => $tawasulActivityID];
        $sql = "SELECT tawasulActivity.*, NULL as status, tawasulActivityStaff.role FROM tawasulActivity JOIN tawasulActivityStaff ON (tawasulActivity.tawasulActivityID=tawasulActivityStaff.tawasulActivityID) WHERE tawasulActivity.tawasulActivityID=:tawasulActivityID AND tawasulActivityStaff.tawasulPersonID=:tawasulPersonID AND tawasulActivityStaff.role='Organiser' AND active='Y' ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    public function getActivityAccessByStaff($tawasulActivityID, $tawasulPersonID) {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'tawasulActivityID' => $tawasulActivityID];
        $sql = "SELECT tawasulActivity.*, NULL as status, tawasulActivityStaff.role FROM tawasulActivity JOIN tawasulActivityStaff ON (tawasulActivity.tawasulActivityID=tawasulActivityStaff.tawasulActivityID) WHERE tawasulActivity.tawasulActivityID=:tawasulActivityID AND tawasulActivityStaff.tawasulPersonID=:tawasulPersonID AND active='Y' ORDER BY name";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectStaffByCategoryAndPerson($tawasulActivityCategoryID, $tawasulPersonID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulActivity.tawasulActivityID,
                    tawasulActivity.tawasulActivityCategoryID,
                    tawasulActivity.name,
                    tawasulActivityStaff.tawasulActivityStaffID,
                    tawasulActivityStaff.tawasulPersonID,
                    tawasulActivityStaff.role
                FROM tawasulActivityStaff
                JOIN tawasulActivity ON (tawasulActivity.tawasulActivityID=tawasulActivityStaff.tawasulActivityID)
                JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulActivityStaff.tawasulPersonID) 
                WHERE tawasulActivity.tawasulActivityCategoryID=:tawasulActivityCategoryID
                AND tawasulActivityStaff.tawasulPersonID=:tawasulPersonID";

        return $this->db()->select($sql, $data);
    }

    public function deleteStaffNotInList($tawasulActivityID, $personIDList)
    {
        $personIDList = is_array($personIDList) ? implode(',', $personIDList) : $personIDList;

        $data = ['tawasulActivityID' => $tawasulActivityID, 'personIDList' => $personIDList];
        $sql = "DELETE FROM tawasulActivityStaff WHERE tawasulActivityID=:tawasulActivityID AND NOT FIND_IN_SET(tawasulPersonID, :personIDList)";

        return $this->db()->delete($sql, $data);
    }

    public function selectActivityStaffByID($tawasulActivityID, $tawasulPersonID) {
        return $this->selectBy([
            'tawasulPersonID' 	=> $tawasulPersonID,
            'tawasulActivityID' 	=> $tawasulActivityID
        ]);
    }

    public function insertActivityStaff($tawasulActivityID, $tawasulPersonID, $role) {
        return $this->insert([
            'tawasulPersonID' 	=> $tawasulPersonID,
            'tawasulActivityID' 	=> $tawasulActivityID,
            'role'				=> $role
        ]);
    }
}
