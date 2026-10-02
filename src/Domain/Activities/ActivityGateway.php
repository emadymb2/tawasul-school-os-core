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
use TawasulOS\Services\Format;

/**
 * Activity Gateway
 *
 * @version v16
 * @since   v16
 */
class ActivityGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulActivity';
    private static $primaryKey = 'tawasulActivityID';

    private static $searchableColumns = ['tawasulActivity.name', 'tawasulActivity.type'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryActivitiesBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID, $dateType = null, $tawasulYearGroupID = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulActivity.tawasulActivityID', 'tawasulActivity.name', 'tawasulActivity.active', 'tawasulActivity.provider', 'tawasulActivity.registration', 'tawasulActivity.type', 'tawasulSchoolYearTermIDList', 'programStart', 'programEnd', 'payment', 'paymentType', 'paymentFirmness', 'maxParticipants',
                'tawasulActivityType.access', 'tawasulActivityType.maxPerStudent', 'tawasulActivityType.waitingList',
                "(CASE WHEN tawasulActivity.registration = 'Y' THEN '0' ELSE '1' END) AS registrationOrder",
                "GROUP_CONCAT(DISTINCT tawasulYearGroup.nameShort ORDER BY tawasulYearGroup.sequenceNumber SEPARATOR ', ') as yearGroups",
                "COUNT(DISTINCT tawasulYearGroup.tawasulYearGroupID) as yearGroupCount",
                "COUNT(DISTINCT CASE WHEN tawasulActivityStudent.status = 'Accepted' THEN tawasulActivityStudent.tawasulPersonID END) as enrolment",
                "COUNT(DISTINCT CASE WHEN tawasulActivityStudent.status = 'Waiting List' THEN tawasulActivityStudent.tawasulPersonID END) as waiting",
                "COUNT(DISTINCT CASE WHEN tawasulActivityStudent.status = 'Pending' THEN tawasulActivityStudent.tawasulPersonID END) as pending",
                "tawasulActivityPhoto.filePath as headerImage"
            ])
            ->leftJoin('tawasulYearGroup', 'FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulActivity.tawasulYearGroupIDList)')
            ->leftJoin('tawasulActivityType', 'tawasulActivity.type=tawasulActivityType.name')
            ->leftJoin('tawasulActivityPhoto', 'tawasulActivity.tawasulActivityID=tawasulActivityPhoto.tawasulActivityID AND tawasulActivityPhoto.sequenceNumber=0')
            ->leftJoin('tawasulActivityStudent', 'tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID')
            ->where('tawasulActivity.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->groupBy(['tawasulActivity.tawasulActivityID']);


        if (!empty($tawasulYearGroupID)) {
            $query->where('FIND_IN_SET(:tawasulYearGroupID, tawasulActivity.tawasulYearGroupIDList)')
                  ->bindValue('tawasulYearGroupID', $tawasulYearGroupID);
            $query->where("tawasulActivity.active = 'Y'");
            $query->where("tawasulActivityType.access <> 'None'");
        }

        if ($dateType == 'Term') {
            $query->where("NOT tawasulSchoolYearTermIDList=''");
        } else if ($dateType == 'Date') {
            $query->where('listingStart<=:today AND listingEnd>=:today')
                  ->bindValue('today', date('Y-m-d'));
        }

        $criteria->addFilterRules([
            'term' => function ($query, $tawasulSchoolYearTermID) {
                return $query
                    ->where('FIND_IN_SET(:tawasulSchoolYearTermID, tawasulActivity.tawasulSchoolYearTermIDList)')
                    ->bindValue('tawasulSchoolYearTermID', $tawasulSchoolYearTermID);
            },
            'active' => function ($query, $active) {
                return $query
                    ->where('tawasulActivity.active = :active')
                    ->bindValue('active', $active);
            },
            'category' => function ($query, $tawasulActivityCategoryID) {
                return $query
                    ->where('tawasulActivity.tawasulActivityCategoryID = :tawasulActivityCategoryID')
                    ->bindValue('tawasulActivityCategoryID', $tawasulActivityCategoryID);
            },
            'registration' => function ($query, $registration) {
                return $query
                    ->where('tawasulActivity.registration = :registration')
                    ->bindValue('registration', $registration);
            },
            'enrolment' => function ($query, $enrolment) {
                if ($enrolment == 'less') $query->having('enrolment < tawasulActivity.maxParticipants AND tawasulActivity.maxParticipants > 0');
                if ($enrolment == 'full') $query->having('enrolment = tawasulActivity.maxParticipants AND tawasulActivity.maxParticipants > 0');
                if ($enrolment == 'greater') $query->having('enrolment > tawasulActivity.maxParticipants AND tawasulActivity.maxParticipants > 0');
                return $query;
            },
            'status' => function ($query, $status) {
                if ($status == 'waiting') $query->having('waiting > 0');
                if ($status == 'pending') $query->having('pending > 0');
                return $query;
            },
            'yearGroup' => function ($query, $tawasulYearGroupID) {
                return $query
                    ->where('FIND_IN_SET(:tawasulYearGroupID, tawasulActivity.tawasulYearGroupIDList)')
                    ->bindValue('tawasulYearGroupID', $tawasulYearGroupID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryActivitiesByParticipant(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonID)
    {
        $query = $this
            ->newQuery()
            ->cols([
                'tawasulActivity.tawasulActivityID', 'tawasulActivityCategory.tawasulActivityCategoryID', 'tawasulActivity.name', 'tawasulActivity.type', 'tawasulActivityStudent.status', 'NULL AS role', 'tawasulActivityCategory.name as category', 'tawasulActivityCategory.sequenceNumber', 'NULL AS choices', 'tawasulActivityCategory.accessOpenDate', 'tawasulActivityCategory.accessCloseDate', 'tawasulActivityCategory.accessEnrolmentDate', 'tawasulActivityCategory.tawasulYearGroupIDParentRegister'
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulActivityStudent', 'tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID')
            ->leftJoin('tawasulActivityCategory', 'tawasulActivityCategory.tawasulActivityCategoryID=tawasulActivity.tawasulActivityCategoryID')
            ->where('tawasulActivity.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulActivityStudent.tawasulPersonID = :tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulActivity.active="Y"')
            ->where('(tawasulActivityCategory.tawasulActivityCategoryID IS NULL OR ((CURRENT_TIMESTAMP >= tawasulActivityCategory.accessEnrolmentDate AND CURRENT_TIMESTAMP >= tawasulActivityCategory.viewableDate) AND tawasulActivityCategory.active="Y"))');

        $query->unionAll()
            ->cols([
                'tawasulActivity.tawasulActivityID', 'tawasulActivityCategory.tawasulActivityCategoryID', 'tawasulActivity.name', 'tawasulActivity.type', 'NULL AS status', 'tawasulActivityStaff.role AS role', 'tawasulActivityCategory.name as category', 'tawasulActivityCategory.sequenceNumber', 'NULL AS choices', 'tawasulActivityCategory.accessOpenDate', 'tawasulActivityCategory.accessCloseDate', 'tawasulActivityCategory.accessEnrolmentDate', 'tawasulActivityCategory.tawasulYearGroupIDParentRegister'
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulActivityStaff', 'tawasulActivityStaff.tawasulActivityID=tawasulActivity.tawasulActivityID')
            ->leftJoin('tawasulActivityCategory', 'tawasulActivityCategory.tawasulActivityCategoryID=tawasulActivity.tawasulActivityCategoryID')
            ->where('tawasulActivity.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulActivityStaff.tawasulPersonID = :tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulActivity.active="Y" AND tawasulActivityCategory.active="Y"')
            ->where('tawasulActivityCategory.viewableDate IS NOT NULL')
            ->groupBy(['tawasulActivityCategory.tawasulActivityCategoryID', 'tawasulActivity.tawasulActivityID']);

        $query->unionAll()
            ->cols([
                'NULL as tawasulActivityID', 'tawasulActivityCategory.tawasulActivityCategoryID', "NULL as name", 'NULL as type', '"Pending" as status', 'NULL AS role', 'tawasulActivityCategory.name as category', 'tawasulActivityCategory.sequenceNumber', "GROUP_CONCAT(tawasulActivity.name ORDER BY tawasulActivityChoice.choice SEPARATOR ',') AS choices", 'tawasulActivityCategory.accessOpenDate', 'tawasulActivityCategory.accessCloseDate', 'tawasulActivityCategory.accessEnrolmentDate', 'tawasulActivityCategory.tawasulYearGroupIDParentRegister'
            ])
            ->from('tawasulActivityCategory')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulSchoolYearID=tawasulActivityCategory.tawasulSchoolYearID')
            ->leftJoin('tawasulActivityChoice', 'tawasulActivityChoice.tawasulActivityCategoryID=tawasulActivityCategory.tawasulActivityCategoryID AND tawasulActivityChoice.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->leftJoin('tawasulActivity', 'tawasulActivity.tawasulActivityID=tawasulActivityChoice.tawasulActivityID AND tawasulActivity.active="Y"')
            ->leftJoin('tawasulActivityStudent', 'tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID AND tawasulActivityStudent.tawasulPersonID=tawasulActivityChoice.tawasulPersonID')
            ->where('tawasulStudentEnrolment.tawasulPersonID = :tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulActivityCategory.active="Y"')
            ->where('(tawasulActivityCategory.accessEnrolmentDate IS NULL OR CURRENT_TIMESTAMP < tawasulActivityCategory.accessEnrolmentDate)')
            ->where('CURRENT_TIMESTAMP >= tawasulActivityCategory.viewableDate')
            ->groupBy(['tawasulActivityCategory.tawasulActivityCategoryID'])
            ;


        return $this->runQuery($query, $criteria);
    }

    public function selectActiveEnrolledActivities($tawasulSchoolYearID, $tawasulPersonID, $dateType, $date = null)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'tawasulActivity.tawasulActivityID', 'tawasulActivitySlot.tawasulActivitySlotID', 'tawasulActivity.name', 'tawasulActivity.provider', 'tawasulPerson.tawasulPersonID', 'tawasulActivitySlot.timeStart', 'tawasulActivitySlot.timeEnd', 'tawasulActivitySlot.locationExternal', 'tawasulSpace.name as space', 'tawasulDaysOfWeek.name as dayOfWeek',
            ])
            ->innerJoin('tawasulActivitySlot', 'tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID')
            ->leftJoin('tawasulActivityCategory', 'tawasulActivityCategory.tawasulActivityCategoryID=tawasulActivity.tawasulActivityCategoryID')
            ->innerJoin('tawasulDaysOfWeek', 'tawasulActivitySlot.tawasulDaysOfWeekID=tawasulDaysOfWeek.tawasulDaysOfWeekID')
            ->innerJoin('tawasulActivityStudent', 'tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID')
            ->innerJoin('tawasulPerson', "tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID")
            ->leftJoin('tawasulSpace', 'tawasulSpace.tawasulSpaceID=tawasulActivitySlot.tawasulSpaceID')
            ->where('tawasulActivity.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("tawasulActivity.active = 'Y'")
            ->where("tawasulActivityStudent.status='Accepted'")
            ->where("tawasulPerson.status = 'Full'")
            ->where('(dateStart IS NULL OR dateStart<=:today)')
            ->where('(dateEnd IS NULL OR dateEnd>=:today)')
            ->bindValue('today', $date ?? date('Y-m-d'))
            ->where('tawasulActivityStudent.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->bindValue('dateType', $dateType)
            ->where('(tawasulActivityCategory.tawasulActivityCategoryID IS NULL OR CURRENT_TIMESTAMP >= tawasulActivityCategory.accessEnrolmentDate)');

        if ($dateType == 'Term') {
            $query->cols(['tawasulSchoolYearTerm.firstDay as dateStart', 'tawasulSchoolYearTerm.lastDay as dateEnd'])
                ->innerJoin('tawasulSchoolYearTerm', "FIND_IN_SET(tawasulSchoolYearTermID, tawasulActivity.tawasulSchoolYearTermIDList)");
        } else {
            $query->cols(['tawasulActivity.programStart as dateStart', 'tawasulActivity.programEnd as dateEnd']);
        }

        $query->unionAll()
            ->from($this->getTableName())
            ->cols([
                'tawasulActivity.tawasulActivityID', 'tawasulActivitySlot.tawasulActivitySlotID', 'tawasulActivity.name', 'tawasulActivity.provider', 'tawasulPerson.tawasulPersonID', 'tawasulActivitySlot.timeStart', 'tawasulActivitySlot.timeEnd', 'tawasulActivitySlot.locationExternal', 'tawasulSpace.name as space', 'tawasulDaysOfWeek.name as dayOfWeek',
            ])
            ->innerJoin('tawasulActivitySlot', 'tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID')
            ->innerJoin('tawasulDaysOfWeek', 'tawasulActivitySlot.tawasulDaysOfWeekID=tawasulDaysOfWeek.tawasulDaysOfWeekID')
            ->innerJoin('tawasulActivityStaff', 'tawasulActivity.tawasulActivityID=tawasulActivityStaff.tawasulActivityID')
            ->innerJoin('tawasulPerson', "tawasulActivityStaff.tawasulPersonID=tawasulPerson.tawasulPersonID")
            ->leftJoin('tawasulSpace', 'tawasulSpace.tawasulSpaceID=tawasulActivitySlot.tawasulSpaceID')
            ->where('tawasulActivity.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("tawasulActivity.active = 'Y'")
            ->where("tawasulPerson.status = 'Full'")
            ->where('(dateStart IS NULL OR dateStart<=:today)')
            ->where('(dateEnd IS NULL OR dateEnd>=:today)')
            ->bindValue('today', $date ?? date('Y-m-d'))
            ->where('tawasulActivityStaff.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->bindValue('dateType', $dateType);

        if ($dateType == 'Term') {
            $query->cols(['tawasulSchoolYearTerm.firstDay as dateStart', 'tawasulSchoolYearTerm.lastDay as dateEnd'])
                ->innerJoin('tawasulSchoolYearTerm', "FIND_IN_SET(tawasulSchoolYearTermID, tawasulActivity.tawasulSchoolYearTermIDList)");
        } else {
            $query->cols(['tawasulActivity.programStart as dateStart', 'tawasulActivity.programEnd as dateEnd']);
        }

        return $this->runSelect($query);
    }

    public function selectActivitiesByFacility($tawasulSchoolYearID, $tawasulSpaceID, $dateType)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'tawasulActivity.tawasulActivityID', 'tawasulActivitySlot.tawasulActivitySlotID', 'tawasulActivity.name', 'tawasulActivity.provider', 'tawasulSpace.tawasulSpaceID', 'tawasulActivitySlot.timeStart', 'tawasulActivitySlot.timeEnd', 'tawasulActivitySlot.locationExternal', 'tawasulSpace.name as space', 'tawasulDaysOfWeek.name as dayOfWeek',
            ])
            ->innerJoin('tawasulActivitySlot', 'tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID')
            ->innerJoin('tawasulDaysOfWeek', 'tawasulActivitySlot.tawasulDaysOfWeekID=tawasulDaysOfWeek.tawasulDaysOfWeekID')
            ->leftJoin('tawasulSpace', 'tawasulSpace.tawasulSpaceID=tawasulActivitySlot.tawasulSpaceID')
            ->where('tawasulActivity.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("tawasulActivity.active = 'Y'")
            ->where('tawasulSpace.tawasulSpaceID=:tawasulSpaceID')
            ->bindValue('tawasulSpaceID', $tawasulSpaceID);

        if ($dateType == 'Term') {
            $query->cols(['tawasulSchoolYearTerm.firstDay as dateStart', 'tawasulSchoolYearTerm.lastDay as dateEnd'])
                ->innerJoin('tawasulSchoolYearTerm', "FIND_IN_SET(tawasulSchoolYearTermID, tawasulActivity.tawasulSchoolYearTermIDList)");
        } else {
            $query->cols(['tawasulActivity.programStart as dateStart', 'tawasulActivity.programEnd as dateEnd']);
        }

        return $this->runSelect($query);
    }

    public function selectActivityEnrolmentByStudent($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID);
        $sql = "SELECT tawasulActivity.tawasulActivityID AS groupBy, tawasulActivityStudent.* FROM tawasulActivityStudent 
                JOIN tawasulActivity ON (tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID)
                JOIN tawasulActivityCategory ON (tawasulActivityCategory.tawasulActivityCategoryID=tawasulActivity.tawasulActivityCategoryID)
                WHERE tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID
                AND tawasulActivityStudent.tawasulPersonID=:tawasulPersonID
                AND CURRENT_TIMESTAMP >= tawasulActivityCategory.accessEnrolmentDate";

        return $this->db()->select($sql, $data);
    }

    public function selectWeekdayNamesByActivity($tawasulActivityID)
    {
        $data = array('tawasulActivityID' => $tawasulActivityID);
        $sql = "SELECT DISTINCT nameShort 
                FROM tawasulActivitySlot 
                JOIN tawasulDaysOfWeek ON (tawasulActivitySlot.tawasulDaysOfWeekID=tawasulDaysOfWeek.tawasulDaysOfWeekID) 
                WHERE tawasulActivityID=:tawasulActivityID 
                ORDER BY sequenceNumber";

        return $this->db()->select($sql, $data);
    }

    public function selectActivityTypeOptions()
    {
        $sql = "SELECT name as value, name FROM tawasulActivityType ORDER BY name";

        return $this->db()->select($sql);
    }
    
    public function selectActivitiesBySchoolYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulActivity.tawasulActivityID AS value, name FROM tawasulActivity WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND active='Y' ORDER BY name, programStart";

        return $this->db()->select($sql, $data);
    }

    public function selectActivitiesByCategoryAndSchoolYear($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID];
        $sql = "SELECT tawasulActivity.tawasulActivityCategoryID, tawasulActivity.tawasulActivityID, name FROM tawasulActivity WHERE tawasulSchoolYearID=:tawasulSchoolYearID AND active='Y' ORDER BY name, programStart";

        return $this->db()->select($sql, $data);
    }

    public function selectActivitiesByCategory($tawasulActivityCategoryID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID];
        $sql = "SELECT tawasulActivity.tawasulActivityID as value, name FROM tawasulActivity WHERE tawasulActivityCategoryID=:tawasulActivityCategoryID AND active='Y' ORDER BY name, programStart";

        return $this->db()->select($sql, $data);
    }

    public function selectActivitiesByCategoryAndPerson($tawasulActivityCategoryID, $tawasulPersonID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulActivity.tawasulActivityID AS value, tawasulActivity.name 
                FROM tawasulActivity
                JOIN tawasulYearGroup ON (FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulActivity.tawasulYearGroupIDList))
                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulActivity.tawasulSchoolYearID AND tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
                WHERE tawasulActivity.tawasulActivityCategoryID=:tawasulActivityCategoryID 
                AND tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID
                AND tawasulActivity.active='Y'
                AND tawasulActivity.registration='Y'
                ORDER BY tawasulActivity.name, tawasulActivity.programStart";

        return $this->db()->select($sql, $data);
    }

    public function getActivityDetailsByID($tawasulActivityID)
    {
        $data = ['tawasulActivityID' => $tawasulActivityID];
        $sql = "SELECT tawasulActivity.*,
                tawasulActivity.payment as cost,
                tawasulActivity.paymentType as costType,
                tawasulActivity.paymentFirmness as costStatus,
                tawasulActivity.paymentDescription as costDescription,
                tawasulActivityType.access,
                tawasulActivityType.maxPerStudent,
                tawasulActivityType.enrolmentType,
                tawasulActivityType.backupChoice,
                tawasulActivity.registration,
                tawasulSpace.tawasulSpaceID,
                tawasulActivitySlot.timeStart,
                tawasulActivitySlot.timeEnd,
                tawasulActivitySlot.locationExternal,
                tawasulSpace.name as space,
                tawasulDaysOfWeek.name as dayOfWeek,
                GROUP_CONCAT(DISTINCT tawasulYearGroup.nameShort ORDER BY tawasulYearGroup.sequenceNumber SEPARATOR ', ') as yearGroups,
                COUNT(DISTINCT tawasulYearGroup.tawasulYearGroupID) as yearGroupCount
            FROM tawasulActivity 
            LEFT JOIN tawasulYearGroup ON (FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulActivity.tawasulYearGroupIDList))
            LEFT JOIN tawasulActivityType ON (tawasulActivity.type=tawasulActivityType.name)
            LEFT JOIN tawasulActivitySlot ON (tawasulActivity.tawasulActivityID=tawasulActivitySlot.tawasulActivityID)
            LEFT JOIN tawasulDaysOfWeek ON (tawasulActivitySlot.tawasulDaysOfWeekID=tawasulDaysOfWeek.tawasulDaysOfWeekID)
            LEFT JOIN tawasulSpace ON (tawasulSpace.tawasulSpaceID=tawasulActivitySlot.tawasulSpaceID)
            WHERE tawasulActivity.tawasulActivityID=:tawasulActivityID
            GROUP BY tawasulActivity.tawasulActivityID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getActivityDetailsByTimeSlot($tawasulActivitySlotID)
    {
        $data = ['tawasulActivitySlotID' => $tawasulActivitySlotID];
        $sql = "SELECT tawasulActivityID FROM tawasulActivitySlot WHERE tawasulActivitySlotID=:tawasulActivitySlotID";

        $tawasulActivityID = $this->db()->selectOne($sql, $data);

        return $this->getActivityDetailsByID($tawasulActivityID);
    }

    public function selectActivityDetailsByCategory($tawasulActivityCategoryID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID];
        $sql = "SELECT tawasulActivity.tawasulActivityID as groupBy, 
                tawasulActivity.*,
                tawasulSpace.tawasulSpaceID,
                tawasulActivitySlot.timeStart,
                tawasulActivitySlot.timeEnd,
                tawasulActivitySlot.locationExternal,
                tawasulSpace.name as space,
                tawasulDaysOfWeek.name as dayOfWeek,
                COUNT(DISTINCT tawasulActivityStudent.tawasulActivityStudentID) as enrolmentCount,
                GROUP_CONCAT(DISTINCT tawasulYearGroup.nameShort ORDER BY tawasulYearGroup.sequenceNumber SEPARATOR ', ') as yearGroups,
                COUNT(DISTINCT tawasulYearGroup.tawasulYearGroupID) as yearGroupCount
            FROM tawasulActivity 
            LEFT JOIN tawasulYearGroup ON (FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulActivity.tawasulYearGroupIDList))
            LEFT JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID AND tawasulActivityStudent.status='Accepted')
            LEFT JOIN tawasulActivityType ON (tawasulActivity.type=tawasulActivityType.name)
            LEFT JOIN tawasulActivitySlot ON (tawasulActivity.tawasulActivityID=tawasulActivitySlot.tawasulActivityID)
            LEFT JOIN tawasulDaysOfWeek ON (tawasulActivitySlot.tawasulDaysOfWeekID=tawasulDaysOfWeek.tawasulDaysOfWeekID)
            LEFT JOIN tawasulSpace ON (tawasulSpace.tawasulSpaceID=tawasulActivitySlot.tawasulSpaceID)
            WHERE tawasulActivity.tawasulActivityCategoryID=:tawasulActivityCategoryID
            AND tawasulActivity.active='Y'
            GROUP BY tawasulActivity.tawasulActivityID";

        return $this->db()->select($sql, $data);
    }

    function getStudentActivityCountByType($type, $tawasulPersonID)
    {
        $data = array('tawasulPersonID' => $tawasulPersonID, 'type' => $type, 'date' => date('Y-m-d'));
        $sql = "SELECT COUNT(*) 
                FROM tawasulActivity 
                JOIN tawasulActivityStudent ON (tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID) 
                JOIN tawasulSchoolYear ON (tawasulSchoolYear.tawasulSchoolYearID=tawasulActivity.tawasulSchoolYearID)
                WHERE tawasulActivityStudent.tawasulPersonID=:tawasulPersonID 
                AND tawasulActivityStudent.status='Accepted' 
                AND tawasulActivity.type=:type
                AND tawasulActivity.active='Y'
                AND :date BETWEEN tawasulSchoolYear.firstDay AND tawasulSchoolYear.lastDay";
        return $this->db()->selectOne($sql, $data);
    }

    function getOverlappingActivityTimeSlot($tawasulActivityID, $tawasulPersonID, $dateType)
    {
        $data = ['tawasulActivityID' => $tawasulActivityID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT existingActivity.tawasulActivityID as id, existingActivity.name
                    FROM tawasulActivity as sourceActivity
                    JOIN tawasulActivitySlot as sourceSlot ON (sourceActivity.tawasulActivityID=sourceSlot.tawasulActivityID)
                    JOIN tawasulActivity as existingActivity ON (existingActivity.tawasulSchoolYearID=sourceActivity.tawasulSchoolYearID)
                    LEFT JOIN tawasulActivitySlot as existingSlot ON (existingActivity.tawasulActivityID=existingSlot.tawasulActivityID)
                    LEFT JOIN tawasulActivityStudent as existingEnrolment ON (existingActivity.tawasulActivityID=existingEnrolment.tawasulActivityID AND existingEnrolment.tawasulPersonID=:tawasulPersonID ) 
                WHERE sourceActivity.tawasulActivityID=:tawasulActivityID
                    AND existingEnrolment.status='Accepted' 
                    AND existingActivity.active='Y'
                    AND existingSlot.tawasulDaysOfWeekID=sourceSlot.tawasulDaysOfWeekID
                    AND (
                        (existingSlot.timeStart >= sourceSlot.timeStart AND existingSlot.timeStart < sourceSlot.timeEnd) OR
                        (sourceSlot.timeStart >= existingSlot.timeStart AND sourceSlot.timeStart < existingSlot.timeEnd)
                    )
                ";

        if ($dateType == 'Date') {
            $sql .= "AND (
                (existingActivity.programStart >= sourceActivity.programStart AND existingActivity.programStart < sourceActivity.programEnd) OR
                (sourceActivity.programStart >= existingActivity.programStart AND sourceActivity.programStart < existingActivity.programEnd)
            )";
        } else if ($dateType == 'Term') {
            $sql .= "AND sourceActivity.tawasulSchoolYearTermIDList LIKE CONCAT('%',existingActivity.tawasulSchoolYearTermIDList,'%')";
        }

        return $this->db()->select($sql, $data);
    }

    public function getActivitySignUpAccess($tawasulActivityID, $tawasulPersonID)
    {
        $data = ['tawasulActivityID' => $tawasulActivityID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulStudentEnrolment.tawasulStudentEnrolmentID
                FROM tawasulActivity
                JOIN tawasulActivityCategory ON (tawasulActivity.tawasulActivityCategoryID=tawasulActivityCategory.tawasulActivityCategoryID)
                JOIN tawasulYearGroup ON (FIND_IN_SET(tawasulYearGroup.tawasulYearGroupID, tawasulActivity.tawasulYearGroupIDList))
                JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulActivityCategory.tawasulSchoolYearID AND tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
                WHERE tawasulActivity.tawasulActivityID=:tawasulActivityID
                AND tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID
                AND tawasulActivity.registration='Y'
                GROUP BY tawasulActivity.tawasulActivityID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getNextActivityByID($tawasulActivityCategoryID, $tawasulActivityID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID, 'tawasulActivityID' => $tawasulActivityID];
        $sql = "SELECT * FROM tawasulActivity WHERE name=(SELECT MIN(name) FROM tawasulActivity WHERE name > (SELECT name FROM tawasulActivity WHERE tawasulActivityID=:tawasulActivityID AND tawasulActivityCategoryID=:tawasulActivityCategoryID) AND tawasulActivityCategoryID=:tawasulActivityCategoryID AND active='Y') AND tawasulActivityCategoryID=:tawasulActivityCategoryID AND active='Y'";

        return $this->db()->selectOne($sql, $data);
    }

    public function getPreviousActivityByID($tawasulActivityCategoryID, $tawasulActivityID)
    {
        $data = ['tawasulActivityCategoryID' => $tawasulActivityCategoryID, 'tawasulActivityID' => $tawasulActivityID];
        $sql = "SELECT * FROM tawasulActivity WHERE name=(SELECT MAX(name) FROM tawasulActivity WHERE name < (SELECT name FROM tawasulActivity WHERE tawasulActivityID=:tawasulActivityID AND tawasulActivityCategoryID=:tawasulActivityCategoryID) AND tawasulActivityCategoryID=:tawasulActivityCategoryID AND active='Y') AND tawasulActivityCategoryID=:tawasulActivityCategoryID AND active='Y'";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectActivitiesByStaff($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulActivity.tawasulActivityID as value, name FROM tawasulActivity JOIN tawasulActivityStaff ON (tawasulActivityStaff.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE tawasulPersonID=:tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID AND active='Y' ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    public function selectActivitiesByStudent($tawasulSchoolYearID, $tawasulPersonID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulPersonID' => $tawasulPersonID];
        $sql = "SELECT tawasulActivity.tawasulActivityID as value, name FROM tawasulActivity JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID) WHERE tawasulPersonID=:tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID AND status='Accepted' AND active='Y' ORDER BY name";
        
        return $this->db()->select($sql, $data);
    }
}
