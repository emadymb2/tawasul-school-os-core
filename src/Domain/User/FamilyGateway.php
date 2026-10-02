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

namespace TawasulOS\Domain\User;

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\ScrubbableGateway;
use TawasulOS\Domain\Traits\Scrubbable;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\Traits\ScrubByFamily;

/**
 * @version v16
 * @since   v16
 */
class FamilyGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByFamily;

    private static $tableName = 'tawasulFamily';
    private static $primaryKey = 'tawasulFamilyID';

    private static $searchableColumns = ['name'];

    private static $scrubbableKey = 'tawasulFamilyID';
    private static $scrubbableColumns = ['nameAddress' => '', 'homeAddress' => '', 'homeAddressDistrict' => '', 'homeAddressCountry' => '', 'status' => 'Other', 'languageHomePrimary' => '', 'languageHomeSecondary' => ''];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryFamilies(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulFamilyID', 'name', 'status'
            ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryFamiliesByStudent(QueryCriteria $criteria, $tawasulPersonID)
    {
        $tawasulPersonIDList = is_array($tawasulPersonID) ? implode(',', $tawasulPersonID) : $tawasulPersonID;

        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulFamily.tawasulFamilyID', 'tawasulFamily.*', "GROUP_CONCAT(DISTINCT tawasulFamilyChild.tawasulPersonID SEPARATOR ',') as tawasulPersonIDList"
            ])
            ->innerJoin('tawasulFamilyChild', 'tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID')
            ->where('FIND_IN_SET(tawasulFamilyChild.tawasulPersonID, :tawasulPersonIDList)')
            ->bindValue('tawasulPersonIDList', $tawasulPersonIDList)
            ->groupBy(['tawasulFamily.tawasulFamilyID']);

        return $this->runQuery($query, $criteria);
    }

    public function queryFamiliesByAdult(QueryCriteria $criteria, $tawasulPersonID)
    {
        $tawasulPersonIDList = is_array($tawasulPersonID) ? implode(',', $tawasulPersonID) : $tawasulPersonID;

        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulFamily.tawasulFamilyID', 'tawasulFamily.*', "GROUP_CONCAT(DISTINCT tawasulFamilyAdult.tawasulPersonID SEPARATOR ',') as tawasulPersonIDList"
            ])
            ->innerJoin('tawasulFamilyAdult', 'tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID')
            ->where('FIND_IN_SET(tawasulFamilyAdult.tawasulPersonID, :tawasulPersonIDList)')
            ->bindValue('tawasulPersonIDList', $tawasulPersonIDList)
            ->groupBy(['tawasulFamily.tawasulFamilyID']);

        return $this->runQuery($query, $criteria);
    }

    public function selectFamiliesWithActiveStudents($tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulFamily.tawasulFamilyID', 'tawasulFamily.name', 'tawasulPerson.tawasulPersonID', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulFormGroup.nameShort as formGroup', 'tawasulFormGroup.tawasulFormGroupID', 'tawasulYearGroup.tawasulYearGroupID'
            ])
            ->innerJoin('tawasulFamilyChild', 'tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulFamilyChild.tawasulPersonID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID')
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("tawasulPerson.status = 'Full'")
            ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)')
            ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
            ->bindValue('today', date('Y-m-d'))
            ->orderBy(['tawasulYearGroup.sequenceNumber', 'tawasulFormGroup.nameShort', 'tawasulPerson.surname', 'tawasulPerson.preferredName']);

        return $this->runSelect($query);
    }

    public function selectAdultsByFamily($tawasulFamilyIDList, $allFields = false)
    {
        $tawasulFamilyIDList = is_array($tawasulFamilyIDList) ? implode(',', $tawasulFamilyIDList) : $tawasulFamilyIDList;

        $query = $this
            ->newSelect()
            ->cols($allFields
                ? ['tawasulFamilyAdult.tawasulFamilyID', 'tawasulFamilyAdult.*', 'tawasulPerson.*']
                : ['tawasulFamilyAdult.tawasulFamilyID', 'tawasulPerson.tawasulPersonID', 'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulPerson.status', 'tawasulPerson.email', 'tawasulPerson.gender'])
            ->from('tawasulFamilyAdult')
            ->innerJoin('tawasulPerson', 'tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->where('FIND_IN_SET(tawasulFamilyAdult.tawasulFamilyID, :tawasulFamilyIDList)')
            ->bindValue('tawasulFamilyIDList', $tawasulFamilyIDList)
            ->orderBy(['tawasulFamilyAdult.contactPriority', 'tawasulPerson.surname', 'tawasulPerson.preferredName']);

        return $this->runSelect($query);
    }

    public function selectChildrenByFamily($tawasulFamilyIDList, $allFields = false)
    {
        $tawasulFamilyIDList = is_array($tawasulFamilyIDList) ? implode(',', $tawasulFamilyIDList) : $tawasulFamilyIDList;

        $query = $this
            ->newSelect()
            ->cols($allFields
                ? ['tawasulFamilyChild.tawasulFamilyID', 'tawasulFamilyChild.*', 'tawasulPerson.*']
                : ['tawasulFamilyChild.tawasulFamilyID', 'tawasulPerson.tawasulPersonID', 'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulPerson.status', 'tawasulPerson.email'])
            ->from('tawasulFamilyChild')
            ->innerJoin('tawasulPerson', 'tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->where('FIND_IN_SET(tawasulFamilyChild.tawasulFamilyID, :tawasulFamilyIDList)')
            ->bindValue('tawasulFamilyIDList', $tawasulFamilyIDList)
            ->orderBy(['tawasulPerson.surname', 'tawasulPerson.preferredName']);

        return $this->runSelect($query);
    }

    public function selectFamilyAdultsByStudent($tawasulPersonID, $allUsers = false)
    {
        $tawasulPersonIDList = is_array($tawasulPersonID) ? implode(',', $tawasulPersonID) : $tawasulPersonID;
        $data = array('tawasulPersonIDList' => $tawasulPersonIDList);
        $sql = "SELECT tawasulFamilyChild.tawasulPersonID, tawasulFamilyAdult.tawasulFamilyID, tawasulPerson.*, tawasulFamilyAdult.childDataAccess, tawasulFamilyAdult.contactEmail, tawasulFamilyAdult.contactCall, GROUP_CONCAT(DISTINCT (CASE WHEN tawasulPersonalDocumentType.name IS NOT NULL THEN tawasulPersonalDocument.country END) SEPARATOR ', ') as citizenship, 'Family' as type, tawasulFamilyRelationship.relationship
            FROM tawasulFamilyChild
            JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamilyChild.tawasulFamilyID)
            JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID)
            LEFT JOIN tawasulFamilyRelationship ON (tawasulFamilyRelationship.tawasulFamilyID=tawasulFamilyChild.tawasulFamilyID && tawasulFamilyRelationship.tawasulPersonID1=tawasulFamilyAdult.tawasulPersonID && tawasulFamilyRelationship.tawasulPersonID2=tawasulFamilyChild.tawasulPersonID)
            LEFT JOIN tawasulPersonalDocument ON (tawasulPersonalDocument.foreignTable='tawasulPerson' AND tawasulPersonalDocument.foreignTableID=tawasulPerson.tawasulPersonID AND tawasulPersonalDocument.country IS NOT NULL)
            LEFT JOIN tawasulPersonalDocumentType ON (tawasulPersonalDocumentType.tawasulPersonalDocumentTypeID=tawasulPersonalDocument.tawasulPersonalDocumentTypeID AND tawasulPersonalDocumentType.document='Passport')
            WHERE FIND_IN_SET(tawasulFamilyChild.tawasulPersonID, :tawasulPersonIDList)";

        if (!$allUsers) $sql .= " AND tawasulPerson.status='Full'";

        $sql .= " GROUP BY tawasulFamilyChild.tawasulPersonID, tawasulFamilyAdult.tawasulFamilyAdultID ORDER BY tawasulFamilyAdult.contactPriority, tawasulPerson.surname, tawasulPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectContactPriority1AdultsByStudent($tawasulPersonID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID];
        $sql = "
            SELECT
                tawasulFamilyAdult.tawasulPersonID,
                tawasulPerson.title,
                tawasulPerson.preferredName,
                tawasulPerson.surname,
                tawasulPerson.status,
                tawasulPerson.email
            FROM tawasulFamilyChild
                JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
                JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
                JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID)
            WHERE
                tawasulFamilyChild.tawasulPersonID=:tawasulPersonID
                AND tawasulFamilyAdult.childDataAccess='Y'
                AND tawasulFamilyAdult.contactPriority=1
                AND tawasulPerson.status='Full'";

        return $this->db()->select($sql, $data);
    }

    public function selectFamiliesByStudent($tawasulPersonID)
    {
        $tawasulPersonIDList = is_array($tawasulPersonID) ? implode(',', $tawasulPersonID) : $tawasulPersonID;
        $data = array('tawasulPersonIDList' => $tawasulPersonIDList);
        $sql = "SELECT tawasulFamilyChild.tawasulPersonID, tawasulFamily.*
            FROM tawasulFamilyChild
            JOIN tawasulFamily ON (tawasulFamily.tawasulFamilyID=tawasulFamilyChild.tawasulFamilyID)
            WHERE FIND_IN_SET(tawasulFamilyChild.tawasulPersonID, :tawasulPersonIDList)
            ORDER BY tawasulFamily.name";

        return $this->db()->select($sql, $data);
    }

    public function selectFamiliesByAdult($tawasulPersonID)
    {
        $tawasulPersonIDList = is_array($tawasulPersonID) ? implode(',', $tawasulPersonID) : $tawasulPersonID;
        $data = array('tawasulPersonIDList' => $tawasulPersonIDList);
        $sql = "SELECT tawasulFamilyAdult.tawasulPersonID, tawasulFamily.*
            FROM tawasulFamilyAdult
            JOIN tawasulFamily ON (tawasulFamily.tawasulFamilyID=tawasulFamilyAdult.tawasulFamilyID)
            WHERE
                FIND_IN_SET(tawasulFamilyAdult.tawasulPersonID, :tawasulPersonIDList)
                AND tawasulFamilyAdult.childDataAccess='Y'
            ORDER BY tawasulFamily.name";

        return $this->db()->select($sql, $data);
    }
}
