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

namespace TawasulOS\Domain\Calendar;

use TawasulOS\Domain\Traits\TableAware;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v29
 * @since   v29
 */
class CalendarEventPersonGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulCalendarEventPerson';
    private static $primaryKey = 'tawasulCalendarEventPersonID';

    private static $searchableColumns = [];

    public function queryEventAttendees($criteria, $tawasulCalendarEventID) {
        $query = $this
            ->newQuery()
            ->cols(['tawasulCalendarEventPerson.*', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.image_240', 'tawasulRole.category as roleCategory', 'tawasulStudentEnrolment.tawasulFormGroupID', 'tawasulFormGroup.nameShort as formGroup', 'tawasulStudentEnrolment.tawasulYearGroupID', 'tawasulYearGroup.nameShort as yearGroup', 'tawasulYearGroup.sequenceNumber as yearGroupSequence'])
            ->from($this->getTableName())
            ->innerJoin('tawasulCalendarEvent', 'tawasulCalendarEvent.tawasulCalendarEventID=tawasulCalendarEventPerson.tawasulCalendarEventID')
            ->innerJoin('tawasulCalendar', 'tawasulCalendarEvent.tawasulCalendarID=tawasulCalendar.tawasulCalendarID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulCalendarEventPerson.tawasulPersonID')
            ->leftJoin('tawasulRole', 'tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID')
            ->leftJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulCalendar.tawasulSchoolYearID')
            ->leftJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->leftJoin('tawasulYearGroup', 'tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID')
            ->where('tawasulCalendarEventPerson.tawasulCalendarEventID = :tawasulCalendarEventID')
            ->bindValue('tawasulCalendarEventID', $tawasulCalendarEventID)
            ->where('tawasulCalendarEventPerson.role = :role')
            ->bindValue('role', 'Attendee');

        return $this->runQuery($query, $criteria);
    }

    public function queryAllEventParticipants($criteria, $tawasulCalendarEventID) {
        $query = $this
            ->newQuery()
            ->cols(['tawasulCalendarEventPerson.*', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulPerson.image_240', 'tawasulRole.category as roleCategory', 'tawasulFormGroup.nameShort as formGroup'])
            ->from($this->getTableName())
            ->innerJoin('tawasulCalendarEvent', 'tawasulCalendarEventPerson.tawasulCalendarEventID=tawasulCalendarEvent.tawasulCalendarEventID')
            ->innerJoin('tawasulCalendar', 'tawasulCalendarEvent.tawasulCalendarID=tawasulCalendar.tawasulCalendarID')
            ->innerJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulCalendarEventPerson.tawasulPersonID')
            ->leftJoin('tawasulRole', 'tawasulPerson.tawasulRoleIDPrimary=tawasulRole.tawasulRoleID')
            ->leftJoin('tawasulStudentEnrolment', 'tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulSchoolYearID=tawasulCalendar.tawasulSchoolYearID')
            ->leftJoin('tawasulFormGroup', 'tawasulFormGroup.tawasulFormGroupID=tawasulStudentEnrolment.tawasulFormGroupID')
            ->where('tawasulCalendarEventPerson.tawasulCalendarEventID = :tawasulCalendarEventID')
            ->bindValue('tawasulCalendarEventID', $tawasulCalendarEventID);

        return $this->runQuery($query, $criteria);
    }

    public function selectEventStaff($tawasulCalendarEventID) {
        $select = $this
            ->newSelect()
            ->cols(['preferredName, surname, tawasulCalendarEventPerson.*'])
            ->from($this->getTableName())
            ->leftJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulCalendarEventPerson.tawasulPersonID')
            ->where('tawasulCalendarEventPerson.tawasulCalendarEventID = :tawasulCalendarEventID')
            ->bindValue('tawasulCalendarEventID', $tawasulCalendarEventID)
            ->where('tawasulCalendarEventPerson.role != :role')
            ->bindValue('role', 'Attendee')
            ->orderBy(['surname', 'preferredName']);

        return $this->runSelect($select);
    }

    public function selectEventParticipantConflicts($tawasulCalendarEventID) {
        $select = $this
            ->newSelect()
            ->cols(['otherPerson.tawasulPersonID as groupBy', 'otherPerson.tawasulPersonID', 'otherEvent.name as event', 'otherPerson.role', 'otherEvent.tawasulCalendarEventID'])
            ->from('tawasulCalendarEvent')
            ->innerJoin('tawasulCalendarEvent as otherEvent', 'otherEvent.tawasulCalendarEventID <> tawasulCalendarEvent.tawasulCalendarEventID AND ((otherEvent.dateStart >= tawasulCalendarEvent.dateStart AND otherEvent.dateStart <= tawasulCalendarEvent.dateEnd) OR (tawasulCalendarEvent.dateStart >= otherEvent.dateStart AND tawasulCalendarEvent.dateStart <= otherEvent.dateEnd))')
            ->innerJoin('tawasulCalendarEventPerson as eventPerson', 'eventPerson.tawasulCalendarEventID=tawasulCalendarEvent.tawasulCalendarEventID')
            ->innerJoin('tawasulCalendarEventPerson as otherPerson', 'otherPerson.tawasulCalendarEventID=otherEvent.tawasulCalendarEventID AND eventPerson.tawasulPersonID=otherPerson.tawasulPersonID')
            ->where('tawasulCalendarEvent.tawasulCalendarEventID = :tawasulCalendarEventID')
            ->where('((otherEvent.timeStart >= tawasulCalendarEvent.timeStart AND otherEvent.timeStart < tawasulCalendarEvent.timeEnd) OR (tawasulCalendarEvent.timeStart >= otherEvent.timeStart AND tawasulCalendarEvent.timeStart < otherEvent.timeEnd) OR (otherEvent.allDay <> tawasulCalendarEvent.allDay))')
            ->bindValue('tawasulCalendarEventID', $tawasulCalendarEventID);

        return $this->runSelect($select);
    }
    
    public function selectTargetParticipants($tawasulSchoolYearID, $targetStudents, $targetID)
    {
        switch ($targetStudents) {
            case 'Activity':
                $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulActivityID' => $targetID];
                $sql = "SELECT tawasulPerson.tawasulPersonID
                        FROM tawasulStudentEnrolment
                        JOIN tawasulPerson ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        JOIN tawasulActivityStudent ON (tawasulActivityStudent.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                        AND tawasulActivityStudent.tawasulActivityID=:tawasulActivityID
                        AND tawasulActivityStudent.status='Accepted'
                        AND tawasulPerson.status='Full'
                        ORDER BY tawasulPerson.surname, tawasulPerson.preferredName";
                    break;
            case 'Messenger':
                $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulGroupID' => $targetID];
                $sql = "SELECT tawasulPerson.tawasulPersonID
                        FROM tawasulStudentEnrolment 
                        JOIN tawasulPerson ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        JOIN tawasulGroupPerson ON (tawasulGroupPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                        AND tawasulGroupPerson.tawasulGroupID=:tawasulGroupID
                        AND tawasulPerson.status='Full' 
                        ORDER BY tawasulPerson.surname, tawasulPerson.preferredName";
                    break;
             case 'Class':
                $data = ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulCourseClassID' => $targetID];
                $sql = "SELECT tawasulCourseClassPerson.tawasulPersonID
                        FROM tawasulCourseClassPerson
                        JOIN tawasulCourseClass ON (tawasulCourseClass.tawasulCourseClassID=tawasulCourseClassPerson.tawasulCourseClassID)
                        JOIN tawasulPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID
                        AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID
                        AND tawasulPerson.status='Full'
                        AND tawasulCourseClassPerson.role='Student'
                        GROUP BY tawasulCourseClassPerson.tawasulPersonID
                        ORDER BY tawasulPerson.surname, tawasulPerson.preferredName";
                    break;
            case 'Individual':
                $data = ['tawasulPersonIDList' => implode(',', $targetID)];
                $sql = "SELECT tawasulPersonID
                        FROM tawasulPerson
                        WHERE FIND_IN_SET(tawasulPerson.tawasulPersonID, :tawasulPersonIDList)
                        AND tawasulPerson.status='Full' 
                        ORDER BY tawasulPerson.surname, tawasulPerson.preferredName";
                break;
        }

        return $this->db()->select($sql, $data);
    }
}
