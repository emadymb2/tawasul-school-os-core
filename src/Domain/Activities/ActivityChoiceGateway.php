<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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

class ActivityChoiceGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulActivityChoice';
    private static $primaryKey = 'tawasulActivityChoiceID';
    private static $searchableColumns = ['tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulActivityCategory.name', 'tawasulActivity.name'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryChoices(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols([
                'tawasulActivityCategory.tawasulActivityCategoryID',
                'tawasulActivityCategory.name as categoryName',
                'tawasulActivityCategory.nameShort as categoryNameShort',
                'tawasulActivityChoice.tawasulPersonID',
                'tawasulActivityChoice.timestampModified',
                'tawasulPerson.preferredName',
                'tawasulPerson.surname',
                'tawasulPerson.image_240',
                'tawasulFormGroup.nameShort as formGroup',
                'tawasulYearGroup.nameShort as yearGroup',
                "GROUP_CONCAT(tawasulActivity.name ORDER BY tawasulActivityChoice.choice SEPARATOR ',') as choices",
                "GROUP_CONCAT(CONCAT(tawasulActivityChoice.choice, ':', tawasulActivity.name) ORDER BY tawasulActivityChoice.choice SEPARATOR ',') as choiceList",
                "(CASE WHEN tawasulActivityStudent.tawasulActivityStudentID IS NOT NULL THEN enrolledActivity.name ELSE '' END) as enrolledActivity"
                
            ])
            ->innerJoin('tawasulActivity', 'tawasulActivity.tawasulActivityID=tawasulActivityChoice.tawasulActivityID')
            ->innerJoin('tawasulActivityCategory', 'tawasulActivityCategory.tawasulActivityCategoryID=tawasulActivity.tawasulActivityCategoryID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulActivityChoice.tawasulPersonID')
            ->leftJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulActivityCategory.tawasulSchoolYearID')
            ->leftJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->leftJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID')
            ->leftJoin('tawasulActivityStudent', 'tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID')
            ->leftJoin('tawasulActivity as enrolledActivity', 'enrolledActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID')
            ->where('tawasulActivityCategory.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->groupBy(['tawasulActivityChoice.tawasulPersonID', 'tawasulActivityCategory.tawasulActivityCategoryID']);


        $criteria->addFilterRules([
            'category' => function ($query, $tawasulActivityCategoryID) {
                return $query
                    ->where('tawasulActivityCategory.tawasulActivityCategoryID = :tawasulActivityCategoryID')
                    ->bindValue('tawasulActivityCategoryID', $tawasulActivityCategoryID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryChoicesByPerson(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols([
                'tawasulActivityCategory.tawasulActivityCategoryID',
                'tawasulActivityCategory.name as categoryName',
                'tawasulActivityCategory.nameShort as categoryNameShort',
                'tawasulActivityChoice.tawasulPersonID',
                'tawasulActivityChoice.timestampModified',
                'tawasulPerson.preferredName',
                'tawasulPerson.surname',
                'tawasulPerson.image_240',
                "GROUP_CONCAT(tawasulActivity.name ORDER BY tawasulActivityChoice.choice SEPARATOR ',') as choices",
                
            ])
            ->innerJoin('tawasulActivity', 'tawasulActivity.tawasulActivityID=tawasulActivityChoice.tawasulActivityID')
            ->innerJoin('tawasulActivityCategory', 'tawasulActivityCategory.tawasulActivityCategoryID=tawasulActivity.tawasulActivityCategoryID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulActivityChoice.tawasulPersonID')
            ->where('tawasulActivityCategory.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulActivityChoice.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->groupBy(['tawasulActivityChoice.tawasulPersonID']);

        return $this->runQuery($query, $criteria);
    }

    public function queryNotSignedUpStudentsByCategory($criteria, $tawasulActivityCategoryID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulActivityCategory')
            ->cols([
                'tawasulStudentEnrolment.tawasulPersonID as groupBy',
                '0 as tawasulActivityID',
                'tawasulActivityCategory.tawasulActivityCategoryID',
                'tawasulActivityCategory.name as categoryName',
                'tawasulActivityCategory.nameShort as categoryNameShort',
                'tawasulStudentEnrolment.tawasulPersonID',
                'tawasulPerson.surname',
                'tawasulPerson.preferredName',
                'tawasulPerson.email',
                'tawasulPerson.image_240',
                'tawasulFormGroup.name as formGroup',
                'tawasulYearGroup.name as yearGroup',
                'tawasulYearGroup.sequenceNumber as yearGroupSequence',
            ])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulSchoolYearID=tawasulActivityCategory.tawasulSchoolYearID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')

            ->leftJoin('tawasulActivityChoice', 'tawasulActivityChoice.tawasulActivityCategoryID=tawasulActivityCategory.tawasulActivityCategoryID AND tawasulActivityChoice.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulActivity', 'tawasulActivity.tawasulActivityID=tawasulActivityChoice.tawasulActivityID')

            ->where('tawasulActivityCategory.tawasulActivityCategoryID=:tawasulActivityCategoryID')
            ->bindValue('tawasulActivityCategoryID', $tawasulActivityCategoryID)
            ->where("tawasulPerson.status = 'Full'")
            ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)')
            ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
            ->bindValue('today', date('Y-m-d'))
            ->where('tawasulActivityChoice.tawasulActivityChoiceID IS NULL')
            ->groupBy(['tawasulPerson.tawasulPersonID']);

        return $this->runQuery($query, $criteria);
    }

    public function selectChoiceCountsByCategory($tawasulActivityCategoryID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID, 'today' => date('Y-m-d')];
        $sql = "SELECT tawasulActivity.tawasulActivityID as groupBy,
                    tawasulActivity.tawasulActivityID,
                    COUNT(DISTINCT CASE WHEN tawasulActivityChoice.choice=1 AND tawasulPerson.tawasulPersonID IS NOT NULL THEN tawasulActivityChoice.tawasulActivityChoiceID END) as choice1,
                    COUNT(DISTINCT CASE WHEN tawasulActivityChoice.choice=2 AND tawasulPerson.tawasulPersonID IS NOT NULL THEN tawasulActivityChoice.tawasulActivityChoiceID END) as choice2,
                    COUNT(DISTINCT CASE WHEN tawasulActivityChoice.choice=3 AND tawasulPerson.tawasulPersonID IS NOT NULL THEN tawasulActivityChoice.tawasulActivityChoiceID END) as choice3,
                    COUNT(DISTINCT CASE WHEN tawasulActivityChoice.choice=4 AND tawasulPerson.tawasulPersonID IS NOT NULL THEN tawasulActivityChoice.tawasulActivityChoiceID END) as choice4,
                    COUNT(DISTINCT CASE WHEN tawasulActivityChoice.choice=5 AND tawasulPerson.tawasulPersonID IS NOT NULL THEN tawasulActivityChoice.tawasulActivityChoiceID END) as choice5
                FROM tawasulActivity
                LEFT JOIN tawasulActivityChoice ON (tawasulActivityChoice.tawasulActivityID=tawasulActivity.tawasulActivityID)
                LEFT JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulActivityChoice.tawasulPersonID AND tawasulPerson.status = 'Full' AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)
                AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today))
                WHERE tawasulActivity.tawasulActivityCategoryID=:tawasulActivityCategoryID
                GROUP BY tawasulActivity.tawasulActivityID";

        return $this->db()->select($sql, $data);
    }

    public function selectChoicesByCategory($tawasulActivityCategoryID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID, 'today' => date('Y-m-d')];
        $sql = "SELECT tawasulActivityChoice.tawasulPersonID as groupBy,
                    tawasulActivityChoice.tawasulActivityChoiceID as enrolmentID,
                    tawasulActivityChoice.tawasulPersonID,
                    tawasulActivityChoice.timestampCreated,
                    tawasulPerson.surname,
                    tawasulPerson.preferredName,
                    tawasulFormGroup.name as formGroup,
                    tawasulYearGroup.sequenceNumber as yearGroupSequence,
                    MIN(CASE WHEN tawasulActivityChoice.choice=1 THEN tawasulActivityChoice.tawasulActivityID END) as choice1,
                    MIN(CASE WHEN tawasulActivityChoice.choice=2 THEN tawasulActivityChoice.tawasulActivityID END) as choice2,
                    MIN(CASE WHEN tawasulActivityChoice.choice=3 THEN tawasulActivityChoice.tawasulActivityID END) as choice3,
                    MIN(CASE WHEN tawasulActivityChoice.choice=4 THEN tawasulActivityChoice.tawasulActivityID END) as choice4,
                    MIN(CASE WHEN tawasulActivityChoice.choice=5 THEN tawasulActivityChoice.tawasulActivityID END) as choice5
                FROM tawasulActivityChoice
                JOIN tawasulActivityCategory ON (tawasulActivityCategory.tawasulActivityCategoryID=tawasulActivityChoice.tawasulActivityCategoryID)
                JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulActivityChoice.tawasulPersonID)
                LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulActivityCategory.tawasulSchoolYearID)
                LEFT JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID)
                LEFT JOIN tawasulYearGroup ON (tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID)
                WHERE tawasulActivityCategory.tawasulActivityCategoryID=:tawasulActivityCategoryID
                AND tawasulPerson.status = 'Full'
                AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)
                AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)
                GROUP BY tawasulActivityChoice.tawasulPersonID
                ORDER BY tawasulFormGroup.name, tawasulPerson.surname, tawasulPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectChoiceWeightingByCategory($tawasulActivityCategoryID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID];
        $sql = "SELECT tawasulActivityChoice.tawasulPersonID as groupBy,
                    tawasulActivityChoice.tawasulPersonID,
                    SUM(pastChoice.choice) as choiceCount,
                    COUNT(DISTINCT pastChoice.tawasulActivityCategoryID) as categoryCount
                FROM tawasulActivityChoice
                JOIN tawasulActivityCategory ON (tawasulActivityCategory.tawasulActivityCategoryID=tawasulActivityChoice.tawasulActivityCategoryID)
                LEFT JOIN tawasulActivityStudent AS pastEnrolment ON (pastEnrolment.tawasulPersonID=tawasulActivityChoice.tawasulPersonID AND pastEnrolment.status='Confirmed')
                LEFT JOIN tawasulActivity as pastActivity ON (pastActivity.tawasulActivityID=pastEnrolment.tawasulActivityID AND pastActivity.tawasulActivityCategoryID<>tawasulActivityCategory.tawasulActivityCategoryID)
                LEFT JOIN tawasulActivityChoice as pastChoice ON (pastChoice.tawasulActivityChoiceID=pastEnrolment.tawasulActivityChoiceID AND pastChoice.tawasulPersonID=tawasulActivityChoice.tawasulPersonID )
                WHERE tawasulActivityCategory.tawasulActivityCategoryID=:tawasulActivityCategoryID
                AND tawasulActivityChoice.choice=1
                GROUP BY tawasulActivityChoice.tawasulPersonID
                ORDER BY choiceCount DESC";

        return $this->db()->select($sql, $data);
    }

    public function getTimestampMinMaxByCategory($tawasulActivityCategoryID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID];
        $sql = "SELECT UNIX_TIMESTAMP(MIN(timestampCreated)) as min, UNIX_TIMESTAMP(MAX(timestampCreated)) as max
            FROM tawasulActivityChoice 
            WHERE tawasulActivityChoice.tawasulActivityCategoryID=:tawasulActivityCategoryID
            GROUP BY tawasulActivityChoice.tawasulActivityCategoryID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getYearGroupWeightingMax()
    {
        return $this->db()->selectOne("SELECT MAX(sequenceNumber) FROM tawasulYearGroup");
    }

    public function selectChoicesByPerson($tawasulActivityCategoryID, $tawasulPersonID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulActivityChoice.choice as groupBy, tawasulActivityChoice.*
                FROM tawasulActivityChoice
                JOIN tawasulActivity ON (tawasulActivity.tawasulActivityID=tawasulActivityChoice.tawasulActivityID)
                WHERE tawasulActivity.tawasulActivityCategoryID=:tawasulActivityCategoryID
                AND tawasulActivityChoice.tawasulPersonID=:tawasulPersonID
                ORDER BY tawasulActivityChoice.choice";

        return $this->db()->select($sql, $data);
    }

    public function getChoiceByActivityAndPerson($tawasulActivityID, $tawasulPersonID)
    {
        $data = ['tawasulActivityID' => $tawasulActivityID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT *
                FROM tawasulActivityChoice
                WHERE tawasulActivityChoice.tawasulActivityID=:tawasulActivityID
                AND tawasulActivityChoice.tawasulPersonID=:tawasulPersonID
                LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }

    public function deleteChoicesNotInList($tawasulActivityCategoryID, $tawasulPersonID, $choiceIDs)
    {
        $choiceIDs = is_array($choiceIDs) ? implode(',', $choiceIDs) : $choiceIDs;

        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID, 'tawasulPersonID' => $tawasulPersonID, 'choiceIDs' => $choiceIDs];
        $sql = "DELETE FROM tawasulActivityChoice 
                WHERE tawasulActivityCategoryID=:tawasulActivityCategoryID 
                AND tawasulPersonID=:tawasulPersonID
                AND NOT FIND_IN_SET(tawasulActivityChoiceID, :choiceIDs)";

        return $this->db()->delete($sql, $data);
    }

}
