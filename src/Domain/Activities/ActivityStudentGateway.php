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
 * Activity Student Gateway
 *
 * @version v27
 * @since   v27
 */
class ActivityStudentGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulActivityStudent';
    private static $primaryKey = 'tawasulActivityStudentID';

    private static $searchableColumns = ['surname', 'preferredName'];

    public function queryActivityEnrolment($criteria, $tawasulActivityID) {
        $query = $this
            ->newQuery()
            ->cols(['tawasulActivityStudent.*', 'surname', 'preferredName', 'tawasulFormGroup.nameShort as formGroup', 'FIND_IN_SET(tawasulActivityStudent.status, "Accepted,Pending,Waiting List,Not Accepted,Left") as sortOrder'])
            ->from($this->getTableName())
            ->innerJoin('tawasulActivity', 'tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulActivityStudent.tawasulPersonID')
            ->leftJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulActivity.tawasulSchoolYearID')
            ->leftJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->where('tawasulActivityStudent.tawasulActivityID = :tawasulActivityID')
            ->bindValue('tawasulActivityID', $tawasulActivityID)
            ->where('tawasulPerson.status="Full"');

        return $this->runQuery($query, $criteria);
    }

    public function queryAllActivityParticipants($criteria, $tawasulActivityID) {
        $query = $this
            ->newQuery()
            ->cols(['tawasulActivityStudent.tawasulActivityStudentID id', 'tawasulActivityStudent.tawasulPersonID', '"Student" as role', '"Student" as roleCategory', 'tawasulActivityStudent.status', 'surname', 'preferredName', 'tawasulFormGroup.nameShort as formGroup', 'FIND_IN_SET(tawasulActivityStudent.status, "Accepted,Pending,Waiting List,Not Accepted,Left") as sortOrder', 'tawasulActivityChoice.choice'])
            ->from('tawasulActivityStudent')
            ->innerJoin('tawasulActivity', 'tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulActivityStudent.tawasulPersonID')
            ->leftJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulActivity.tawasulSchoolYearID')
            ->leftJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->leftJoin('tawasulActivityChoice', 'tawasulActivityChoice.tawasulActivityChoiceID=tawasulActivityStudent.tawasulActivityChoiceID')
            ->where('tawasulActivityStudent.tawasulActivityID = :tawasulActivityID')
            ->bindValue('tawasulActivityID', $tawasulActivityID)
            ->where('tawasulPerson.status="Full"');

        $query->unionAll()
            ->cols(['tawasulActivityStaff.tawasulActivityStaffID as id', 'tawasulActivityStaff.tawasulPersonID', 'tawasulActivityStaff.role', '"Staff" as roleCategory', '"Staff" as status', 'surname', 'preferredName', 'NULL as formGroup', 'FIND_IN_SET(tawasulActivityStaff.role, "Organiser,Coach,Assistant,Other") as sortOrder', 'NULL as choice'])
            ->from('tawasulActivityStaff')
            ->innerJoin('tawasulActivity', 'tawasulActivity.tawasulActivityID=tawasulActivityStaff.tawasulActivityID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulActivityStaff.tawasulPersonID')
            ->innerJoin('tawasulStaff', 'tawasulPerson.tawasulPersonID=tawasulStaff.tawasulPersonID')
            ->where('tawasulActivityStaff.tawasulActivityID = :tawasulActivityID')
            ->bindValue('tawasulActivityID', $tawasulActivityID)
            ->where('tawasulPerson.status="Full"');

        return $this->runQuery($query, $criteria);
    }

    public function queryUnenrolledStudentsByCategory($criteria, $tawasulActivityCategoryID)
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
                'tawasulPerson.dob',
                'tawasulFormGroup.name as formGroup',
                'tawasulYearGroup.name as yearGroup',
                'tawasulYearGroup.sequenceNumber as yearGroupSequence',
                'MIN(CASE WHEN tawasulActivityChoice.choice=1 THEN tawasulActivityChoice.tawasulActivityID END) as choice1',
                'MIN(CASE WHEN tawasulActivityChoice.choice=2 THEN tawasulActivityChoice.tawasulActivityID END) as choice2',
                'MIN(CASE WHEN tawasulActivityChoice.choice=3 THEN tawasulActivityChoice.tawasulActivityID END) as choice3',
                'MIN(CASE WHEN tawasulActivityChoice.choice=4 THEN tawasulActivityChoice.tawasulActivityID END) as choice4',
                'MIN(CASE WHEN tawasulActivityChoice.choice=5 THEN tawasulActivityChoice.tawasulActivityID END) as choice5',
                "GROUP_CONCAT(DISTINCT choiceActivity.name ORDER BY tawasulActivityChoice.choice SEPARATOR ',') as choices",
            ])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulSchoolYearID=tawasulActivityCategory.tawasulSchoolYearID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            
            ->leftJoin('tawasulActivity', 'tawasulActivity.tawasulActivityCategoryID=tawasulActivityCategory.tawasulActivityCategoryID AND tawasulActivity.tawasulSchoolYearID=tawasulActivityCategory.tawasulSchoolYearID')
            ->leftJoin('tawasulActivityStudent', 'tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID')

            ->leftJoin('tawasulActivityChoice', 'tawasulActivityChoice.tawasulActivityCategoryID=tawasulActivityCategory.tawasulActivityCategoryID AND tawasulActivityChoice.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->leftJoin('tawasulActivity AS choiceActivity', 'choiceActivity.tawasulActivityID=tawasulActivityChoice.tawasulActivityID')

            ->where('tawasulActivityCategory.tawasulActivityCategoryID=:tawasulActivityCategoryID')
            ->bindValue('tawasulActivityCategoryID', $tawasulActivityCategoryID)
            // ->where('FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulActivity.tawasulYearGroupIDList)')
            ->where("tawasulPerson.status = 'Full'")
            ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)')
            ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
            ->bindValue('today', date('Y-m-d'))
            ->having('COUNT(tawasulActivityStudent.tawasulActivityStudentID) = 0')
            ->groupBy(['tawasulPerson.tawasulPersonID']);

        return $this->runQuery($query, $criteria);
    }

    public function selectEnrolmentsByCategory($tawasulActivityCategoryID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID, 'today' => date('Y-m-d')];
        $sql = "SELECT tawasulActivityStudent.tawasulActivityStudentID as groupBy,
                    tawasulActivityStudent.tawasulActivityStudentID as enrolmentID,
                    tawasulActivityStudent.tawasulActivityID,
                    tawasulActivityStudent.tawasulPersonID,
                    tawasulActivityStudent.status,
                    tawasulActivityChoice.timestampCreated,
                    tawasulPerson.surname,
                    tawasulPerson.preferredName,
                    tawasulPerson.dob,
                    tawasulFormGroup.name as formGroup,
                    tawasulYearGroup.name as yearGroup,
                    tawasulYearGroup.sequenceNumber as yearGroupSequence,
                    MIN(CASE WHEN tawasulActivityChoice.choice=1 THEN tawasulActivityChoice.tawasulActivityID END) as choice1,
                    MIN(CASE WHEN tawasulActivityChoice.choice=2 THEN tawasulActivityChoice.tawasulActivityID END) as choice2,
                    MIN(CASE WHEN tawasulActivityChoice.choice=3 THEN tawasulActivityChoice.tawasulActivityID END) as choice3,
                    MIN(CASE WHEN tawasulActivityChoice.choice=4 THEN tawasulActivityChoice.tawasulActivityID END) as choice4,
                    MIN(CASE WHEN tawasulActivityChoice.choice=5 THEN tawasulActivityChoice.tawasulActivityID END) as choice5
                FROM tawasulActivityStudent
                JOIN tawasulActivity ON (tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID)
                JOIN tawasulActivityCategory ON (tawasulActivityCategory.tawasulActivityCategoryID=tawasulActivity.tawasulActivityCategoryID)
                JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulActivityStudent.tawasulPersonID)
                LEFT JOIN tawasulActivityChoice ON (tawasulActivity.tawasulActivityCategoryID=tawasulActivityChoice.tawasulActivityCategoryID AND tawasulActivityChoice.tawasulPersonID=tawasulActivityStudent.tawasulPersonID)
                LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulActivityCategory.tawasulSchoolYearID)
                LEFT JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID)
                LEFT JOIN tawasulYearGroup ON (tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID)
                WHERE tawasulActivityCategory.tawasulActivityCategoryID=:tawasulActivityCategoryID 
                AND tawasulPerson.status = 'Full'
                AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)
                AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)
                GROUP BY tawasulActivityStudent.tawasulPersonID
                ORDER BY tawasulYearGroup.sequenceNumber, tawasulFormGroup.name, tawasulPerson.surname, tawasulPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    public function getEnrolmentByCategoryAndPerson($tawasulActivityCategoryID, $tawasulPersonID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulActivityStudent.*, tawasulActivity.name as activityName
                FROM tawasulActivityStudent
                JOIN tawasulActivity ON (tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID)
                WHERE tawasulActivity.tawasulActivityCategoryID=:tawasulActivityCategoryID
                AND tawasulActivityStudent.tawasulPersonID=:tawasulPersonID
                LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }

    public function deleteEnrolmentByCategoryAndPerson($tawasulActivityCategoryID, $tawasulPersonID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "DELETE tawasulActivityStudent
                FROM tawasulActivityStudent
                JOIN tawasulActivity ON (tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID)
                WHERE tawasulActivity.tawasulActivityCategoryID=:tawasulActivityCategoryID
                AND tawasulActivityStudent.tawasulPersonID=:tawasulPersonID";

        return $this->db()->selectOne($sql, $data);
    }

}
