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

use TawasulOS\Services\Format;
use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v17
 * @since   v17
 */
class ActivityReportGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulActivity';

    private static $searchableColumns = ['tawasulActivity.name', 'tawasulActivity.type'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryActivityEnrollmentSummary(QueryCriteria $criteria, $tawasulSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulActivity.tawasulActivityID', 'tawasulActivity.name', 'tawasulActivity.active', 'tawasulActivity.provider', 'tawasulActivity.registration', 'tawasulActivity.type', 'maxParticipants',
                "COUNT(DISTINCT CASE WHEN tawasulActivityStudent.status = 'Accepted' THEN tawasulActivityStudent.tawasulPersonID END) as enrolment",
                "COUNT(DISTINCT CASE WHEN tawasulActivityStudent.status <> 'Not Accepted' THEN tawasulActivityStudent.tawasulPersonID END) as registered",
            ])
            ->leftJoin('tawasulActivityStudent', 'tawasulActivityStudent.tawasulActivityID=tawasulActivity.tawasulActivityID')
            ->leftJoin('tawasulPerson', "tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulPerson.status = 'Full'")
            ->leftJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL OR dateEnd>=:today)')
            ->bindValue('today', date('Y-m-d'))
            ->where('tawasulActivity.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->groupBy(['tawasulActivity.tawasulActivityID']);

        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('tawasulActivity.active = :active')
                    ->bindValue('active', $active);
            },
            'registration' => function ($query, $registration) {
                return $query
                    ->where('tawasulActivity.registration = :registration')
                    ->bindValue('registration', $registration);
            },
            'enrolment' => function ($query, $enrolment) {
                if ($enrolment == 'less') {
                    $query->having('enrolment < tawasulActivity.maxParticipants AND tawasulActivity.maxParticipants > 0');
                }
                if ($enrolment == 'full') {
                    $query->having('enrolment = tawasulActivity.maxParticipants AND tawasulActivity.maxParticipants > 0');
                }
                if ($enrolment == 'greater') {
                    $query->having('enrolment > tawasulActivity.maxParticipants AND tawasulActivity.maxParticipants > 0');
                }
                return $query;
            },
            'status' => function ($query, $status) {
                if ($status == 'waiting') {
                    $query->having('waiting > 0');
                }
                if ($status == 'pending') {
                    $query->having('pending > 0');
                }
                return $query;
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryParticipantsByActivity(QueryCriteria $criteria, $tawasulActivityID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols(['tawasulPerson.tawasulPersonID', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.dob', 'tawasulActivityStudent.status', 'tawasulFormGroup.nameShort AS formGroup'])
            ->innerJoin('tawasulActivityStudent', 'tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID')
            ->innerJoin('tawasulPerson', "tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID")
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->where('tawasulActivity.tawasulActivityID = :tawasulActivityID')
            ->bindValue('tawasulActivityID', $tawasulActivityID)
            ->where("tawasulActivityStudent.status <> 'Not Accepted'")
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID=tawasulActivity.tawasulSchoolYearID')
            ->where("tawasulPerson.status = 'Full'")
            ->where('(dateStart IS NULL OR dateStart<=:today)')
            ->where('(dateEnd IS NULL OR dateEnd>=:today)')
            ->bindValue('today', date('Y-m-d'));

        return $this->runQuery($query, $criteria);
    }

    public function queryActivityAttendanceByDate(QueryCriteria $criteria, $tawasulSchoolYearID, $dateType, $date)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulActivity.tawasulActivityID', 'tawasulActivity.name as activity', 'tawasulActivity.provider', 'tawasulPerson.tawasulPersonID', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulActivityStudent.status', 'tawasulFormGroup.nameShort AS formGroup',
                "(CASE WHEN tawasulActivityAttendance.tawasulActivityAttendanceID IS NULL THEN 'Absent' ELSE 'Present' END) AS attendance"
            ])
            ->innerJoin('tawasulActivitySlot', 'tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID')
            ->innerJoin('tawasulDaysOfWeek', 'tawasulActivitySlot.tawasulDaysOfWeekID=tawasulDaysOfWeek.tawasulDaysOfWeekID')
            ->innerJoin('tawasulActivityStudent', 'tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID')
            ->innerJoin('tawasulPerson', "tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID")
            ->innerJoin('tawasulStudentEnrolment', 'tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID')
            ->innerJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->leftJoin('tawasulActivityAttendance', "tawasulActivityAttendance.tawasulActivityID=tawasulActivity.tawasulActivityID
                AND tawasulActivityAttendance.date = :date
                AND (tawasulActivityAttendance.attendance LIKE CONCAT('%', tawasulPerson.tawasulPersonID, '%') )")
            ->where('tawasulActivity.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where("tawasulActivity.active = 'Y'")
            ->where('tawasulDaysOfWeek.name=:dayOfWeek')
            ->bindValue('dayOfWeek', date('l', Format::timestamp($date)))
            ->where('tawasulStudentEnrolment.tawasulSchoolYearID=tawasulActivity.tawasulSchoolYearID')
            ->where("tawasulActivityStudent.status='Accepted'")
            ->where("tawasulPerson.status = 'Full'")
            ->where('(dateStart IS NULL OR dateStart<=:today)')
            ->where('(dateEnd IS NULL OR dateEnd>=:today)')
            ->bindValue('today', date('Y-m-d'))
            ->bindValue('date', $date)
            ->groupBy(['tawasulActivity.tawasulActivityID', 'tawasulActivityStudent.tawasulPersonID']);

        if ($dateType == 'Term') {
            $query->innerJoin('tawasulSchoolYearTerm', "FIND_IN_SET(tawasulSchoolYearTermID, tawasulActivity.tawasulSchoolYearTermIDList)")
                ->where('(:date BETWEEN tawasulSchoolYearTerm.firstDay AND tawasulSchoolYearTerm.lastDay)');
        } else {
            $query->where('(:date BETWEEN tawasulActivity.programStart AND tawasulActivity.programEnd)');
        }

        return $this->runQuery($query, $criteria);
    }

    public function selectActivitiesByStudent($tawasulSchoolYearID, $tawasulPersonID, $status = 'Accepted')
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulActivityStudent.tawasulPersonID', 'tawasulActivityStudent.status', 'tawasulActivity.*'
            ])
            ->innerJoin('tawasulActivityStudent', 'tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID')
            ->where('tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulActivityStudent.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->orderBy(['tawasulActivity.name']);

        if ($status == 'Accepted') {
            $query->where("tawasulActivityStudent.status='Accepted'");
        } else {
            $query->where("tawasulActivityStudent.status<>'Not Accepted'");
        }

        return $this->db()->select($query->getStatement(), $query->getBindValues());
    }

    public function selectActivitySpreadByStudent($tawasulSchoolYearID, $tawasulPersonID, $dateType, $status = 'Accepted')
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols($dateType == 'Term'
                ? ["CONCAT(tawasulSchoolYearTerm.tawasulSchoolYearTermID, '-', tawasulActivitySlot.tawasulDaysOfWeekID) AS groupBy"]
                : ['tawasulActivitySlot.tawasulDaysOfWeekID AS groupBy'])
            ->cols([
                'tawasulActivityStudent.tawasulPersonID',
                'COUNT(DISTINCT tawasulActivityStudent.tawasulActivityStudentID) AS count',
                "COUNT(DISTINCT CASE WHEN tawasulActivityStudent.status<>'Accepted' THEN tawasulActivityStudent.tawasulActivityStudentID END) AS notAccepted",
                "GROUP_CONCAT(DISTINCT tawasulActivity.name SEPARATOR ', ') AS activityNames"
            ])
            ->innerJoin('tawasulActivityStudent', 'tawasulActivity.tawasulActivityID=tawasulActivityStudent.tawasulActivityID')
            ->innerJoin('tawasulActivitySlot', 'tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID')
            ->where('tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->where('tawasulActivityStudent.tawasulPersonID=:tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID);

        if ($status == 'Accepted') {
            $query->where("tawasulActivityStudent.status='Accepted'");
        } else {
            $query->where("tawasulActivityStudent.status<>'Not Accepted'");
        }

        if ($dateType == 'Term') {
            $query->innerJoin('tawasulSchoolYearTerm', 'FIND_IN_SET(tawasulSchoolYearTerm.tawasulSchoolYearTermID, tawasulActivity.tawasulSchoolYearTermIDList)')
                ->groupBy(['tawasulSchoolYearTerm.tawasulSchoolYearTermID', 'tawasulActivitySlot.tawasulDaysOfWeekID']);
        } else {
            $query->groupBy(['tawasulActivitySlot.tawasulDaysOfWeekID']);
        }

        return $this->db()->select($query->getStatement(), $query->getBindValues());
    }

    public function selectActivityWeekdays($tawasulSchoolYearID)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT tawasulDaysOfWeek.*
                FROM tawasulDaysOfWeek 
                JOIN tawasulActivitySlot ON (tawasulActivitySlot.tawasulDaysOfWeekID=tawasulDaysOfWeek.tawasulDaysOfWeekID) 
                JOIN tawasulActivity ON (tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID) 
                WHERE tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID AND schoolDay='Y' 
                GROUP BY tawasulDaysOfWeek.tawasulDaysOfWeekID
                ORDER BY tawasulDaysOfWeek.sequenceNumber";

        return $this->db()->select($sql, $data);
    }

    public function selectActivityWeekdaysPerTerm($tawasulSchoolYearID)
    {
        $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
        $sql = "SELECT tawasulSchoolYearTerm.name, tawasulDaysOfWeek.*, tawasulSchoolYearTerm.name as termName, tawasulSchoolYearTerm.tawasulSchoolYearTermID as tawasulSchoolYearTermID
                FROM tawasulDaysOfWeek 
                JOIN tawasulActivitySlot ON (tawasulActivitySlot.tawasulDaysOfWeekID=tawasulDaysOfWeek.tawasulDaysOfWeekID) 
                JOIN tawasulActivity ON (tawasulActivitySlot.tawasulActivityID=tawasulActivity.tawasulActivityID) 
                JOIN tawasulSchoolYearTerm ON (tawasulSchoolYearTerm.tawasulSchoolYearID=tawasulActivity.tawasulSchoolYearID)
                WHERE tawasulActivity.tawasulSchoolYearID=:tawasulSchoolYearID AND schoolDay='Y' 
                GROUP BY tawasulDaysOfWeek.tawasulDaysOfWeekID, tawasulSchoolYearTerm.tawasulSchoolYearTermID
                ORDER BY tawasulSchoolYearTerm.sequenceNumber, tawasulDaysOfWeek.sequenceNumber";

        return $this->db()->select($sql, $data);
    }

    public function queryStudentActivities(QueryCriteria $criteria)
    {
        $query = $this
        ->newQuery()
        ->cols([
          'tawasulActivity.tawasulActivityID',
          'tawasulActivity.name as activityName',
          'tawasulActivity.type as activityType',
          'tawasulActivity.programStart',
          'tawasulActivity.programEnd',
          'tawasulActivityStudent.status',
          'GROUP_CONCAT(DISTINCT term.nameShort) as terms',
          'tawasulSchoolYear.name as yearName',
          'tawasulSchoolYear.sequenceNumber as yearSequenceNumber',
          'tawasulActivity.active',
          'tawasulPerson.preferredName',
          'tawasulPerson.surname',
          'tawasulPerson.title'
        ])
        ->from('tawasulActivity')
        ->innerJoin('tawasulActivityStudent', 'tawasulActivity.tawasulActivityID = tawasulActivityStudent.tawasulActivityID')
        ->innerJoin('tawasulPerson', 'tawasulActivityStudent.tawasulPersonID = tawasulPerson.tawasulPersonID')
        ->innerJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID = tawasulStudentEnrolment.tawasulPersonID')
        ->innerJoin('tawasulFormGroup', 'tawasulStudentEnrolment.tawasulFormGroupID = tawasulFormGroup.tawasulFormGroupID')
        ->innerJoin('tawasulSchoolYear', 'tawasulStudentEnrolment.tawasulSchoolYearID = tawasulSchoolYear.tawasulSchoolYearID')
        ->leftJoin('tawasulSchoolYearTerm term', 'FIND_IN_SET(term.tawasulSchoolYearTermID, tawasulActivity.tawasulSchoolYearTermIDList) > 0')
        ->where("tawasulPerson.status = 'Full'")
        ->where("(tawasulPerson.dateStart IS NULL OR tawasulPerson.dateStart <= CURRENT_TIMESTAMP)")
        ->where("(tawasulPerson.dateEnd IS NULL OR tawasulPerson.dateEnd >= CURRENT_TIMESTAMP)")
        ->groupBy([
            'tawasulActivity.tawasulActivityID',
            'tawasulActivityStudent.tawasulActivityStudentID',
            'tawasulPerson.tawasulPersonID',
        ])
        ->distinct();

        $criteria->addFilterRules([
        'tawasulPersonID' => function ($query, $tawasulPersonID) {
            return $query
            ->where('tawasulActivityStudent.tawasulPersonID = :tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID);
        },
        'tawasulSchoolYearID' => function ($query, $tawasulSchoolYearID) {
            return $query
            ->where('tawasulActivity.tawasulSchoolYearID = :tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID);
        },
        'active' => function ($query, $active) {
            return $query
            ->where('tawasulActivity.active = :active')
            ->bindValue('active', $active);
        }
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryStudentYears(QueryCriteria $criteria)
    {
        $query = $this
        ->newQuery()
        ->cols([
          'tawasulSchoolYear.tawasulSchoolYearID',
          'tawasulSchoolYear.name'
        ])
        ->from('tawasulStudentEnrolment')
        ->innerJoin('tawasulSchoolYear', 'tawasulStudentEnrolment.tawasulSchoolYearID = tawasulSchoolYear.tawasulSchoolYearID')
        ->orderBy(['tawasulSchoolYear.sequenceNumber'])
        ->distinct();

        $criteria->addFilterRules([
        'tawasulPersonID' => function ($query, $tawasulPersonID) {
            return $query
            ->where('tawasulStudentEnrolment.tawasulPersonID = :tawasulPersonID')
            ->bindValue('tawasulPersonID', $tawasulPersonID);
        }
        ]);
        return $this->runQuery($query, $criteria);
    }
}
