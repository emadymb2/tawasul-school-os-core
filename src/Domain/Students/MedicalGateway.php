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

namespace TawasulOS\Domain\Students;

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\ScrubbableGateway;
use TawasulOS\Domain\Traits\Scrubbable;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\Traits\ScrubByPerson;

/**
 * @version v16
 * @since   v16
 */
class MedicalGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByPerson;

    private static $tableName = 'tawasulPersonMedical';
    private static $primaryKey = 'tawasulPersonMedicalID';

    private static $searchableColumns = ['preferredName', 'surname', 'username'];

    private static $scrubbableKey = 'tawasulPersonID';
    private static $scrubbableColumns = ['longTermMedication' => '','longTermMedicationDetails' => '','comment' => '', 'fields' => ''];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryMedicalFormsBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulPersonMedicalID', 'longTermMedication', 'longTermMedicationDetails', 'comment', 'tawasulPerson.tawasulPersonID', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulFormGroup.name as formGroup', '(SELECT COUNT(*) FROM tawasulPersonMedicalCondition WHERE tawasulPersonMedicalCondition.tawasulPersonMedicalID=tawasulPersonMedical.tawasulPersonMedicalID) as conditionCount'
            ])
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulPersonMedical.tawasulPersonID')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->where("tawasulPerson.status = 'Full'")
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    public function selectMedicalConditionsByID($tawasulPersonMedicalID)
    {
        $tawasulPersonMedicalID = is_array($tawasulPersonMedicalID) ? implode(',', $tawasulPersonMedicalID) : $tawasulPersonMedicalID;

        $data = array('tawasulPersonMedicalID' => $tawasulPersonMedicalID);
        $sql = "SELECT tawasulPersonMedicalCondition.tawasulPersonMedicalID, tawasulPersonMedicalCondition.*, tawasulAlertLevel.name AS risk, tawasulAlertLevel.color as alertColor, (CASE WHEN tawasulMedicalCondition.tawasulMedicalConditionID IS NOT NULL THEN tawasulMedicalCondition.name ELSE tawasulPersonMedicalCondition.name END) as name , tawasulMedicalCondition.description
                FROM tawasulPersonMedicalCondition
                JOIN tawasulAlertLevel ON (tawasulPersonMedicalCondition.tawasulAlertLevelID=tawasulAlertLevel.tawasulAlertLevelID)
                LEFT JOIN tawasulMedicalCondition ON (tawasulMedicalCondition.tawasulMedicalConditionID=tawasulPersonMedicalCondition.name OR tawasulMedicalCondition.name=tawasulPersonMedicalCondition.name)
                WHERE FIND_IN_SET(tawasulPersonMedicalCondition.tawasulPersonMedicalID, :tawasulPersonMedicalID)
                ORDER BY tawasulAlertLevel.sequenceNumber DESC, tawasulPersonMedicalCondition.name";

        return $this->db()->select($sql, $data);
    }

    public function getMedicalFormByPerson($tawasulPersonID)
    {
        $data = array('tawasulPersonID' => $tawasulPersonID);
        $sql = "SELECT tawasulPersonMedical.*, surname, preferredName
                FROM tawasulPersonMedical
                JOIN tawasulPerson ON (tawasulPersonMedical.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE tawasulPersonMedical.tawasulPersonID=:tawasulPersonID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getMedicalFormByID($tawasulPersonMedicalID)
    {
        $data = array('tawasulPersonMedicalID' => $tawasulPersonMedicalID);
        $sql = "SELECT tawasulPersonMedical.*, surname, preferredName
                FROM tawasulPersonMedical
                JOIN tawasulPerson ON (tawasulPersonMedical.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE tawasulPersonMedical.tawasulPersonMedicalID=:tawasulPersonMedicalID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getMedicalConditionByID($tawasulPersonMedicalConditionID)
    {
        $data = array('tawasulPersonMedicalConditionID' => $tawasulPersonMedicalConditionID);
        $sql = "SELECT tawasulPersonMedicalCondition.*, (CASE WHEN tawasulMedicalCondition.tawasulMedicalConditionID IS NOT NULL THEN tawasulMedicalCondition.name ELSE tawasulPersonMedicalCondition.name END) as name, surname, preferredName, tawasulPerson.tawasulPersonID
                FROM tawasulPersonMedicalCondition
                JOIN tawasulPersonMedical ON (tawasulPersonMedicalCondition.tawasulPersonMedicalID=tawasulPersonMedical.tawasulPersonMedicalID)
                JOIN tawasulPerson ON (tawasulPersonMedical.tawasulPersonID=tawasulPerson.tawasulPersonID)
                LEFT JOIN tawasulMedicalCondition ON (tawasulMedicalCondition.tawasulMedicalConditionID=tawasulPersonMedicalCondition.name)
                WHERE tawasulPersonMedicalConditionID=:tawasulPersonMedicalConditionID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get the risk level of the highest-risk condition for an individual.
     *
     * @version  v25
     * @since    v25
     *
     * @param int   $tawasulPersonID  The person ID.
     *
     * @return array  An array of fields in the medical alert information of the person,
     *                or an empty array if none found.
     */
    public function getHighestMedicalRisk($tawasulPersonID)
    {
        $sql = "SELECT tawasulAlertLevel.name as level, tawasulAlertLevel.* FROM tawasulPersonMedical 
            JOIN tawasulPersonMedicalCondition ON (tawasulPersonMedical.tawasulPersonMedicalID=tawasulPersonMedicalCondition.tawasulPersonMedicalID) 
            JOIN tawasulAlertLevel ON (tawasulPersonMedicalCondition.tawasulAlertLevelID=tawasulAlertLevel.tawasulAlertLevelID) 
            WHERE tawasulPersonID=:tawasulPersonID 
            ORDER BY tawasulAlertLevel.sequenceNumber DESC";
        return $this->db()->selectOne($sql, ['tawasulPersonID' => $tawasulPersonID]);
    }
}
