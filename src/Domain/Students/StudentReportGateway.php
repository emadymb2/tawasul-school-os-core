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
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\Traits\SharedUserLogic;

/**
 * @version v17
 * @since   v17
 */
class StudentReportGateway extends QueryableGateway
{
    use TableAware;
    use SharedUserLogic;

    private static $tableName = 'tawasulStudentEnrolment';
    private static $searchableColumns = ['tawasulPerson.transport', 'tawasulPerson.surname', 'tawasulPerson.preferredName'];


    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryStudentDetails(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonID)
    {
        $tawasulPersonIDList = is_array($tawasulPersonID) ? implode(',', $tawasulPersonID) : $tawasulPersonID;

        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.*', 'tawasulPersonMedical.*',
                'tawasulFormGroup.nameShort as formGroup',
                "(SELECT timestamp FROM tawasulPersonUpdate WHERE tawasulPersonID=tawasulPerson.tawasulPersonID AND status='Complete' ORDER BY timestamp DESC LIMIT 1) as lastPersonalUpdate",
                "(SELECT timestamp FROM tawasulPersonMedicalUpdate WHERE tawasulPersonID=tawasulPerson.tawasulPersonID AND status='Complete' ORDER BY timestamp DESC LIMIT 1) as lastMedicalUpdate",
                'tawasulPerson.tawasulPersonID'
            ])
            ->leftJoin('tawasulPersonMedical', 'tawasulPersonMedical.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->where('FIND_IN_SET(tawasulPerson.tawasulPersonID, :tawasulPersonIDList)')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->bindValue('tawasulPersonIDList', $tawasulPersonIDList);

        return $this->runQuery($query, $criteria);
    }

    public function queryStudentTransport(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.tawasulPersonID', 'tawasulPerson.transport', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.address1', 'tawasulPerson.address1District', 'tawasulPerson.address1Country', 'tawasulFormGroup.nameShort as formGroup',
            ])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("tawasulPerson.status = 'Full'")
            ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)')
            ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
            ->bindValue('today', date('Y-m-d'));

        $criteria->addFilterRules([
            'transport' => function ($query, $transport) {
                return $query
                    ->where('tawasulPerson.transport=:transport')
                    ->bindValue('transport', $transport);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryStudentCountByFormGroup(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulFormGroup')
            ->cols([
                'tawasulFormGroup.name as formGroup',
                'tawasulYearGroup.sequenceNumber',
                'FORMAT(AVG((TO_DAYS(NOW())-TO_DAYS(tawasulPerson.dob)))/365.242199, 1) as meanAge',
                "count(DISTINCT tawasulPerson.tawasulPersonID) AS total",
                "count(CASE WHEN tawasulPerson.gender='M' THEN tawasulPerson.tawasulPersonID END) as totalMale",
                "count(CASE WHEN tawasulPerson.gender='F' THEN tawasulPerson.tawasulPersonID END) as totalFemale",
                "count(CASE WHEN tawasulPerson.gender='Other' THEN tawasulPerson.tawasulPersonID END) as totalOther",
                "count(CASE WHEN tawasulPerson.gender='Unspecified' THEN tawasulPerson.tawasulPersonID END) as totalUnspecified",
            ])
            ->leftJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->leftJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->leftJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID')
            ->where('tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->groupBy(['tawasulFormGroup.tawasulFormGroupID']);

        if (!$criteria->hasFilter('from')) {
            $query->where("tawasulPerson.status='Full'");
        }

        $criteria->addFilterRules([
            'from' => function ($query, $date) {
                return $query
                    ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart<=:date)')
                    ->bindValue('date', $date);
            },
            'to' => function ($query, $date) {
                return $query
                    ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd>=:date)')
                    ->bindValue('date', $date);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryStudentPrivacyChoices(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.tawasulPersonID', 'tawasulPerson.privacy', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.image_240', 'tawasulFormGroup.nameShort as formGroup',
            ])
            ->innerJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID')
            ->innerJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("(tawasulPerson.privacy <> '' AND tawasulPerson.privacy IS NOT NULL)")
            ->where("tawasulPerson.status = 'Full'")
            ->where("tawasulPerson.status = 'Full'")
            ->where('(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= :today)')
            ->where('(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= :today)')
            ->bindValue('today', date('Y-m-d'));

        return $this->runQuery($query, $criteria);
    }

    public function queryStudentStatusBySchoolYear(QueryCriteria $criteria, $tawasulSchoolYearID, $status = 'Full', $dateFrom = null, $dateTo = null, $ignoreEnrolment = false)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from('tawasulPerson')
            ->cols([
                'tawasulPerson.tawasulPersonID', 'tawasulStudentEnrolmentID', 'tawasulPerson.title', 'tawasulPerson.preferredName', 'tawasulPerson.surname', 'tawasulPerson.username', 'officialName', 'tawasulYearGroup.nameShort AS yearGroup', 'tawasulFormGroup.nameShort AS formGroup', 'tawasulStudentEnrolment.rollOrder', 'tawasulPerson.dateStart', 'tawasulPerson.dateEnd', 'tawasulPerson.status', 'tawasulPerson.lastSchool', 'tawasulPerson.departureReason', 'tawasulPerson.nextSchool', "'Student' as roleCategory"
            ])
            ->leftJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->leftJoin('tawasulSchoolYear AS currentSchoolYear', 'currentSchoolYear.tawasulSchoolYearID = tawasulStudentEnrolment.tawasulSchoolYearID')
            ->leftJoin('tawasulYearGroup', 'tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID')
            ->leftJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);

        if ($ignoreEnrolment) {
            $query->innerJoin('tawasulRole', 'FIND_IN_SET(tawasulRole.tawasulRoleID, tawasulPerson.tawasulRoleIDAll)')
                  ->where("tawasulRole.category='Student'");
        } else {
            $query->where("tawasulStudentEnrolment.tawasulStudentEnrolmentID IS NOT NULL")
                  ->where('tawasulPerson.status = :status')
                  ->bindValue('status', $status);
        }

        if (!empty($dateFrom) && !empty($dateTo)) {
            $query->where($status == 'Full'
                ? 'tawasulPerson.dateStart BETWEEN :dateFrom AND :dateTo'
                : 'tawasulPerson.dateEnd BETWEEN :dateFrom AND :dateTo')
            ->bindValue('dateFrom', $dateFrom)
            ->bindValue('dateTo', $dateTo);
        }

        if ($status == 'Full' && empty($dateFrom)) {
            // This ensures the new student list for the current year excludes any students who were enrolled in the previous year
            $query->cols(['(
                SELECT COUNT(*) FROM tawasulStudentEnrolment AS pastEnrolment WHERE pastEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND pastEnrolment.tawasulSchoolYearID=(
                    SELECT tawasulSchoolYearID FROM tawasulSchoolYear WHERE sequenceNumber=(
                        SELECT MAX(sequenceNumber) FROM tawasulSchoolYear WHERE sequenceNumber < currentSchoolYear.sequenceNumber
                        )
                    )
                ) AS pastEnrolmentCount'])
                ->having('pastEnrolmentCount = 0');
        }

        return $this->runQuery($query, $criteria);
    }

    public function selectStudentCountByYearGroup($tawasulSchoolYearID)
    {
        $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'today' => date('Y-m-d')];
        $sql = "SELECT tawasulYearGroup.nameShort as yearGroup, count(DISTINCT tawasulStudentEnrolmentID) as studentCount
                FROM tawasulStudentEnrolment
                JOIN tawasulPerson ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                JOIN tawasulYearGroup ON (tawasulStudentEnrolment.tawasulYearGroupID=tawasulYearGroup.tawasulYearGroupID)
                JOIN tawasulSchoolYear ON (tawasulStudentEnrolment.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID)
                WHERE tawasulPerson.status='Full'
                AND tawasulSchoolYear.tawasulSchoolYearID=:tawasulSchoolYearID
                AND (dateStart IS NULL OR dateStart<=:today)
                AND (dateEnd IS NULL OR dateEnd>=:today)
                GROUP BY tawasulYearGroup.tawasulYearGroupID
                ORDER BY tawasulYearGroup.sequenceNumber";

        return $this->db()->select($sql, $data);
    }
}
