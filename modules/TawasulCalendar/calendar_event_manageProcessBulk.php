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

use TawasulOS\Domain\Calendar\CalendarEventGateway;
use TawasulOS\Domain\Calendar\CalendarEventPersonGateway;
use TawasulOS\Support\Facades\Access;

require_once __DIR__ . '/../../tawasul.php';

$action = $_POST['action'] ?? '';


$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulCalendar/calendar_event_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulCalendar/calendar_event_manage.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $events = $_POST['tawasulCalendarEventID'] ?? [];
    $calendarEventGateway = $container->get(CalendarEventGateway::class);

    // Proceed!
    if (count($events) < 1) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
    } else {
        $partialFail = false;

        $canManageAllEvents = Access::allows('Calendar', 'calendar_event_edit', 'Manage Events_all');

        if ($action == 'Duplicate' || $action == 'DuplicateParticipants') {
            foreach ($events AS $tawasulCalendarEventID) { // For every event to be duplicated
                // Check existence of event and fetch details
                $calendarEvent = $calendarEventGateway->getByID($tawasulCalendarEventID);
                if (empty($calendarEvent)) {
                    $partialFail = true;
                    continue;
                }

                // Check for access to edit this event
                $event = $calendarEventGateway->getEventDetailsByID($tawasulCalendarEventID, $session->get('tawasulPersonID'));
                if ($event['editor'] != 'Y' && !$canManageAllEvents) {
                    $partialFail = true;
                    continue;
                }

                $name = $calendarEvent['name'];
                $name .= ' (Copy)';

                // Write the duplicate to the database
                $data = ['tawasulCalendarID' => $calendarEvent['tawasulCalendarID'], 'tawasulCalendarEventTypeID' => $calendarEvent['tawasulCalendarEventTypeID'], 'name' => $name, 'description' => $calendarEvent['description'], 'status' => $calendarEvent['status'], 'allDay' => $calendarEvent['allDay'], 'dateStart' => $calendarEvent['dateStart'], 'dateEnd' => $calendarEvent['dateEnd'], 'timeStart' => $calendarEvent['timeStart'], 'timeEnd' => $calendarEvent['timeEnd'], 'locationType' => $calendarEvent['locationType'], 'locationDetail' => $calendarEvent['locationDetail'], 'locationURL' => $calendarEvent['locationURL'], 'tawasulSpaceID' => $calendarEvent['tawasulSpaceID'], 'foreignTable' => $calendarEvent['foreignTable'], 'foreignTableID' => $calendarEvent['foreignTableID'], 'timestampCreated' => date('Y-m-d H:i:s'), 'timestampModified' => date('Y-m-d H:i:s'), 'tawasulPersonIDCreated' => $session->get('tawasulPersonID'), 'tawasulPersonIDModified' => $session->get('tawasulPersonID'), 'tawasulPersonIDOrganiser' => $calendarEvent['tawasulPersonIDOrganiser']];

                $inserted = $calendarEventGateway->insert($data);
                // $tawasulCalendarEventID = str_pad($connection2->lastInsertID(), 8, '0', STR_PAD_LEFT);

                if ($action == 'DuplicateParticipants') {
                    $calendarEventPersonGateway = $container->get(CalendarEventPersonGateway::class);
                    $participants = $calendarEventPersonGateway->selectBy(['tawasulCalendarEventID' => $tawasulCalendarEventID]);
                    
                    while ($participant = $participants->fetch()) {
                        $data = ['tawasulCalendarEventID' => $inserted, 'tawasulPersonID' => $participant['tawasulPersonID'], 'role' => $participant['role'], 'timestampCreated' => date('Y-m-d H:i:s'), 'timestampModified' => date('Y-m-d H:i:s'), 'tawasulPersonIDCreated' => $session->get('tawasulPersonID'), 'tawasulPersonIDModified' => $session->get('tawasulPersonID')];
                        $insertedParticipant = $calendarEventPersonGateway->insert($data);
                    }
                }
            }
        }
    
        if ($partialFail == true) {
            $URL .= '&return=warning1';
            header("Location: {$URL}");
        } else {
            $URL .= '&return=success0';
            header("Location: {$URL}");
        }
    }
}
