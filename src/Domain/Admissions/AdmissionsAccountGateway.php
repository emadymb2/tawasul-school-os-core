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
 * Admissions Account
 *
 * @version v24
 * @since   v24
 */
class AdmissionsAccountGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulAdmissionsAccount';
    private static $primaryKey = 'tawasulAdmissionsAccountID';

    private static $searchableColumns = ['tawasulAdmissionsAccount.email', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulRole.name', 'tawasulFamily.name'];

    public function queryAdmissionsAccounts(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->cols([
                'tawasulAdmissionsAccount.tawasulAdmissionsAccountID',
                'tawasulAdmissionsAccount.email',
                'tawasulAdmissionsAccount.timestampCreated',
                'tawasulAdmissionsAccount.timestampActive',
                'tawasulPerson.tawasulPersonID',
                'tawasulPerson.title',
                'tawasulPerson.surname',
                'tawasulPerson.preferredName',
                'tawasulRole.name as roleName',
                'tawasulFamily.name as familyName',
                'tawasulFamily.tawasulFamilyID as tawasulFamilyID',
                "(COUNT(DISTINCT tawasulAdmissionsApplicationID)) as applicationCount",
                "(COUNT(DISTINCT tawasulFormSubmissionID)) as formCount",
                '(CASE WHEN tawasulPerson.tawasulPersonID IS NULL THEN 1 ELSE 0 END) as sortOrder',
            ])
            ->from($this->getTableName())
            ->leftJoin('tawasulAdmissionsApplication', 'tawasulAdmissionsApplication.foreignTable="tawasulAdmissionsAccount" AND tawasulAdmissionsApplication.foreignTableID=tawasulAdmissionsAccount.tawasulAdmissionsAccountID')
            ->leftJoin('tawasulFormSubmission', 'tawasulFormSubmission.foreignTable="tawasulAdmissionsAccount" AND tawasulFormSubmission.foreignTableID=tawasulAdmissionsAccount.tawasulAdmissionsAccountID')
            ->leftJoin('tawasulPerson', 'tawasulAdmissionsAccount.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulRole', 'tawasulRole.tawasulRoleID=tawasulPerson.tawasulRoleIDPrimary')
            ->leftJoin('tawasulFamily', 'tawasulAdmissionsAccount.tawasulFamilyID=tawasulFamily.tawasulFamilyID')
            ->groupBy(['tawasulAdmissionsAccount.tawasulAdmissionsAccountID']);

        return $this->runQuery($query, $criteria);
    }

    public function getAccountByEmail($email)
    {
        $data = ['email' => $email];
        $sql = "SELECT * FROM tawasulAdmissionsAccount WHERE email=:email";

        return $this->db()->selectOne($sql, $data);
    }

    public function getAccountByAccessID($accessID)
    {
        $data = ['accessID' => $accessID];
        $sql = "SELECT * FROM tawasulAdmissionsAccount WHERE accessID=:accessID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getAccountByPerson($tawasulPersonID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT * FROM tawasulAdmissionsAccount WHERE tawasulPersonID=:tawasulPersonID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getAccountByAccessToken($accessID, $accessToken)
    {
        $data = ['accessID' => $accessID, 'accessToken' => $accessToken];
        $sql = "SELECT * FROM tawasulAdmissionsAccount WHERE accessID=:accessID AND accessToken=:accessToken AND CURRENT_TIMESTAMP() <= timestampTokenExpire";

        return $this->db()->selectOne($sql, $data);
    }

    public function getUniqueAccessID($salt)
    {
        do {
            $accessID = hash('sha256', microtime().$salt);
            $checkID = $this->selectBy(['accessID' => $accessID])->fetch();
        } while (!empty($checkID));

        return $accessID;
    }

    public function getUniqueAccessToken($salt)
    {
        do {
            $accessToken = hash('sha256', microtime().$salt);
            $checkToken = $this->selectBy(['accessToken' => $accessToken])->fetch();
        } while (!empty($checkToken));

        return substr($accessToken, 0, 32);
    }
}
