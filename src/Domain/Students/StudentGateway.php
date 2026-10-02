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

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\Traits\SharedUserLogic;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v16
 * @since   v16
 */
class StudentGateway extends QueryableGateway
{
    use TableAware;
    use SharedUserLogic;

    private static $tableName = 'tawasulStudentEnrolment';
    private static $primaryKey = 'tawasulStudentEnrolmentID';

    private static $searchableColumns = ['tawasulPerson.preferredName', 'tawasulPerson.firstName', 'tawasulPerson.surname', 'tawasulPerson.nameInCharacters', 'tawasulPerson.username', 'tawasulPerson.email', 'tawasulPerson.emailAlternate', 'tawasulPerson.studentID', 'tawasulPerson.phone1', 'tawasulPerson.vehicleRegistration'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryStudentsBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID, $searchFamilyDetails = false, $branchCond = '', $branchParams = [])
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.tawasulPersonID', 'tawasulStudentEnrolmentID', 'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulPerson.image_240',  'tawasulYearGroup.tawasulYearGroupID', 'tawasulYearGroup.nameShort AS yearGroup', 'tawasulFormGroup.tawasulFormGroupID', 'tawasulFormGroup.nameShort AS formGroup', 'tawasulStudentEnrolment.rollOrder', 'tawasulPerson.dateStart', 'tawasulPerson.dateEnd', 'tawasulPerson.status', "'Student' as roleCategory"
            ])
            ->leftJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->leftJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->leftJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        // tawasulStudentEnrolment is left-joined, so applying the branch
        // predicate here (rather than in the join condition) keeps students who
        // are not enrolled this year visible on the "all" view.
        if ($branchCond !== '') {
            $query->where($branchCond, $branchParams);
        }

        if ($criteria->hasFilter('all')) {
            $query->innerJoin('tawasulRole', 'FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)')
                  ->where("tawasulRole.category='Student'");
        } else {
            $query->where("tawasulStudentEnrolment.tawasulStudentEnrolmentID IS NOT NULL")
                  ->where("tawasulPerson.status = 'Full'")
                  ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)')
                  ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
                  ->bindValue('today', date('Y-m-d'));
        }

        if ($searchFamilyDetails && $criteria->hasSearchText()) {
            self::$searchableColumns = array_merge(self::$searchableColumns, ['parent1.email', 'parent1.emailAlternate', 'parent2.email', 'parent2.emailAlternate']);

            $query
                ->leftJoin('tawasulFamilyChild as child', "child.tawasulPersonID=tawasulPerson.tawasulPersonID")
                ->leftJoin('tawasulFamilyAdult as adult1', "(adult1.tawasulFamilyID=child.tawasulFamilyID AND adult1.contactPriority=1)")
                ->leftJoin('tawasulPerson as parent1', "(parent1.tawasulPersonID=adult1.tawasulPersonID AND parent1.status='Full')")
                ->leftJoin('tawasulFamilyAdult as adult2', "(adult2.tawasulFamilyID=child.tawasulFamilyID AND adult2.contactPriority=2)")
                ->leftJoin('tawasulPerson as parent2', "(parent2.tawasulPersonID=adult2.tawasulPersonID AND parent2.status='Full')");
        }

        $criteria->addFilterRules($this->getSharedUserFilterRules());

        $criteria->addFilterRules([
            'yearGroup' => function ($query, $tawasulYearGroupID) {
                return $query
                    ->where('tawasulStudentEnrolment.tawasulYearGroupID = :tawasulYearGroupID')
                    ->bindValue('tawasulYearGroupID', $tawasulYearGroupID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryStudentEnrolmentBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.tawasulPersonID', 'tawasulStudentEnrolmentID', 'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulPerson.image_240', 'tawasulYearGroup.nameShort AS yearGroup', 'tawasulFormGroup.nameShort AS formGroup', 'tawasulStudentEnrolment.rollOrder', 'tawasulPerson.dateStart', 'tawasulPerson.dateEnd', 'tawasulPerson.status', "'Student' as roleCategory"
            ])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        $criteria->addFilterRules($this->getSharedUserFilterRules());

        return $this->runQuery($query, $criteria);
    }

    public function queryStudentEnrolmentByFormGroup(QueryCriteria $criteria, $tawasulFormGroupID = null)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.tawasulPersonID', 'tawasulStudentEnrolmentID', 'tawasulStudentEnrolment.tawasulSchoolYearID', 'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulPerson.image_240', 'tawasulYearGroup.nameShort AS yearGroup', 'tawasulFormGroup.nameShort AS formGroup', 'tawasulStudentEnrolment.rollOrder', 'tawasulPerson.dateStart', 'tawasulPerson.dateEnd', 'tawasulPerson.status', "'Student' as roleCategory", 'gender', 'dob', 'transport', 'lockerNumber', 'privacy',
                "GROUP_CONCAT(DISTINCT (CASE WHEN tawasulPersonalDocumentType.name IS NOT NULL THEN tawasulPersonalDocument.country END) SEPARATOR '<br/>') as citizenship"
            ])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->leftJoin('tawasulPersonalDocument', "tawasulPersonalDocument.foreignTable='tawasulPerson' AND tawasulPersonalDocument.foreignTableID=tawasulPerson.tawasulPersonID AND tawasulPersonalDocument.country IS NOT NULL")
            ->leftJoin('tawasulPersonalDocumentType', "tawasulPersonalDocumentType.tawasulPersonalDocumentTypeID=tawasulPersonalDocument.tawasulPersonalDocumentTypeID AND tawasulPersonalDocumentType.document='Passport'")
            ->where("tawasulPerson.status = 'Full'")
            ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)')
            ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
            ->bindValue('today', date('Y-m-d'))
            ->groupBy(['tawasulPerson.tawasulPersonID']);

        if (!empty($tawasulFormGroupID)) {
            $query
                ->where('tawasulStudentEnrolment.tawasulFormGroupID = :tawasulFormGroupID')
                ->bindValue('tawasulFormGroupID', $tawasulFormGroupID);
        } else {
            $query->where("tawasulStudentEnrolment.tawasulSchoolYearID=(SELECT tawasulSchoolYearID FROM tawasulSchoolYear WHERE status='Current' LIMIT 1)");
        }

        $criteria->addFilterRules($this->getSharedUserFilterRules());

        $criteria->addFilterRules([
            'view' => function ($query, $view) {
                if ($view == 'extended') {
                    $query->cols(['tawasulHouse.name as house', 'tawasulPersonMedical.*', 'COUNT(tawasulPersonMedicalConditionID) as conditionCount'])
                        ->leftJoin('tawasulHouse', 'tawasulHouse.tawasulHouseID=tawasulPerson.tawasulHouseID')
                        ->leftJoin('tawasulPersonMedical', 'tawasulPersonMedical.tawasulPersonID=tawasulPerson.tawasulPersonID')
                        ->leftJoin('tawasulPersonMedicalCondition', 'tawasulPersonMedicalCondition.tawasulPersonMedicalID=tawasulPersonMedical.tawasulPersonMedicalID')
                        ->groupBy(['tawasulPerson.tawasulPersonID']);
                }
                return $query;
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryStudentsAndTeachersBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulRoleIDCurrentCategory = null)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.tawasulPersonID', 'tawasulStudentEnrolmentID', 'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulPerson.image_240', 'tawasulYearGroup.nameShort AS yearGroup', 'tawasulFormGroup.nameShort AS formGroup', 'tawasulStudentEnrolment.rollOrder', 'tawasulPerson.dateStart', 'tawasulPerson.dateEnd', 'tawasulPerson.status', 'tawasulRole.category as roleCategory', 'tawasulStaff.type as staffType'
            ])
            ->innerJoin('tawasulRole', 'FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)')
            ->leftJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->leftJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->leftJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->leftJoin('tawasulStaff', "tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID")
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)

            ->groupBy(['tawasulPerson.tawasulPersonID']);

        if (!$criteria->hasFilter('all') || $tawasulRoleIDCurrentCategory != 'Staff') {
            $query->where("(tawasulStudentEnrolment.tawasulStudentEnrolmentID IS NOT NULL OR (tawasulStaff.tawasulStaffID IS NOT NULL AND tawasulRole.category='Staff') )")
                  ->where("tawasulPerson.status = 'Full'")
                  ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)')
                  ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
                  ->bindValue('today', date('Y-m-d'));
        }

        $criteria->addFilterRules($this->getSharedUserFilterRules());

        return $this->runQuery($query, $criteria);
    }

    public function selectUnenrolledStudentsBySchoolYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulPerson.tawasulPersonID AS value, CONCAT(surname, \", \", preferredName, \" (\", username, \")\") AS name
                FROM tawasulPerson
                    JOIN tawasulRole ON (FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll))
                    LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID)
                    LEFT JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                WHERE
                    (status='Full' OR status='Expected')
                    AND tawasulFormGroup.name IS NULL
                    AND tawasulRole.category='Student'
                ORDER BY name, surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectAnyStudentsByFamilyAdult($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulPerson.tawasulPersonID, title, surname, preferredName, image_240, 'Student' as roleCategory, tawasulYearGroup.tawasulYearGroupID
                FROM tawasulFamilyAdult
                JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamilyAdult.tawasulFamilyID)
                JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID)
                LEFT JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID)
                LEFT JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
                WHERE tawasulFamilyAdult.tawasulPersonID=:tawasulPersonID
                AND tawasulFamilyAdult.childDataAccess='Y'
                GROUP BY tawasulPerson.tawasulPersonID
                ORDER BY surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function selectActiveStudentsByFamilyAdult($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID, 'today' => date('Y-m-d'));
        $sql = "SELECT tawasulPerson.tawasulPersonID as groupBy, tawasulPerson.tawasulPersonID, title, surname, preferredName, image_240, tawasulYearGroup.nameShort AS yearGroup, tawasulFormGroup.nameShort AS formGroup, 'Student' as roleCategory, tawasulYearGroup.tawasulYearGroupID, tawasulFormGroup.tawasulFormGroupID 
                FROM tawasulFamilyAdult
                JOIN tawasulFamilyChild ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamilyAdult.tawasulFamilyID)
                JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID)
                JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
                JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                WHERE tawasulFamilyAdult.tawasulPersonID=:tawasulPersonID
                AND tawasulFamilyAdult.childDataAccess='Y'
                AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                AND tawasulPerson.status='Full'
                AND (tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:today)
                AND (tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:today)
                GROUP BY tawasulPerson.tawasulPersonID
                ORDER BY surname, preferredName";

        return $this->db()->select($sql, $data);
    }

    public function getStudentByFamilyAdult($tawasulPersonIDStudent, $tawasulPersonIDAdult)
    {
        $data = ['tawasulPersonIDStudent' => $tawasulPersonIDStudent, 'tawasulPersonIDAdult' => $tawasulPersonIDAdult, 'today' => date('Y-m-d')];
        $sql = "SELECT tawasulPerson.tawasulPersonID FROM tawasulFamilyChild JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulPerson.status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL  OR dateEnd>=:today) AND tawasulFamilyChild.tawasulPersonID=:tawasulPersonIDStudent AND tawasulFamilyAdult.tawasulPersonID=:tawasulPersonIDAdult AND childDataAccess='Y'";
        
        return $this->db()->selectOne($sql, $data);
    }

    public function selectActiveStudentByPerson($tawasulSchoolYearID, $tawasulPersonID, $onlyFull = true)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulPerson.tawasulPersonID, title, surname, preferredName, email, image_240, gender, dateStart, dateEnd, tawasulPerson.status, tawasulStudentEnrolment.tawasulStudentEnrolmentID, tawasulStudentEnrolment.tawasulSchoolYearID, tawasulYearGroup.tawasulYearGroupID, tawasulYearGroup.nameShort AS yearGroup, tawasulYearGroup.name AS yearGroupName, tawasulFormGroup.tawasulFormGroupID, tawasulFormGroup.nameShort AS formGroup, tawasulFormGroup.name AS formGroupName, 'Student' as roleCategory, tawasulPerson.privacy, tawasulStudentEnrolment.fields
                FROM tawasulPerson
                LEFT JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID)
                LEFT JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
                LEFT JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                WHERE tawasulPerson.tawasulPersonID=:tawasulPersonID";

        if ($onlyFull) {
            $data['today'] = date('Y-m-d');
            $sql .= " AND tawasulPerson.status='Full'
                AND (dateStart IS NULL OR dateStart<=:today)
                AND (dateEnd IS NULL  OR dateEnd>=:today) 
                AND tawasulStudentEnrolment.tawasulStudentEnrolmentID IS NOT NULL";
        }

        return $this->db()->select($sql, $data);
    }
    
    public function getStudentByUsername($tawasulSchoolYearID, $username)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'username' => $username);
        $sql = "SELECT tawasulPerson.tawasulPersonID, title, surname, preferredName, image_240, gender, tawasulStudentEnrolment.tawasulStudentEnrolmentID, tawasulStudentEnrolment.tawasulSchoolYearID, tawasulYearGroup.tawasulYearGroupID, tawasulYearGroup.nameShort AS yearGroup, tawasulFormGroup.tawasulFormGroupID, tawasulFormGroup.nameShort AS formGroup, 'Student' as roleCategory, tawasulPerson.privacy, tawasulPerson.username
                FROM tawasulPerson
                JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
                JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                WHERE tawasulPerson.username=:username
                AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectAllStudentEnrolmentsByPerson($tawasulPersonID)
    {
        $data = array('tawasulPersonID' => $tawasulPersonID);
        $sql = "SELECT *
                FROM tawasulStudentEnrolment
                JOIN tawasulSchoolYear ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                WHERE tawasulPersonID=:tawasulPersonID
                AND (tawasulSchoolYear.status='Current' OR tawasulSchoolYear.status='Past')
                ORDER BY sequenceNumber DESC";

        return $this->db()->select($sql, $data);
    }

    public function getStudentEnrolmentCount($tawasulSchoolYearID, $date = null)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'date' => $date ?? date('Y-m-d')];
        $sql = "SELECT COUNT(tawasulPerson.tawasulPersonID)
                FROM tawasulPerson
                JOIN tawasulStudentEnrolment ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                WHERE tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID ";

        if (!empty($date)) {
            $sql .= " AND ((status='Full' AND (dateStart IS NULL OR dateStart<=:date) AND (dateEnd IS NULL OR dateEnd>=:date))
                OR (status='Left' AND (dateStart IS NULL OR dateStart<=:date) AND dateEnd>=:date))";
        } else {
            $sql .= " AND status='Full'
                AND (dateStart IS NULL OR dateStart<=:date) AND (dateEnd IS NULL OR dateEnd>=:date)";
        }
        return $this->db()->selectOne($sql, $data);
    }

    public function selectAllRelatedUsersByStudent($tawasulSchoolYearID, $tawasulYearGroupID, $tawasulFormGroupID, $tawasulPersonID, $includeClassTeachers = true)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID, 'tawasulYearGroupID' => $tawasulYearGroupID, 'tawasulFormGroupID' => $tawasulFormGroupID);
        $sql = "
            (
                SELECT 'Head of Year' as type, '' as classID, tawasulPerson.tawasulPersonID, surname, preferredName, email, image_240, tawasulYearGroup.name as context, 1 as listOrder
                FROM tawasulPerson
                JOIN tawasulYearGroup ON (tawasulYearGroup.tawasulPersonIDHOY=tawasulPersonID)
                WHERE status='Full' AND tawasulYearGroupID=:tawasulYearGroupID
            )
            UNION
            (
                SELECT 'IN Assistant' as type, '' as classID, tawasulPerson.tawasulPersonID, surname, preferredName, email, image_240, '' as context, 2 as listOrder
                FROM tawasulPerson
                    JOIN tawasulINAssistant ON (tawasulINAssistant.tawasulPersonIDAssistant=tawasulPerson.tawasulPersonID)
                    JOIN tawasulStaff ON (tawasulStaff.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE status='Full'
                    AND tawasulPersonIDStudent=:tawasulPersonID
            )
            UNION
            (
                SELECT 'Educational Assistant' as type, '' as classID, tawasulPerson.tawasulPersonID, surname, preferredName, email, image_240, '' as context, 2 as listOrder
                FROM tawasulPerson
                JOIN tawasulFormGroup ON (tawasulFormGroup.tawasulPersonIDEA=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDEA2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDEA3=tawasulPerson.tawasulPersonID)
                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
                JOIN tawasulSchoolYear ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                AND tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID
                AND tawasulPerson.status='Full'
            )
            UNION
            (
                SELECT 'Form Tutor' as type, '' as classID, tawasulPerson.tawasulPersonID, surname, preferredName, email, image_240, tawasulFormGroup.name as context, 0 as listOrder
                FROM tawasulFormGroup
                JOIN tawasulPerson ON (tawasulFormGroup.tawasulPersonIDTutor=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor2=tawasulPerson.tawasulPersonID OR tawasulFormGroup.tawasulPersonIDTutor3=tawasulPerson.tawasulPersonID)
                WHERE tawasulFormGroupID=:tawasulFormGroupID AND tawasulPerson.status='Full'
            )";

            if ($includeClassTeachers) {
                $sql .= "UNION (
                    SELECT DISTINCT 'Class Teacher' as type, tawasulCourseClass.tawasulCourseClassID as classID, teacher.tawasulPersonID, teacher.surname, teacher.preferredName, teacher.email, teacher.image_240, tawasulCourse.name as context, 4 as listOrder
                    FROM tawasulPerson AS teacher
                    JOIN tawasulCourseClassPerson AS teacherClass ON (teacherClass.tawasulPersonID=teacher.tawasulPersonID)
                    JOIN tawasulCourseClassPerson AS studentClass ON (studentClass.tawasulCourseClassID=teacherClass.tawasulCourseClassID)
                    JOIN tawasulPerson AS student ON (studentClass.tawasulPersonID=student.tawasulPersonID)
                    JOIN tawasulCourseClass ON (studentClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID)
                    JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID)
                    WHERE teacher.status='Full' AND teacherClass.role='Teacher' AND studentClass.role='Student' AND student.tawasulPersonID=:tawasulPersonID AND tawasulCourse.tawasulSchoolYearID=:tawasulSchoolYearID
                    ORDER BY teacher.preferredName, teacher.surname, teacher.email
                ) ";
            }

        $sql .= " ORDER BY listOrder, preferredName, surname, email";

        return $this->db()->select($sql, $data);
    }

    public function queryStudentHistoryByPerson(QueryCriteria $criteria, $tawasulPersonID)
    {
        //Students from timetable classes
        $query = $this
            ->newQuery()
            ->distinct()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.tawasulPersonID', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulPerson.image_240',  'tawasulPerson.dob'
            ])
            ->innerJoin('tawasulCourseClassPerson AS student', 'student.tawasulPersonID=tawasulPerson.tawasulPersonID AND student.role LIKE \'Student%\'')
            ->innerJoin('tawasulCourseClassPerson AS teacher', 'teacher.tawasulCourseClassID=student.tawasulCourseClassID AND teacher.role LIKE \'Teacher%\'')
            ->innerJoin('tawasulCourseClass', 'student.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID')
            ->innerJoin('tawasulCourse', 'tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID')
            ->where("(tawasulPerson.image_240 <> '' AND tawasulPerson.image_240 IS NOT NULL)")
            ->where('teacher.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID);

        //Students from form groups
        $this->unionWithCriteria($query, $criteria)
            ->distinct()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.tawasulPersonID', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulPerson.image_240',  'tawasulPerson.dob'
            ])
            ->innerJoin('tawasulStudentEnrolment AS student', 'student.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulFormGroup', 'student.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->where("(tawasulPerson.image_240 <> '' AND tawasulPerson.image_240 IS NOT NULL)")
            ->where('(tawasulPersonIDTutor=:tawasulPersonID OR tawasulPersonIDTutor2=:tawasulPersonID OR tawasulPersonIDTutor3=:tawasulPersonID)')
            ->bindValue('tawasulPersonID', $tawasulPersonID);

        return $this->runQuery($query, $criteria);
    }

    public function selectActiveStudentNames($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql  = "SELECT preferredName
                FROM tawasulPerson
                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                AND tawasulPerson.status='Full'";

        return $this->db()->select($sql, $data);
    }

    public function selectStudentEnrolmentHistory($tawasulPersonID)
    {
        $data = ['tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulFormGroup.name AS formGroup, tawasulSchoolYear.name AS schoolYear, tawasulYearGroup.nameShort as studyYear
            FROM tawasulStudentEnrolment
            JOIN tawasulFormGroup ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID)
            JOIN tawasulSchoolYear ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
            JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
            WHERE tawasulPersonID=:tawasulPersonID
            AND (tawasulSchoolYear.status = 'Current' OR tawasulSchoolYear.status='Past')
            ORDER BY tawasulStudentEnrolment.tawasulSchoolYearID";
          
          return $this->db()->select($sql, $data);
    }
}
