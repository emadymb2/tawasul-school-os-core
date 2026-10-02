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

namespace TawasulOS\Domain\FormGroups;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * FormGroup Gateway
 *
 * @version v16
 * @since   v16
 */
class FormGroupGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulFormGroup';
    private static $primaryKey = 'tawasulFormGroupID';
    private static $searchableColumns = [];

    public function queryFormGroups(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulSchoolYear.sequenceNumber',
                'tawasulSchoolYear.tawasulSchoolYearID',
                'tawasulFormGroup.tawasulFormGroupID',
                'tawasulSchoolYear.name as yearName',
                'tawasulFormGroup.name',
                'tawasulFormGroup.nameShort',
                'tawasulFormGroup.tawasulPersonIDTutor',
                'tawasulFormGroup.tawasulPersonIDTutor2',
                'tawasulFormGroup.tawasulPersonIDTutor3',
                'tawasulSpace.name AS space',
                'tawasulFormGroup.website',
                "LENGTH(tawasulFormGroup.name) as sortOrder"

            ])
            ->innerJoin('tawasulSchoolYear', 'tawasulFormGroup.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID')
            ->leftJoin('tawasulSpace', 'tawasulFormGroup.tawasulSpaceID=tawasulSpace.tawasulSpaceID')
            ->where('tawasulSchoolYear.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    public function selectFormGroupsBySchoolYear($tawasulSchoolYearID)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'today' => date('Y-m-d'));
        $sql = "SELECT tawasulFormGroup.tawasulFormGroupID, tawasulFormGroup.name, tawasulFormGroup.nameShort, tawasulFormGroup.tawasulSpaceID, tawasulSpace.name AS space, tawasulFormGroup.website, tawasulPersonIDTutor, tawasulPersonIDTutor2, tawasulPersonIDTutor3, COUNT(DISTINCT students.tawasulPersonID) as students, (SELECT MAX(sequenceNumber) FROM tawasulYearGroup JOIN tawasulStudentEnrolment ON (tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID) WHERE tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) as sequenceNumber
                FROM tawasulFormGroup
                LEFT JOIN (
                    SELECT tawasulStudentEnrolment.tawasulPersonID, tawasulStudentEnrolment.tawasulFormGroupID FROM tawasulStudentEnrolment
                    JOIN tawasulPerson ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                    WHERE status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL OR dateEnd>=:today)
                    ORDER BY tawasulStudentEnrolment.tawasulYearGroupID
                ) AS students ON (students.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                LEFT JOIN tawasulSpace ON (tawasulFormGroup.tawasulSpaceID=tawasulSpace.tawasulSpaceID)
                WHERE tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID
                GROUP BY tawasulFormGroup.tawasulFormGroupID
                ORDER BY LENGTH(tawasulFormGroup.name), tawasulFormGroup.name";

        return $this->db()->select($sql, $data);
    }

    public function selectFormGroupsBySchoolYearMyChildren($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID, 'today' => date('Y-m-d'));
        $sql = "SELECT tawasulFormGroup.tawasulFormGroupID, tawasulFormGroup.name, tawasulFormGroup.nameShort, tawasulSpace.name AS space, tawasulFormGroup.website, tawasulPersonIDTutor, tawasulPersonIDTutor2, tawasulPersonIDTutor3, COUNT(DISTINCT students.tawasulPersonID) as students, (SELECT MAX(sequenceNumber) FROM tawasulYearGroup JOIN tawasulStudentEnrolment ON (tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID) WHERE tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) as sequenceNumber
                FROM tawasulFormGroup
                JOIN (
                    SELECT tawasulStudentEnrolment.tawasulPersonID, tawasulStudentEnrolment.tawasulFormGroupID FROM tawasulStudentEnrolment
                    JOIN tawasulPerson ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                    WHERE status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL OR dateEnd>=:today)
                    ORDER BY tawasulStudentEnrolment.tawasulYearGroupID
                ) AS students ON (students.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulPersonID=students.tawasulPersonID)
                JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
                JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
                LEFT JOIN tawasulSpace ON (tawasulFormGroup.tawasulSpaceID=tawasulSpace.tawasulSpaceID)
                WHERE tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID
                    AND tawasulFamilyAdult.tawasulPersonID=:tawasulPersonID
                GROUP BY tawasulFormGroup.tawasulFormGroupID
                ORDER BY LENGTH(tawasulFormGroup.name), tawasulFormGroup.name";

        return $this->db()->select($sql, $data);
    }

    public function selectTutorsByFormGroup($tawasulFormGroupID)
    {
        $data = array('tawasulFormGroupID' => $tawasulFormGroupID);
        $sql = "SELECT tawasulPersonID, title, surname, preferredName, email, status
                FROM tawasulFormGroup
                LEFT JOIN tawasulPerson ON ((tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor OR tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor2 OR tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor3) AND tawasulPerson.status='Full')
                WHERE tawasulFormGroup.tawasulFormGroupID=:tawasulFormGroupID
                ORDER BY tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor DESC, tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor2 DESC";

        return $this->db()->select($sql, $data);
    }

    public function selectFormGroupsByTutor($tawasulPersonID)
    {
        $data = array('tawasulPersonID' => $tawasulPersonID);
        $sql = "SELECT tawasulFormGroup.*, tawasulSpace.name as spaceName
                FROM tawasulFormGroup
                LEFT JOIN tawasulSpace ON (tawasulSpace.tawasulSpaceID=tawasulFormGroup.tawasulSpaceID)
                WHERE (tawasulFormGroup.tawasulPersonIDTutor = :tawasulPersonID
                    OR tawasulFormGroup.tawasulPersonIDTutor2 = :tawasulPersonID
                    OR tawasulFormGroup.tawasulPersonIDTutor3 = :tawasulPersonID)
                AND tawasulSchoolYearID=(SELECT tawasulSchoolYearID FROM tawasulSchoolYear WHERE status='Current' LIMIT 1)
                ORDER BY tawasulFormGroup.nameShort";

        return $this->db()->select($sql, $data);
    }

    public function selectTutorsByStudent($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID, 'tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulPerson.tawasulPersonID, tawasulFormGroup.tawasulPersonIDTutor, tawasulFormGroup.tawasulPersonIDTutor2, tawasulFormGroup.tawasulPersonIDTutor3, tawasulPerson.surname, tawasulPerson.preferredName, tawasulStudentEnrolment.tawasulYearGroupID
        FROM tawasulStudentEnrolment
        JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
        LEFT JOIN tawasulPerson ON ((tawasulPerson.tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor AND tawasulPerson.status='Full') OR (tawasulPerson.tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor2 AND tawasulPerson.status='Full') OR (tawasulPerson.tawasulPersonID=tawasulFormGroup.tawasulPersonIDTutor3 AND tawasulPerson.status='Full'))
        WHERE tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID";

        return $this->db()->select($sql, $data);
    }

    public function selectFormGroups()
    {
        $sql = "SELECT tawasulFormGroupID as value, name, tawasulSchoolYearID FROM tawasulFormGroup ORDER BY tawasulSchoolYearID, name";

        return $this->db()->select($sql);
    }

    public function getFormGroupByID($tawasulFormGroupID)
    {
        $data = array('tawasulFormGroupID' => $tawasulFormGroupID);
        $sql = "SELECT *
                FROM tawasulFormGroup
                WHERE tawasulFormGroupID=:tawasulFormGroupID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Take a form group, and return the next one, or false if none.
     *
     * @version v17
     * @since   v17
     *
     * @param int $tawasulFormGroupID
     *
     * @return int|false
     */
    public function getNextFormGroupID($tawasulFormGroupID)
    {
        $sql = 'SELECT tawasulFormGroupIDNext FROM tawasulFormGroup WHERE tawasulFormGroupID=:tawasulFormGroupID';
        return $this->db()->selectOne($sql, [
            'tawasulFormGroupID' => $tawasulFormGroupID,
        ]);
    }          
  
    public function getFormGroupDetailsByID($tawasulFormGroupID)
    {
        $data = ['tawasulFormGroupID' => $tawasulFormGroupID];
        $sql = 'SELECT tawasulSchoolYear.tawasulSchoolYearID, tawasulFormGroupID, tawasulSchoolYear.name as yearName, tawasulFormGroup.name, tawasulFormGroup.nameShort, tawasulPersonIDTutor, tawasulPersonIDTutor2, tawasulPersonIDTutor3, tawasulPersonIDEA, tawasulPersonIDEA2, tawasulPersonIDEA3, tawasulSpace.name AS space, website FROM tawasulFormGroup JOIN tawasulSchoolYear ON (tawasulFormGroup.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) LEFT JOIN tawasulSpace ON (tawasulFormGroup.tawasulSpaceID=tawasulSpace.tawasulSpaceID) WHERE tawasulFormGroupID=:tawasulFormGroupID';
        
        return $this->db()->select($sql, $data);
    }
  
    public function selectFormGroupListBySchoolYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = 'SELECT tawasulFormGroupID AS value, name FROM tawasulFormGroup WHERE tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY name';
        
        return $this->db()->select($sql, $data);
    }

    public function selectFormGroupsByStaff($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ["tawasulSchoolYearID" => $tawasulSchoolYearID, "tawasulPersonID" => $tawasulPersonID];
        $sql = "SELECT tawasulFormGroupID AS value, name FROM tawasulFormGroup WHERE (tawasulPersonIDTutor=:tawasulPersonID OR tawasulPersonIDTutor2=:tawasulPersonID OR tawasulPersonIDTutor3=:tawasulPersonID) AND tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY name";
        
        return $this->db()->select($sql, $data);
    }
  
    public function getFormGroupDetailsByFamilyAdult($tawasulFormGroupID, $tawasulPersonID)
    {
        $data = ['tawasulFormGroupID' => $tawasulFormGroupID, 'tawasulPersonID' => $tawasulPersonID, 'today' => date('Y-m-d')];
        $sql = "SELECT tawasulSchoolYear.tawasulSchoolYearID, tawasulFormGroup.tawasulFormGroupID, tawasulSchoolYear.name as yearName, tawasulFormGroup.name, tawasulFormGroup.nameShort, tawasulPersonIDTutor, tawasulPersonIDTutor2, tawasulPersonIDTutor3, tawasulPersonIDEA, tawasulPersonIDEA2, tawasulPersonIDEA3, tawasulSpace.name AS space, website
        FROM tawasulFormGroup JOIN tawasulSchoolYear ON (tawasulFormGroup.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) 
        JOIN (SELECT tawasulStudentEnrolment.tawasulPersonID, tawasulStudentEnrolment.tawasulFormGroupID FROM tawasulStudentEnrolment JOIN tawasulPerson ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL OR dateEnd>=:today) ORDER BY tawasulStudentEnrolment.tawasulYearGroupID) AS students ON (students.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
        JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulPersonID=students.tawasulPersonID)
        JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
        JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID)
        LEFT JOIN tawasulSpace ON (tawasulFormGroup.tawasulSpaceID=tawasulSpace.tawasulSpaceID)
        WHERE tawasulFormGroup.tawasulFormGroupID=:tawasulFormGroupID
        AND tawasulFamilyAdult.tawasulPersonID=:tawasulPersonID";

        return $this->db()->select($sql, $data);
    }

    public function selectFormGroupsByStudent($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ["tawasulSchoolYearID" => $tawasulSchoolYearID, "tawasulPersonID" => $tawasulPersonID];
        $sql = "SELECT tawasulFormGroupID AS value, name FROM tawasulFormGroup JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) WHERE tawasulPersonID=:tawasulPersonID AND tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY name";

        return $this->db()->select($sql, $data);
    }
}
