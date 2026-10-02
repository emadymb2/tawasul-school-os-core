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
 * Staff Gateway
 *
 * @version v16
 * @since   v16
 */
class StaffGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulStaff';
    private static $primaryKey = 'tawasulStaffID';

    private static $searchableColumns = ['preferredName', 'surname', 'username', 'tawasulPerson.nameInCharacters', 'tawasulStaff.jobTitle'];

    /**
     * Queries the list of users for the Manage Staff page.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    /**
     * @param QueryCriteria $criteria
     * @param string $branchCond Optional condition from
     *                         tosBranchScope() scoping the query to one branch.
     * @param array  $branchParams Bind parameters for $branchCond.
     * @return DataSet
     */
    public function queryAllStaff(QueryCriteria $criteria, $tawasulSchoolYearID = null, $branchCond = '', $branchParams = [])
    {
        $biographicalGroupingOrder = '';
        
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulPerson.tawasulPersonID', 'tawasulPerson.title', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.status', 'tawasulPerson.username', 'tawasulPerson.image_240', 'tawasulPerson.email', 'tawasulPerson.phone1', 'tawasulPerson.phone1Type', 'tawasulPerson.phone1CountryCode', 'tawasulPerson.phone2', 'tawasulPerson.phone2Type', 'tawasulPerson.phone2CountryCode',
                'tawasulStaff.tawasulStaffID', 'tawasulStaff.initials', 'tawasulStaff.type', 'tawasulStaff.jobTitle', 'tawasulStaff.biography', 'tawasulStaff.qualifications', 'tawasulStaff.countryOfOrigin','tawasulStaff.biographicalGrouping', 'tawasulStaff.biographicalGroupingPriority'
            ])
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulStaff.tawasulPersonID');

        if ($branchCond !== '') {
            $query->where($branchCond, $branchParams);
        }

        if (!$criteria->hasFilter('all')) {
            $query->where('tawasulPerson.status = "Full"');
        }

        if (!empty($tawasulSchoolYearID)) {
            $biographicalGroupingOrder = $this->db()->selectOne("SELECT value FROM tawasulSetting WHERE scope='Staff' AND name='biographicalGroupingOrder'");

            $query->cols([
                'tawasulFormGroup.name AS formGroupName',
                "GROUP_CONCAT(DISTINCT tawasulSpace.name ORDER BY tawasulSpace.name SEPARATOR '<br/>') as facility",
                "GROUP_CONCAT(DISTINCT tawasulSpace.phoneInternal ORDER BY tawasulSpace.name SEPARATOR '<br/>') as extension",
                "GROUP_CONCAT(DISTINCT tawasulDepartment.name ORDER BY tawasulDepartment.name SEPARATOR '<br/>') as department",
                "(CASE WHEN FIND_IN_SET(tawasulStaff.biographicalGrouping, :biographicalGroupingSortOrder) > 0 THEN FIND_IN_SET(tawasulStaff.biographicalGrouping, :biographicalGroupingSortOrder) WHEN tawasulStaff.biographicalGrouping <> '' THEN 998 ELSE 999 END) AS biographicalGroupingOrder",
            ])
            ->leftJoin('tawasulFormGroup', '((tawasulFormGroup.tawasulPersonIDTutor=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor3=tawasulPerson.tawasulPersonID) AND tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID)')
            ->leftJoin('tawasulSpacePerson', 'tawasulSpacePerson.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulSpace', '(tawasulSpace.tawasulSpaceID=tawasulSpacePerson.tawasulSpaceID OR tawasulSpace.tawasulSpaceID=tawasulFormGroup.tawasulSpaceID)')
            ->leftJoin('tawasulDepartmentStaff', 'tawasulDepartmentStaff.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulDepartment', 'tawasulDepartment.tawasulDepartmentID=tawasulDepartmentStaff.tawasulDepartmentID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->bindValue('biographicalGroupingSortOrder', !empty($biographicalGroupingOrder) ? $biographicalGroupingOrder : 'Default,Test')
            ->groupBy(['tawasulPerson.tawasulPersonID']);
        }

        $criteria->addFilterRules([
            'type' => function ($query, $type) {
                return $query
                    ->where('tawasulStaff.type = :type')
                    ->bindValue('type', ucfirst($type));
            },

            'biographicalGrouping' => function ($query, $grouping) {
                return $query
                    ->where('tawasulStaff.biographicalGrouping = :grouping')
                    ->bindValue('grouping', $grouping);
            },

            'biographicalGroupingSort' => function ($query, $group) use ($biographicalGroupingOrder) {
                if (!empty($biographicalGroupingOrder)) {
                    return $query->cols(["(CASE WHEN FIND_IN_SET(tawasulStaff.biographicalGrouping, :biographicalGroupingSortOrder) > 0 THEN FIND_IN_SET(tawasulStaff.biographicalGrouping, :biographicalGroupingSortOrder) WHEN tawasulStaff.biographicalGrouping <> '' THEN 998 ELSE 999 END) AS biographicalGroupingOrder"])
                        ->bindValue('biographicalGroupingSortOrder', $biographicalGroupingOrder)
                        ->orderBy(['biographicalGroupingOrder',  'biographicalGrouping', 'biographicalGroupingPriority DESC', 'surname', 'preferredName']);
                } else {
                    return $query->orderBy(['(biographicalGrouping="Leadership Team") DESC',  'biographicalGrouping', 'biographicalGroupingPriority DESC', 'surname', 'preferredName']);
                }
                
            },

            'status' => function ($query, $status) {
                return $query
                    ->where('tawasulPerson.status = :status')
                    ->bindValue('status', ucfirst($status));
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectStaffByID($tawasulPersonID, $type = null)
    {
        $tawasulPersonIDList = is_array($tawasulPersonID) ? implode(',', $tawasulPersonID) : $tawasulPersonID;

        $data = array('tawasulPersonIDList' => $tawasulPersonIDList);
        $sql = "SELECT tawasulPerson.tawasulPersonID, tawasulPerson.title, tawasulPerson.preferredName, tawasulPerson.surname, tawasulPerson.image_240, tawasulStaff.type, tawasulStaff.jobTitle, tawasulPerson.username
                FROM tawasulPerson
                LEFT JOIN tawasulStaff ON (tawasulPerson.tawasulPersonID=tawasulStaff.tawasulPersonID)
                WHERE FIND_IN_SET(tawasulPerson.tawasulPersonID, :tawasulPersonIDList)
                AND tawasulPerson.status='Full'";

        if (!empty($type)) $sql .= " AND tawasulStaff.type='Teaching'";

        $sql .= " ORDER BY surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectStaffByStaffID($tawasulStaffID) {
        $data = array('tawasulStaffID' => $tawasulStaffID);
        $sql = 'SELECT tawasulStaff.*, title, surname, preferredName, initials, dateStart, dateEnd FROM tawasulStaff JOIN tawasulPerson ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulStaffID=:tawasulStaffID';

        return $this->db()->select($sql, $data);
    }

    public function selectPotentialStaff() {
        $sql = "SELECT tawasulPerson.tawasulPersonID
            FROM tawasulPerson 
            JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll))
            LEFT JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) 
            WHERE tawasulStaff.tawasulStaffID IS NULL
            AND tawasulRole.category='Staff'
            GROUP BY tawasulPerson.tawasulPersonID";

        return $this->db()->select($sql);
    }

    public function getIsPreferredNameUnique($preferredName)
    {
        $data = array('preferredName' => $preferredName);
        $sql = "SELECT COUNT(*) = 1
                FROM tawasulStaff 
                JOIN tawasulPerson ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID) 
                WHERE tawasulPerson.preferredName=:preferredName
                AND tawasulPerson.status='Full'
                GROUP BY preferredName";

        return $this->db()->selectOne($sql, $data);
    }
}
