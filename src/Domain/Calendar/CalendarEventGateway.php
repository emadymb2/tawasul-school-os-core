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
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\QueryableGateway;

/**
 * @version v29
 * @since   v29
 */
class CalendarEventGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulCalendarEvent';
    private static $primaryKey = 'tawasulCalendarEventID';
    private static $searchableColumns = ['tawasulCalendarEvent.name', 'tawasulCalendarEvent.description', 'tawasulPerson.surname', 'tawasulPerson.preferredName', 'tawasulCalendar.name', 'tawasulCalendarEventType.type',];


     public function queryEvents(QueryCriteria $criteria, $tawasulSchoolYearID, $tawasulPersonID = null)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->cols([
                'tawasulCalendarEvent.tawasulCalendarEventID',
                'tawasulCalendarEvent.tawasulCalendarID',
                'tawasulCalendarEvent.tawasulCalendarEventTypeID',
                'tawasulCalendarEvent.name as eventName',
                'tawasulCalendarEvent.status',
                'tawasulCalendarEvent.description',
                'tawasulCalendarEvent.dateStart',
                'tawasulCalendarEvent.dateEnd',
                'tawasulCalendarEvent.timeStart',
                'tawasulCalendarEvent.timeEnd',
                'tawasulCalendarEvent.allDay',
                'tawasulCalendarEvent.locationType',
                'tawasulCalendarEvent.locationDetail',
                'tawasulCalendarEvent.locationURL',
                'tawasulCalendarEvent.tawasulPersonIDOrganiser',
                'tawasulCalendar.name as calendarName',
                'tawasulCalendarEventType.type',
                'tawasulPerson.preferredName', 
                'tawasulPerson.surname',
                'tawasulCalendar.color',
                'tawasulSpace.name as space',
                'COUNT(DISTINCT tawasulCalendarEventPerson.tawasulPersonID) as participants'
            ])
            ->from($this->getTableName())
            ->leftJoin('tawasulCalendar', 'tawasulCalendar.tawasulCalendarID=tawasulCalendarEvent.tawasulCalendarID')
            ->leftJoin('tawasulCalendarEventType', 'tawasulCalendarEventType.tawasulCalendarEventTypeID=tawasulCalendarEvent.tawasulCalendarEventTypeID')
            ->leftJoin('tawasulCalendarEventPerson', 'tawasulCalendarEventPerson.tawasulCalendarEventID=tawasulCalendarEvent.tawasulCalendarEventID')
            ->leftJoin('tawasulPerson', 'tawasulPerson.tawasulPersonID=tawasulCalendarEvent.tawasulPersonIDOrganiser')
            ->leftJoin('tawasulSpace', 'tawasulSpace.tawasulSpaceID=tawasulCalendarEvent.tawasulSpaceID')
            ->where('tawasulCalendar.tawasulSchoolYearID=:tawasulSchoolYearID')
            ->bindValue('tawasulSchoolYearID', $tawasulSchoolYearID)
            ->groupBy(['tawasulCalendarEvent.tawasulCalendarEventID']);

        if (!empty($tawasulPersonID)) {
            $query
                ->cols(['editor.tawasulCalendarEditorID', 'editor.editAllEvents', '(CASE WHEN editor.editAllEvents="Y" OR (tawasulCalendarEvent.tawasulPersonIDOrganiser=:tawasulPersonID OR tawasulCalendarEvent.tawasulPersonIDCreated=:tawasulPersonID) THEN "Y" ELSE "N" END) as editor'])
                ->leftJoin('tawasulCalendarEventPerson as participant', 'participant.tawasulCalendarEventID=tawasulCalendarEvent.tawasulCalendarEventID')
                ->leftJoin('tawasulCalendarEditor as editor', 'editor.tawasulCalendarID=tawasulCalendarEvent.tawasulCalendarID AND editor.tawasulPersonID=:tawasulPersonID')
                ->where('((participant.tawasulPersonID=:tawasulPersonID OR tawasulCalendarEvent.tawasulPersonIDOrganiser=:tawasulPersonID OR tawasulCalendarEvent.tawasulPersonIDCreated=:tawasulPersonID) 
                OR (editor.tawasulCalendarEditorID IS NOT NULL AND (editor.editAllEvents="Y" OR (tawasulCalendarEvent.tawasulPersonIDOrganiser=:tawasulPersonID OR tawasulCalendarEvent.tawasulPersonIDCreated=:tawasulPersonID))))')
                ->bindValue('tawasulPersonID', $tawasulPersonID);
        } else {
            $query->cols(['"N" as editor']);
        }

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('tawasulCalendarEvent.status = :status')
                    ->bindValue('status', ucfirst($status));
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectVisibleEventsByPerson($tawasulPersonID, $roleCategory, $dateStart, $dateEnd)
    {
        $query = $this
            ->newSelect()
            ->cols([
                'tawasulCalendarEvent.tawasulCalendarEventID as id', 'tawasulCalendarEvent.name as eventName', 'tawasulCalendarEvent.name as title', 'tawasulCalendarEvent.description', 
                "(CASE WHEN allDay='N' THEN CONCAT(tawasulCalendarEvent.dateStart, 'T', timeStart) ELSE tawasulCalendarEvent.dateStart END) as start", 
                "(CASE WHEN allDay='N' THEN CONCAT(tawasulCalendarEvent.dateEnd, 'T', timeEnd) ELSE DATE_ADD(tawasulCalendarEvent.dateEnd, INTERVAL 1 DAY) END) as end", 'tawasulCalendarEvent.dateStart', 'tawasulCalendarEvent.dateEnd',
                'tawasulCalendar.color', 'tawasulCalendarEventType.type', 'tawasulCalendarEvent.allDay', 'tawasulCalendarEvent.timeStart', 'tawasulCalendarEvent.timeEnd',
                'tawasulCalendar.name as calendar', 'tawasulCalendarEvent.locationType', 'tawasulSpace.phoneInternal AS phone',
                '(CASE WHEN tawasulCalendarEvent.locationType="Internal" THEN tawasulSpace.name ELSE tawasulCalendarEvent.locationDetail END) AS location'
            ])
            ->from($this->getTableName())
            ->innerJoin('tawasulCalendar', 'tawasulCalendar.tawasulCalendarID=tawasulCalendarEvent.tawasulCalendarID')
            ->leftJoin('tawasulCalendarEventType', 'tawasulCalendarEventType.tawasulCalendarEventTypeID=tawasulCalendarEvent.tawasulCalendarEventTypeID')
            ->leftJoin('tawasulCalendarEventPerson', 'tawasulCalendarEvent.tawasulCalendarEventID=tawasulCalendarEventPerson.tawasulCalendarEventID AND tawasulCalendarEventPerson.tawasulPersonID=:tawasulPersonID')
            ->leftJoin('tawasulSpace', 'tawasulSpace.tawasulSpaceID=tawasulCalendarEvent.tawasulSpaceID')
            ->where('(tawasulCalendarEvent.dateStart BETWEEN :dateStart AND :dateEnd OR tawasulCalendarEvent.dateEnd BETWEEN :dateStart AND :dateEnd)')
            ->bindValue('dateStart', $dateStart)
            ->bindValue('dateEnd', $dateEnd)
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->orderBy(['tawasulCalendarEvent.dateStart', 'tawasulCalendarEvent.dateEnd']);

        $viewableParticipants = "(tawasulCalendar.viewableParticipants='Y' AND tawasulCalendarEventPerson.tawasulCalendarEventPersonID IS NOT NULL)";
        if ($roleCategory == 'Staff') {
            $query->where("(tawasulCalendar.viewableStaff='Y' OR tawasulCalendar.public='Y' OR {$viewableParticipants})");
        } elseif ($roleCategory == 'Student') {
            $query->where("(tawasulCalendar.viewableStudents='Y' OR tawasulCalendar.public='Y' OR {$viewableParticipants})");
        } elseif ($roleCategory == 'Parent') {
            $query->where("(tawasulCalendar.viewableParents='Y' OR tawasulCalendar.public='Y' OR {$viewableParticipants})");
        } elseif ($roleCategory == 'Other') {
            $query->where("(tawasulCalendar.viewableOther='Y' OR tawasulCalendar.public='Y' OR {$viewableParticipants})");
        } else {
            $query->where("(tawasulCalendar.public='Y' OR {$viewableParticipants})");
        }

        return $this->runSelect($query);
    }

    public function selectEventsByCalendar($tawasulCalendarID, $tawasulPersonID, $dateStart, $dateEnd)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'tawasulCalendarEvent.tawasulCalendarEventID',
                'tawasulCalendarEvent.tawasulCalendarID',
                'tawasulCalendarEvent.tawasulCalendarEventTypeID',
                'tawasulCalendarEvent.name',
                'tawasulCalendarEvent.status',
                'tawasulCalendarEvent.description',
                'tawasulCalendarEvent.dateStart',
                'tawasulCalendarEvent.dateEnd',
                'tawasulCalendarEvent.timeStart',
                'tawasulCalendarEvent.timeEnd',
                'tawasulCalendarEvent.allDay',
                'tawasulCalendarEvent.locationType',
                'tawasulCalendarEvent.locationDetail',
                'tawasulCalendarEvent.locationURL',
                'tawasulCalendarEvent.tawasulSpaceID',
                'tawasulCalendarEvent.tawasulPersonIDOrganiser',
                'tawasulCalendarEventType.type',
                'organiser.preferredName as organiserPreferredName', 
                'organiser.surname as organiserSurname',
                '(CASE WHEN tawasulCalendarEvent.tawasulSpaceID IS NOT NULL THEN tawasulSpace.name ELSE NULL END) AS space',
                '(CASE WHEN tawasulCalendarEventPersonID IS NOT NULL THEN tawasulCalendarEventPerson.role ELSE NULL END) as role',
                '(CASE WHEN tawasulCalendarEventPersonID IS NOT NULL THEN "Y" ELSE "N" END) as participant',
            ])
            ->leftJoin('tawasulCalendarEventType', 'tawasulCalendarEventType.tawasulCalendarEventTypeID=tawasulCalendarEvent.tawasulCalendarEventTypeID')
            ->leftJoin('tawasulCalendar', 'tawasulCalendar.tawasulCalendarID=tawasulCalendarEvent.tawasulCalendarID')
            ->leftJoin('tawasulCalendarEventPerson', 'tawasulCalendarEvent.tawasulCalendarEventID=tawasulCalendarEventPerson.tawasulCalendarEventID AND tawasulCalendarEventPerson.tawasulPersonID=:tawasulPersonID')
            ->leftJoin('tawasulPerson as organiser', "tawasulCalendarEvent.tawasulPersonIDOrganiser=organiser.tawasulPersonID AND organiser.status = 'Full'")
            ->leftJoin('tawasulSpace', 'tawasulSpace.tawasulSpaceID=tawasulCalendarEvent.tawasulSpaceID')
            ->where('tawasulCalendar.tawasulCalendarID = :tawasulCalendarID')
            ->bindValue('tawasulCalendarID', $tawasulCalendarID)
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->where("tawasulCalendarEvent.status = 'Confirmed'")
            ->where('(tawasulCalendarEvent.dateStart <= :rangeEnd AND tawasulCalendarEvent.dateEnd >= :rangeStart)')
            ->bindValue('rangeStart', $dateStart)
            ->bindValue('rangeEnd', $dateEnd);

        return $this->runSelect($query);
    }
    
    public function selectEventsByFacility($tawasulCalendarID, $tawasulSpaceID, $rangeStart = null, $rangeEnd = null)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'tawasulCalendarEvent.tawasulCalendarEventID',
                'tawasulCalendarEvent.tawasulCalendarID',
                'tawasulCalendarEvent.tawasulCalendarEventTypeID',
                'tawasulCalendarEvent.name',
                'tawasulCalendarEvent.status',
                'tawasulCalendarEvent.description',
                'tawasulCalendarEvent.dateStart',
                'tawasulCalendarEvent.dateEnd',
                'tawasulCalendarEvent.timeStart',
                'tawasulCalendarEvent.timeEnd',
                'tawasulCalendarEvent.allDay',
                'tawasulCalendarEvent.locationType',
                'tawasulCalendarEvent.locationDetail',
                'tawasulCalendarEvent.locationURL',
                'tawasulCalendarEvent.tawasulSpaceID',
                'tawasulCalendarEvent.tawasulPersonIDOrganiser',
                'tawasulCalendarEventType.type',
                'organiser.preferredName as organiserPreferredName', 
                'organiser.surname as organiserSurname',
                'CASE WHEN tawasulCalendarEvent.tawasulSpaceID IS NOT NULL THEN tawasulSpace.name ELSE NULL END AS space',
                '"N" as participant',
            ])
            ->leftJoin('tawasulCalendar', 'tawasulCalendar.tawasulCalendarID=tawasulCalendarEvent.tawasulCalendarID')
            ->leftJoin('tawasulCalendarEventType', 'tawasulCalendarEventType.tawasulCalendarEventTypeID=tawasulCalendarEvent.tawasulCalendarEventTypeID')
            ->leftJoin('tawasulPerson as organiser', "tawasulCalendarEvent.tawasulPersonIDOrganiser=organiser.tawasulPersonID AND organiser.status = 'Full'")
            ->leftJoin('tawasulSpace', 'tawasulSpace.tawasulSpaceID=tawasulCalendarEvent.tawasulSpaceID')
            ->where('tawasulCalendar.tawasulCalendarID = :tawasulCalendarID')
            ->bindValue('tawasulCalendarID', $tawasulCalendarID)
            ->where("tawasulCalendarEvent.status = 'Confirmed'")
            ->where('(tawasulCalendarEvent.dateStart <= :rangeEnd AND tawasulCalendarEvent.dateEnd >= :rangeStart)')
            ->bindValue('rangeStart', $rangeStart)
            ->bindValue('rangeEnd', $rangeEnd)
            ->where('tawasulSpace.tawasulSpaceID=:tawasulSpaceID')
            ->bindValue('tawasulSpaceID', $tawasulSpaceID);

        return $this->runSelect($query);
    }

    public function getEventDetailsByID($tawasulCalendarEventID, $tawasulPersonID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'tawasulCalendar.name as calendarName',
                'tawasulCalendarEvent.tawasulCalendarEventID',
                'tawasulCalendarEvent.tawasulCalendarID',
                'tawasulCalendarEvent.tawasulCalendarEventTypeID',
                'tawasulCalendarEvent.name',
                'tawasulCalendarEvent.status',
                'tawasulCalendarEvent.description',
                'tawasulCalendarEvent.dateStart',
                'tawasulCalendarEvent.dateEnd',
                'tawasulCalendarEvent.timeStart',
                'tawasulCalendarEvent.timeEnd',
                'tawasulCalendarEvent.allDay',
                'tawasulCalendarEvent.locationType',
                'tawasulCalendarEvent.locationDetail',
                'tawasulCalendarEvent.locationURL',
                'tawasulCalendarEvent.tawasulSpaceID',
                'tawasulCalendarEvent.tawasulPersonIDCreated',
                'tawasulCalendarEvent.tawasulPersonIDOrganiser',
                'tawasulCalendarEventType.type as eventType',
                'organiser.preferredName as organiserPreferredName', 
                'organiser.surname as organiserSurname',
                'tawasulSpace.name AS space',
                '(CASE WHEN editor.editAllEvents="Y" OR (tawasulCalendarEvent.tawasulPersonIDOrganiser=:tawasulPersonID OR tawasulCalendarEvent.tawasulPersonIDCreated=:tawasulPersonID) THEN "Y" ELSE "N" END) as editor',
                '(CASE WHEN participant.tawasulCalendarEventPersonID IS NOT NULL THEN "Y" ELSE "N" END) as participant',
            ])
            ->leftJoin('tawasulCalendar', 'tawasulCalendar.tawasulCalendarID=tawasulCalendarEvent.tawasulCalendarID')
            ->leftJoin('tawasulCalendarEventType', 'tawasulCalendarEventType.tawasulCalendarEventTypeID=tawasulCalendarEvent.tawasulCalendarEventTypeID')
            ->leftJoin('tawasulCalendarEditor as editor', 'editor.tawasulCalendarID=tawasulCalendarEvent.tawasulCalendarID AND editor.tawasulPersonID=:tawasulPersonID')
            ->leftJoin('tawasulCalendarEventPerson as participant', 'participant.tawasulCalendarEventID=tawasulCalendarEvent.tawasulCalendarEventID AND participant.tawasulPersonID=:tawasulPersonID')
            ->leftJoin('tawasulPerson as organiser', "tawasulCalendarEvent.tawasulPersonIDOrganiser=organiser.tawasulPersonID AND organiser.status = 'Full'")
            ->leftJoin('tawasulSpace', 'tawasulSpace.tawasulSpaceID=tawasulCalendarEvent.tawasulSpaceID')
            ->where('tawasulCalendarEvent.tawasulCalendarEventID = :tawasulCalendarEventID')
            ->bindValue('tawasulCalendarEventID', $tawasulCalendarEventID)
            ->bindValue('tawasulPersonID', $tawasulPersonID)
            ->groupBy(['tawasulCalendarEvent.tawasulCalendarEventID']);

        return $this->runSelect($query)->fetch();
    }
}
