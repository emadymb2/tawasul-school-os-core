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

namespace TawasulOS\Domain\Admissions;

use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\Traits\TableAware;

/**
 * Admissions Application Forms
 *
 * @version v24
 * @since   v24
 */
class AdmissionsApplicationGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulAdmissionsApplication';
    private static $primaryKey = 'tawasulAdmissionsApplicationID';

    private static $searchableColumns = ['owner', 'tawasulAdmissionsApplicationID', 'data'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryApplicationsBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID, $type = 'Application')
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->cols([
                'tawasulAdmissionsApplication.tawasulAdmissionsApplicationID',
                'tawasulAdmissionsApplication.tawasulFormID',
                'tawasulAdmissionsApplication.identifier',
                'tawasulAdmissionsApplication.status',
                'tawasulAdmissionsApplication.priority',
                'tawasulAdmissionsApplication.milestones',
                'tawasulAdmissionsApplication.timestampCreated',
                'tawasulForm.tawasulFormID',
                'tawasulForm.name as formName',
                'tawasulAdmissionsAccount.tawasulAdmissionsAccountID',
                'tawasulAdmissionsAccount.accessID',
                'tawasulAdmissionsAccount.email',
                'tawasulYearGroup.name as yearGroup',
                'tawasulFormGroup.name as formGroup',
                'tawasulAdmissionsApplication.data',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.surname")) as studentSurname',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.preferredName")) as studentPreferredName',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.nameInCharacters")) as studentNameInCharacters',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.schoolName1")) as schoolName1',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.dob")) as dob',
             ])
            ->from($this->getTableName())
            ->innerJoin('tawasulForm', 'tawasulAdmissionsApplication.tawasulFormID=tawasulForm.tawasulFormID')
            ->leftJoin('tawasulSchoolYear', 'tawasulSchoolYear.tawasulSchoolYearID=tawasulAdmissionsApplication.tawasulSchoolYearID')
            ->leftJoin('tawasulSchoolYear as schoolYearCheck', 'tawasulAdmissionsApplication.timestampCreated BETWEEN schoolYearCheck.firstDay AND schoolYearCheck.lastDay')
            ->leftJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulAdmissionsApplication.tawasulYearGroupID')
            ->leftJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulAdmissionsApplication.tawasulFormGroupID')
            ->leftJoin('tawasulAdmissionsAccount', "tawasulAdmissionsApplication.foreignTable='tawasulAdmissionsAccount' AND tawasulAdmissionsApplication.foreignTableID=tawasulAdmissionsAccount.tawasulAdmissionsAccountID")
            ->where('((tawasulSchoolYear.tawasulSchoolYearID IS NOT NULL AND tawasulSchoolYear.tawasulSchoolYearID=:tawasulSchoolYearID) OR (tawasulSchoolYear.tawasulSchoolYearID IS NULL AND schoolYearCheck.tawasulSchoolYearID=:tawasulSchoolYearID))')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulForm.type=:type')
            ->bindValue('type', $type);

        $criteria->addFilterRules([
            'admissionsAccount' => function ($query, $admissionsAccount) {
                return $query
                    ->where('tawasulAdmissionsAccount.tawasulAdmissionsAccountID = :admissionsAccount')
                    ->bindValue('admissionsAccount', $admissionsAccount);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('tawasulAdmissionsApplication.status = :status')
                    ->bindValue('status', ucwords($status));
            },
            'paid' => function ($query, $paymentMade) {
                return $query
                    ->where(strtoupper($paymentMade) == 'Y'
                    ? 'tawasulAdmissionsApplication.tawasulPaymentIDSubmit IS NOT NULL'
                    : 'tawasulAdmissionsApplication.tawasulPaymentIDSubmit IS NULL');
            },
            'formGroup' => function ($query, $value) {
                return $query
                    ->where(strtoupper($value) == 'Y'
                        ? 'tawasulAdmissionsApplication.tawasulFormGroupID IS NOT NULL'
                        : 'tawasulAdmissionsApplication.tawasulFormGroupID IS NULL');
            },
            'yearGroup' => function ($query, $tawasulYearGroupID) {
                return $query
                    ->where('tawasulAdmissionsApplication.tawasulYearGroupID = :tawasulYearGroupID')
                    ->bindValue('tawasulYearGroupID', $tawasulYearGroupID);
            },
            'incomplete' => function ($query, $incomplete) {
                return $incomplete != 'N' ? $query : $query
                    ->where("tawasulAdmissionsApplication.status <> 'Incomplete'");
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryApplicationsByForm(QueryCriteria $criteria, $tawasulFormID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['tawasulAdmissionsApplication.tawasulAdmissionsApplicationID', 'tawasulAdmissionsApplication.tawasulFormID', 'tawasulAdmissionsApplication.identifier'])
            ->where('tawasulAdmissionsApplication.tawasulFormID=:tawasulFormID')
            ->bindValue('tawasulFormID', $tawasulFormID);

        return $this->runQuery($query, $criteria);
    }

    public function queryApplicationsByContext(QueryCriteria $criteria, $foreignTable, $foreignTableID) 
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->cols([
                'tawasulAdmissionsApplication.tawasulAdmissionsApplicationID',
                'tawasulAdmissionsApplication.tawasulFormID',
                'tawasulAdmissionsApplication.identifier',
                'tawasulAdmissionsApplication.status',
                'tawasulAdmissionsApplication.timestampCreated',
                'tawasulAdmissionsApplication.tawasulPaymentIDSubmit',
                'tawasulAdmissionsApplication.tawasulPaymentIDProcess',
                'tawasulForm.tawasulFormID',
                'tawasulForm.name as formName',
                'tawasulFormPage.sequenceNumber as page',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.surname")) as studentSurname',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.preferredName")) as studentPreferredName',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.PaySubmissionFeeComplete")) AS submissionFeeComplete',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.PayProcessingFeeComplete")) AS processingFeeComplete',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulForm.config, "$.formSubmissionFee")) as formSubmissionFee',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulForm.config, "$.formProcessingFee")) as formProcessingFee',
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulForm', 'tawasulAdmissionsApplication.tawasulFormID=tawasulForm.tawasulFormID')
            ->leftJoin('tawasulFormPage', 'tawasulAdmissionsApplication.tawasulFormPageID=tawasulFormPage.tawasulFormPageID')
            ->where('tawasulAdmissionsApplication.foreignTable=:foreignTable')
            ->bindValue('foreignTable', $foreignTable)
            ->where('tawasulAdmissionsApplication.foreignTableID=:foreignTableID')
            ->bindValue('foreignTableID', $foreignTableID);

        return $this->runQuery($query, $criteria);
    }

    public function queryFamilyByApplication(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulAdmissionsApplicationID) 
    {
        // Application parents, post-acceptance or pre-existing
        $query = $this
            ->newQuery()
            ->distinct()
            ->cols([
                'tawasulAdmissionsApplication.tawasulAdmissionsApplicationID',
                'tawasulPerson.surname',
                'tawasulPerson.preferredName',
                'tawasulPerson.email',
                'tawasulPerson.tawasulPersonID',
                'tawasulRole.category as roleCategory',
                'tawasulPerson.status',
                'tawasulPerson.image_240',
                'tawasulFamilyRelationship.relationship',
                '"" as yearGroup',
                '"" as applicationID',
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulAdmissionsAccount', 'tawasulAdmissionsApplication.foreignTableID=tawasulAdmissionsAccount.tawasulAdmissionsAccountID')
            ->leftJoin('tawasulFamilyAdult', 'tawasulFamilyAdult.tawasulFamilyID=tawasulAdmissionsAccount.tawasulFamilyID')
            ->leftJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulFamilyAdult.tawasulPersonID OR tawasulPerson.tawasulPersonID=tawasulAdmissionsAccount.tawasulPersonID')
            ->leftJoin('tawasulRole', 'tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary')
            ->leftJoin('tawasulFamilyRelationship', 'tawasulFamilyRelationship.tawasulFamilyID=tawasulAdmissionsAccount.tawasulFamilyID AND tawasulFamilyRelationship.tawasulPersonID1=tawasulPerson.tawasulPersonID AND tawasulFamilyRelationship.tawasulPersonID2=JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.result, "$.tawasulPersonIDStudent"))')
            ->where('tawasulAdmissionsApplication.foreignTable="tawasulAdmissionsAccount"')
            ->where('tawasulAdmissionsApplication.tawasulAdmissionsApplicationID=:tawasulAdmissionsApplicationID')
            ->where('tawasulPerson.tawasulPersonID IS NOT NULL')
            ->bindValue('tawasulAdmissionsApplicationID', $tawasulAdmissionsApplicationID);

        // Application parents, existing family not attached to account, post-acceptance or pre-existing
        $this->unionAllWithCriteria($query, $criteria)
            ->cols([
                'tawasulAdmissionsApplication.tawasulAdmissionsApplicationID',
                'tawasulPerson.surname',
                'tawasulPerson.preferredName',
                'tawasulPerson.email',
                'tawasulPerson.tawasulPersonID',
                'tawasulRole.category as roleCategory',
                'tawasulPerson.status',
                'tawasulPerson.image_240',
                'tawasulFamilyRelationship.relationship',
                '"" as yearGroup',
                '"" as applicationID',
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulAdmissionsAccount', 'tawasulAdmissionsApplication.foreignTableID=tawasulAdmissionsAccount.tawasulAdmissionsAccountID')
            ->leftJoin('tawasulFamilyAdult', 'tawasulFamilyAdult.tawasulFamilyID=JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.tawasulFamilyID"))')
            ->leftJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulFamilyAdult.tawasulPersonID')
            ->leftJoin('tawasulRole', 'tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary')
            ->leftJoin('tawasulFamilyRelationship', 'tawasulFamilyRelationship.tawasulFamilyID=tawasulAdmissionsAccount.tawasulFamilyID AND tawasulFamilyRelationship.tawasulPersonID1=tawasulPerson.tawasulPersonID AND tawasulFamilyRelationship.tawasulPersonID2=JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.result, "$.tawasulPersonIDStudent"))')
            ->where('tawasulAdmissionsApplication.foreignTable="tawasulAdmissionsAccount"')
            ->where('tawasulAdmissionsApplication.tawasulAdmissionsApplicationID=:tawasulAdmissionsApplicationID')
            ->where('tawasulPerson.tawasulPersonID IS NOT NULL')
            ->where('tawasulAdmissionsAccount.tawasulFamilyID IS NULL')
            ->bindValue('tawasulAdmissionsApplicationID', $tawasulAdmissionsApplicationID);

        // Application Parent 1, pre-acceptance
        $this->unionAllWithCriteria($query, $criteria)
            ->cols([
                'tawasulAdmissionsApplication.tawasulAdmissionsApplicationID',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.parent1surname")) as surname',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.parent1preferredName")) as preferredName',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.parent1email")) as email',
                '"" as tawasulPersonID',
                '"Parent" as roleCategory',
                'tawasulAdmissionsApplication.status',
                '"" as image_240',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.parent1relationship")) as relationship',
                '"" as yearGroup',
                '"" as applicationID',
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulAdmissionsAccount', 'tawasulAdmissionsApplication.foreignTableID=tawasulAdmissionsAccount.tawasulAdmissionsAccountID')
            ->where('tawasulAdmissionsAccount.tawasulFamilyID IS NULL')
            ->where('tawasulAdmissionsApplication.status <> "Accepted"')
            ->where('tawasulAdmissionsApplication.foreignTable="tawasulAdmissionsAccount"')
            ->where('tawasulAdmissionsApplication.tawasulAdmissionsApplicationID=:tawasulAdmissionsApplicationID')
            ->where('JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.tawasulPersonIDParent1")) IS NULL')
            ->where('JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.result, "$.tawasulPersonIDParent1")) IS NULL')
            ->bindValue('tawasulAdmissionsApplicationID', $tawasulAdmissionsApplicationID);

        // Application Parent 2, pre-acceptance
        $this->unionAllWithCriteria($query, $criteria)
            ->cols([
                'tawasulAdmissionsApplication.tawasulAdmissionsApplicationID',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.parent2surname")) as surname',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.parent2preferredName")) as preferredName',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.parent2email")) as email',
                '"" as tawasulPersonID',
                '"Parent" as roleCategory',
                'tawasulAdmissionsApplication.status',
                '"" as image_240',
                'JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.parent2relationship")) as relationship',
                '"" as yearGroup',
                '"" as applicationID',
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulAdmissionsAccount', 'tawasulAdmissionsApplication.foreignTableID=tawasulAdmissionsAccount.tawasulAdmissionsAccountID')
            ->where('tawasulAdmissionsAccount.tawasulFamilyID IS NULL')
            ->where('tawasulAdmissionsApplication.status <> "Accepted"')
            ->where('tawasulAdmissionsApplication.foreignTable="tawasulAdmissionsAccount"')
            ->where('tawasulAdmissionsApplication.tawasulAdmissionsApplicationID=:tawasulAdmissionsApplicationID')
            ->where('JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.parent2surname")) IS NOT NULL')
            ->where('JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, "$.tawasulPersonIDParent2")) IS NULL')
            ->where('JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.result, "$.tawasulPersonIDParent2")) IS NULL')
            ->bindValue('tawasulAdmissionsApplicationID', $tawasulAdmissionsApplicationID);

        // Family siblings, same admissions account
        $this->unionAllWithCriteria($query, $criteria)
            ->cols([
                'tawasulAdmissionsApplication.tawasulAdmissionsApplicationID',
                'tawasulPerson.surname',
                'tawasulPerson.preferredName',
                'tawasulPerson.email',
                'tawasulPerson.tawasulPersonID',
                '"Student" as roleCategory',
                'tawasulPerson.status',
                'tawasulPerson.image_240',
                '"Sibling" as relationship',
                'tawasulYearGroup.name as yearGroup',
                '"" as applicationID',
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulAdmissionsAccount', 'tawasulAdmissionsApplication.foreignTableID=tawasulAdmissionsAccount.tawasulAdmissionsAccountID')
            ->innerJoin('tawasulFamily', 'tawasulFamily.tawasulFamilyID=tawasulAdmissionsAccount.tawasulFamilyID')
            ->innerJoin('tawasulFamilyChild', 'tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID')
            ->innerJoin('tawasulPerson', 'tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->leftJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID')
            ->where('tawasulAdmissionsApplication.foreignTable="tawasulAdmissionsAccount"')
            ->where('tawasulAdmissionsApplication.tawasulAdmissionsApplicationID=:tawasulAdmissionsApplicationID')
            ->where('(tawasulAdmissionsApplication.status <> "Accepted" OR JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.result, "$.tawasulPersonIDStudent")) <> tawasulPerson.tawasulPersonID)')
            ->bindValue('tawasulAdmissionsApplicationID', $tawasulAdmissionsApplicationID)
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        // Application siblings, same admissions account (including self)
        $this->unionAllWithCriteria($query, $criteria)
            ->cols([
                'applications.tawasulAdmissionsApplicationID',
                'JSON_UNQUOTE(JSON_EXTRACT(applications.data, "$.surname")) as surname',
                'JSON_UNQUOTE(JSON_EXTRACT(applications.data, "$.preferredName")) as preferredName',
                'JSON_UNQUOTE(JSON_EXTRACT(applications.data, "$.email")) as email',
                '"" as tawasulPersonID',
                '"Student" as roleCategory',
                'applications.status',
                '"" as image_240',
                '"Sibling" as relationship',
                'tawasulYearGroup.name as yearGroup',
                'applications.tawasulAdmissionsApplicationID as applicationID',
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulAdmissionsAccount', 'tawasulAdmissionsApplication.foreignTableID=tawasulAdmissionsAccount.tawasulAdmissionsAccountID')
            ->innerJoin('tawasulAdmissionsApplication as applications', 'applications.foreignTableID=tawasulAdmissionsAccount.tawasulAdmissionsAccountID')
            ->leftJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=applications.tawasulYearGroupID')
            ->where('tawasulAdmissionsApplication.foreignTable="tawasulAdmissionsAccount"')
            ->where('tawasulAdmissionsApplication.tawasulAdmissionsApplicationID=:tawasulAdmissionsApplicationID')
            ->where('applications.status <> "Accepted"')
            ->bindValue('tawasulAdmissionsApplicationID', $tawasulAdmissionsApplicationID);

        return $this->runQuery($query, $criteria);
    }

    public function selectMostRecentApplicationByContext($tawasulFormID, $foreignTable, $foreignTableID) 
    {
        $query = $this
            ->newSelect()
            ->cols([
                'tawasulAdmissionsApplication.tawasulAdmissionsApplicationID',
                'tawasulAdmissionsApplication.data',
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulForm', 'tawasulAdmissionsApplication.tawasulFormID=tawasulForm.tawasulFormID')
            ->where('tawasulAdmissionsApplication.tawasulFormID=:tawasulFormID')
            ->bindValue('tawasulFormID', $tawasulFormID)
            ->where('tawasulAdmissionsApplication.foreignTable=:foreignTable')
            ->bindValue('foreignTable', $foreignTable)
            ->where('tawasulAdmissionsApplication.foreignTableID=:foreignTableID')
            ->bindValue('foreignTableID', $foreignTableID)
            ->orderBy(['tawasulAdmissionsApplication.timestampCreated DESC'])
            ->where("status <> 'Incomplete'")
            ->limit(1);

        return $this->runSelect($query);
    }

    public function getApplicationDetailsByID($tawasulAdmissionsApplicationID)
    {
        $data = ['tawasulAdmissionsApplicationID' => $tawasulAdmissionsApplicationID];
        $sql = "SELECT tawasulAdmissionsApplication.*, 
                    tawasulForm.name as applicationName,
                    tawasulSchoolYear.name as schoolYear,
                    JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, '$.surname')) AS studentSurname,
                    JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, '$.preferredName')) AS studentPreferredName,
                    JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, '$.PaySubmissionFeeComplete')) AS submissionFeeComplete,
                    JSON_UNQUOTE(JSON_EXTRACT(tawasulAdmissionsApplication.data, '$.PayProcessingFeeComplete')) AS processingFeeComplete
                FROM tawasulAdmissionsApplication
                JOIN tawasulForm ON (tawasulAdmissionsApplication.tawasulFormID=tawasulForm.tawasulFormID)
                LEFT JOIN tawasulSchoolYear ON (tawasulAdmissionsApplication.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                WHERE tawasulAdmissionsApplication.tawasulAdmissionsApplicationID=:tawasulAdmissionsApplicationID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getApplicationByIdentifier($tawasulFormID, $identifier, $foreignTable, $foreignTableID, $fields = null)
    {
        return $this->selectBy(['tawasulFormID' => $tawasulFormID, 'identifier' => $identifier, 'foreignTable' => $foreignTable, 'foreignTableID' => $foreignTableID], $fields)->fetch();
    }

    public function getNewUniqueIdentifier(string $tawasulFormID)
    {
        $data = ['tawasulFormID' => $tawasulFormID];

        do {
            $data['identifier'] = bin2hex(random_bytes(20));
        } while (!$this->unique($data, ['tawasulFormID', 'identifier']));

        return $data['identifier'];
    }

}
