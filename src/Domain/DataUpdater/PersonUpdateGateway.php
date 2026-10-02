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

namespace TawasulOS\Domain\DataUpdater;

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
class PersonUpdateGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByPerson;

    private static $tableName = 'tawasulPersonUpdate';
    private static $primaryKey = 'tawasulPersonUpdateID';

    private static $searchableColumns = ['target.surname', 'target.preferredName', 'target.username'];

    private static $scrubbableKey = 'tawasulPersonID';
    private static $scrubbableColumns = ['address1' => '','address1District' => '','address1Country' => '','address2' => '','address2District' => '','address2Country' => '','phone1Type' => '','phone1CountryCode' => '','phone1' => '','phone3Type' => '','phone3CountryCode' => '','phone3' => '','phone2Type' => '','phone2CountryCode' => '','phone2' => '','phone4Type' => '','phone4CountryCode' => '','phone4' => '','languageFirst' => '','languageSecond' => '','languageThird' => '','countryOfBirth' => '','ethnicity' => '','religion' => '','profession'=> null,'employer'=> null,'jobTitle'=> null,'emergency1Name'=> null,'emergency1Number1'=> null,'emergency1Number2'=> null,'emergency1Relationship'=> null,'emergency2Name'=> null,'emergency2Number1'=> null,'emergency2Number2'=> null,'emergency2Relationship'=> null,'vehicleRegistration' => '','fields' => ''];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryDataUpdates(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulPersonUpdateID', 'tawasulPersonUpdate.status', 'tawasulPersonUpdate.timestamp', 'target.preferredName', 'target.surname', 'target.tawasulPersonID as tawasulPersonIDTarget', 'updater.tawasulPersonID as tawasulPersonIDUpdater', 'updater.title as updaterTitle', 'updater.preferredName as updaterPreferredName', 'updater.surname as updaterSurname', 'tawasulRole.category as roleCategory'
            ])
            ->leftJoin('tawasulPerson AS target', 'target.tawasulPersonID=tawasulPersonUpdate.tawasulPersonID')
            ->leftJoin('tawasulPerson AS updater', 'updater.tawasulPersonID=tawasulPersonUpdate.tawasulPersonIDUpdater')
            ->leftJoin('tawasulRole', 'tawasulRole.tawasulRoleID=target.tawasulRoleIDPrimary')
            ->where('tawasulPersonUpdate.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryStudentUpdaterHistory(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonIDList)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.tawasulPersonID', 
                'tawasulPerson.surname', 
                'tawasulPerson.preferredName', 
                'tawasulPerson.tawasulPersonID', 
                'tawasulFormGroup.name as formGroupName', 
                'tawasulPersonUpdate.tawasulPersonUpdateID', 
                'tawasulPersonMedicalUpdate.tawasulPersonMedicalUpdateID', 
                "MAX(tawasulPersonUpdate.timestamp) as personalUpdate", 
                "MAX(tawasulPersonMedicalUpdate.timestamp) as medicalUpdate"
            ])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->leftJoin('tawasulPersonUpdate', 'tawasulPersonUpdate.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulPersonMedicalUpdate', 'tawasulPersonMedicalUpdate.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->where("tawasulPerson.status = 'Full'")
            ->where("FIND_IN_SET(tawasulPerson.tawasulPersonID, :tawasulPersonIDList)")
            ->where("tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID")
            ->bindValue('tawasulPersonIDList', implode(',', $tawasulPersonIDList))
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->groupBy(['tawasulPerson.tawasulPersonID'])
            ;

        $criteria->addFilterRules([
            'cutoff' => function ($query, $cutoffDate) {
                $query->having("((tawasulPersonUpdateID IS NULL OR personalUpdate < :cutoffDate)
                    OR (tawasulPersonMedicalUpdateID IS NULL OR medicalUpdate < :cutoffDate))");
                $query->bindValue('cutoffDate', $cutoffDate);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectParentEmailsByPersonID($tawasulPersonIDList)
    {
        $tawasulPersonIDList = is_array($tawasulPersonIDList) ? implode(',', $tawasulPersonIDList) : $tawasulPersonIDList;
        $data = array('tawasulPersonIDList' => $tawasulPersonIDList);
        $sql = "SELECT tawasulFamilyChild.tawasulPersonID, adult.email 
            FROM tawasulFamilyChild
            LEFT JOIN tawasulFamilyAdult ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamilyAdult.tawasulFamilyID)
            LEFT JOIN tawasulPerson as adult ON (adult.tawasulPersonID=tawasulFamilyAdult.tawasulPersonID)
            WHERE FIND_IN_SET(tawasulFamilyChild.tawasulPersonID, :tawasulPersonIDList)
            AND adult.status='Full' AND adult.email <> ''
            AND tawasulFamilyAdult.contactEmail<>'N' 
            AND tawasulFamilyAdult.childDataAccess='Y'
            ORDER BY tawasulFamilyAdult.contactPriority, adult.surname, adult.preferredName";

        return $this->db()->select($sql, $data);
    }
}
