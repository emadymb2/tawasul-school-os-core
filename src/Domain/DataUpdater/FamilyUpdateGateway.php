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
use TawasulOS\Domain\Traits\ScrubByFamily;

/**
 * @version v16
 * @since   v16
 */
class FamilyUpdateGateway extends QueryableGateway implements ScrubbableGateway
{
    use TableAware;
    use Scrubbable;
    use ScrubByFamily;

    private static $tableName = 'tawasulFamilyUpdate';
    private static $primaryKey = 'tawasulFamilyUpdateID';

    private static $searchableColumns = ['tawasulFamily.name', 'tawasulFamily.nameAddress'];
    
    private static $scrubbableKey = 'tawasulFamilyID';
    private static $scrubbableColumns = ['nameAddress' => '', 'homeAddress' => '', 'homeAddressDistrict' => '', 'homeAddressCountry' => '', 'status' => 'Other', 'languageHomePrimary' => '', 'languageHomeSecondary' => ''];
    
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
                'tawasulFamilyUpdateID', 'tawasulFamilyUpdate.status', 'tawasulFamilyUpdate.timestamp', 'tawasulFamily.name as familyName',  'tawasulFamily.tawasulFamilyID', 'updater.tawasulPersonID as tawasulPersonIDUpdater', 'updater.title as updaterTitle', 'updater.preferredName as updaterPreferredName', 'updater.surname as updaterSurname'
            ])
            ->leftJoin('tawasulFamily', 'tawasulFamily.tawasulFamilyID=tawasulFamilyUpdate.tawasulFamilyID')
            ->leftJoin('tawasulPerson AS updater', 'updater.tawasulPersonID=tawasulFamilyUpdate.tawasulPersonIDUpdater')
            ->where('tawasulFamilyUpdate.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryFamilyUpdaterHistory(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulYearGroupIDList, $requiredUpdatesByType)
    {
        $tawasulYearGroupIDList = is_array($tawasulYearGroupIDList)? implode(',', $tawasulYearGroupIDList) : $tawasulYearGroupIDList;

        $query = $this
            ->newQuery()
            ->from('tawasulFamily')
            ->cols([
                'tawasulFamily.tawasulFamilyID', 
                'tawasulFamily.name as familyName', 
                'MAX(tawasulFamilyUpdate.timestamp) as familyUpdate', 
                "MAX(IFNULL(tawasulPerson.dateEnd, NOW())) as latestEndDate",
                'tawasulFamilyUpdate.tawasulFamilyUpdateID'
            ])
            ->innerJoin('tawasulFamilyChild', 'tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulFamilyChild.tawasulPersonID')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulFamilyUpdate', 'tawasulFamilyUpdate.tawasulFamilyID=tawasulFamily.tawasulFamilyID')
            ->where("tawasulPerson.status='Full'")
            ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=CURRENT_DATE)')
            ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=CURRENT_DATE)')
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('FIND_IN_SET(tawasulStudentEnrolment.tawasulYearGroupID, :tawasulYearGroupIDList)')
            ->bindValue('tawasulYearGroupIDList', $tawasulYearGroupIDList)
            ->groupBy(['tawasulFamily.tawasulFamilyID'])
            ->having('latestEndDate >= NOW()');

        $criteria->addFilterRules([
            'cutoff' => function ($query, $cutoffDate) use ($requiredUpdatesByType) {
                $havingCutoff = "(tawasulFamilyUpdateID IS NULL OR familyUpdate < :cutoffDate)";

                if (in_array('Personal', $requiredUpdatesByType)) {
                    $query->cols([
                        "MAX(IFNULL(studentUpdate.timestamp, '0000-00-00')) as earliestStudentUpdate", 
                        "MAX(IFNULL(adultUpdate.timestamp, '0000-00-00')) as earliestAdultUpdate"
                    ])
                    ->leftJoin('tawasulPersonUpdate AS studentUpdate', 'studentUpdate.tawasulPersonID=tawasulPerson.tawasulPersonID')
                    ->leftJoin('tawasulFamilyAdult', 'tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID')
                    ->leftJoin('tawasulPerson AS adult', "adult.tawasulPersonID=tawasulFamilyAdult.tawasulPersonID AND adult.status='Full'")
                    ->leftJoin('tawasulPersonUpdate AS adultUpdate', 'adultUpdate.tawasulPersonID=adult.tawasulPersonID');
                    $havingCutoff .= " OR (earliestStudentUpdate < :cutoffDate) OR (earliestAdultUpdate < :cutoffDate)";
                }

                if (in_array('Medical', $requiredUpdatesByType)) {
                    $query->cols([
                        "MAX(IFNULL(medicalUpdate.timestamp, '0000-00-00')) as earliestMedicalUpdate", 
                    ])
                    ->leftJoin('tawasulPersonMedicalUpdate AS medicalUpdate', 'medicalUpdate.tawasulPersonID=tawasulPerson.tawasulPersonID');
                    $havingCutoff .= " OR (earliestMedicalUpdate < :cutoffDate)";
                }

                $query->having($havingCutoff)
                    ->bindValue('cutoffDate', $cutoffDate);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectFamilyAdultUpdatesByFamily($tawasulFamilyIDList)
    {
        $tawasulFamilyIDList = is_array($tawasulFamilyIDList) ? implode(',', $tawasulFamilyIDList) : $tawasulFamilyIDList;
        $data = array('tawasulFamilyIDList' => $tawasulFamilyIDList);
        $sql = "SELECT tawasulFamilyAdult.tawasulFamilyID, tawasulPerson.title, tawasulPerson.preferredName, tawasulPerson.surname, tawasulPerson.status, MAX(tawasulPersonUpdate.timestamp) as personalUpdate, (CASE WHEN tawasulFamilyAdult.contactEmail='Y' THEN tawasulPerson.email ELSE '' END) as email
            FROM tawasulFamilyAdult
            JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID)
            LEFT JOIN tawasulPersonUpdate ON (tawasulPersonUpdate.tawasulPersonID=tawasulPerson.tawasulPersonID)
            WHERE FIND_IN_SET(tawasulFamilyAdult.tawasulFamilyID, :tawasulFamilyIDList) 
            AND tawasulPerson.status='Full'
            GROUP BY tawasulFamilyAdult.tawasulPersonID 
            ORDER BY tawasulFamilyAdult.contactPriority ASC, tawasulPerson.surname, tawasulPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectFamilyChildUpdatesByFamily($tawasulFamilyIDList, $tawasulSchoolYearID)
    {
        $tawasulFamilyIDList = is_array($tawasulFamilyIDList) ? implode(',', $tawasulFamilyIDList) : $tawasulFamilyIDList;
        $data = array('tawasulFamilyIDList' => $tawasulFamilyIDList, 'tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT tawasulFamilyChild.tawasulFamilyID, '' as title, tawasulPerson.preferredName, tawasulPerson.surname, tawasulPerson.status, tawasulFormGroup.nameShort as formGroup, MAX(tawasulPersonUpdate.timestamp) as personalUpdate, MAX(tawasulPersonMedicalUpdate.timestamp) as medicalUpdate, tawasulPerson.dateStart AS dateStart
            FROM tawasulFamilyChild
            JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID)
            JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
            JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID)
            JOIN tawasulYearGroup ON (tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID)
            LEFT JOIN tawasulPersonUpdate ON (tawasulPersonUpdate.tawasulPersonID=tawasulPerson.tawasulPersonID)
            LEFT JOIN tawasulPersonMedicalUpdate ON (tawasulPersonMedicalUpdate.tawasulPersonID=tawasulPerson.tawasulPersonID)
            WHERE FIND_IN_SET(tawasulFamilyChild.tawasulFamilyID, :tawasulFamilyIDList) 
            AND tawasulPerson.status='Full'
            AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
            GROUP BY tawasulFamilyChild.tawasulPersonID 
            ORDER BY tawasulYearGroup.sequenceNumber, tawasulFormGroup.nameShort, tawasulPerson.surname, tawasulPerson.preferredName";

        return $this->db()->select($sql, $data);
    }
}
