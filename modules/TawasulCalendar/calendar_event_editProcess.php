<?php
/*
TawasulOS, Flexible & Open School System
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

use TawasulOS\Data\Validator;
use TawasulOS\Domain\Attendance\AttendanceLogPersonGateway;
use TawasulOS\Domain\Calendar\CalendarEventGateway;
use TawasulOS\Domain\Calendar\CalendarEventPersonGateway;
use TawasulOS\Support\Facades\Access;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['description' => 'HTML']);

$tawasulCalendarEventID = $_POST['tawasulCalendarEventID'] ?? '';

$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulCalendar/calendar_event_edit.php&tawasulCalendarEventID=$tawasulCalendarEventID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulCalendar/calendar_event_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $calendarEventGateway = $container->get(CalendarEventGateway::class);
    $calendarEventPersonGateway = $container->get(CalendarEventPersonGateway::class);
    $attendanceLogPersonGateway = $container->get(AttendanceLogPersonGateway::class);

    // Get event details
    $event = $calendarEventGateway->getEventDetailsByID($tawasulCalendarEventID, $session->get('tawasulPersonID'));
    if (empty($event)) {
        header("Location: {$URL}&return=error2");
        exit;
    } 

    // Check for access to edit this event
    if ($event['editor'] != 'Y' && !Access::allows('Calendar', 'calendar_event_edit', 'Manage Events_all')) {
        header("Location: {$URL}&return=error0");
        exit;
    } 

    if (empty($_POST['dateStart']) || empty($_POST['dateEnd'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    }

    $dateStart = new DateTime(trim($_POST['dateStart'], '"'));
    $dateEnd = new DateTime(trim($_POST['dateEnd'], '"'));

    if (empty($dateStart) || empty($dateEnd)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    }
    
    $data = [
        'tawasulCalendarID'          => $_POST['tawasulCalendarID'] ?? '',
        'tawasulCalendarEventTypeID' => $_POST['tawasulCalendarEventTypeID'] ?? '',
        'name'                      => $_POST['name'] ?? '',
        'description'               => $_POST['description'] ?? '',
        'status'                    => $_POST['status'] ?? 'Tentative',
        'dateStart'                 => $dateStart->format('Y-m-d'),
        'dateEnd'                   => $dateEnd->format('Y-m-d'),
        'allDay'                    => !empty($_POST['allDay']) ? $_POST['allDay'] : 'N',
        'timeStart'                 => $_POST['timeStart'] ?? null,
        'timeEnd'                   => $_POST['timeEnd'] ?? null,
        'locationType'              => $_POST['locationType'] ?? 'External',
        'locationDetail'            => $_POST['locationDetail'] ?? '',
        'locationURL'               => $_POST['locationURL'] ?? '',
        'tawasulSpaceID'             => !empty($_POST['tawasulSpaceID']) ? $_POST['tawasulSpaceID'] : null,
        'timestampModified'         => date('Y-m-d H:i:s'),
        'tawasulPersonIDModified'    => $session->get('tawasulPersonID') ?? '',
    ];
    
    // Validate the required values are present
    if (empty($data['name']) || empty($data['tawasulCalendarID']) || empty($data['tawasulCalendarEventTypeID']) || empty($data['locationType']) || empty($data['dateStart']) || empty($data['dateEnd'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    }

    // Check if the date or time has changed compared to the existing event
    $dateTimeChanged = $data['dateStart'] !== $event['dateStart'] || $data['dateEnd'] !== $event['dateEnd'] || $data['timeStart'] !== $event['timeStart'] || $data['timeEnd'] !== $event['timeEnd'];

    // Update the record
    if (!$calendarEventGateway->update($tawasulCalendarEventID, $data)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
    }

    $organiser = $calendarEventPersonGateway->selectBy(['tawasulCalendarEventID' => $tawasulCalendarEventID, 'role' => 'Organiser', 'tawasulPersonID' => $tawasulPersonIDOrganiser])->fetch();

     if (empty($organiser)) {
         $organiserData = [
            'tawasulCalendarEventID'     => $tawasulCalendarEventID,
            'tawasulPersonID'            => $tawasulPersonIDOrganiser,
            'role'                      => 'Organiser',
            'tawasulPersonIDModified'    => $session->get('tawasulPersonID') ?? '',
            'timestampModified'         => date('Y-m-d H:i:s'),
            'timestampCreated'          => date('Y-m-d H:i:s'),
            'tawasulPersonIDCreated'     => $session->get('tawasulPersonID') ?? '',
        ];

        $inserted = $calendarEventPersonGateway->insertAndUpdate($organiserData, $organiserData);
     }

    $staff = $_POST['staff'] ?? [];
    $role = $_POST['role'] ?? 'Other';

    if (!is_array($staff)) {
        $staff = [strval($staff)];
    }

    foreach ($staff as $staffPersonID) {
        $personData = [
            'tawasulCalendarEventID' => $tawasulCalendarEventID,
            'tawasulPersonID'   => $staffPersonID,
            'role'    => $role,
            'tawasulPersonIDModified' => $session->get('tawasulPersonID') ?? '',
            'timestampModified' => date('Y-m-d H:i:s'),
            'timestampCreated'        => date('Y-m-d H:i:s'),
            'tawasulPersonIDCreated'   => $session->get('tawasulPersonID') ?? '',
        ];

        $inserted = $calendarEventPersonGateway->insertAndUpdate($personData, $personData);
        $partialFail &= !$inserted;
    }

    // If event date or time changed, remove future absences for all student participants using the OLD dates
    $futureAbsencesRemoved = false;
    if ($dateTimeChanged) {
        $futureAbsencesRemoved = $attendanceLogPersonGateway->deleteWhere(['foreignTable' => 'tawasulCalendarEvent', 'foreignTableID' => $tawasulCalendarEventID]);
    }

    if ($futureAbsencesRemoved) {
        $URL .= "&return=warning9&editID=$tawasulCalendarEventID";
    } elseif ($partialFail) {
        $URL .= "&return=warning1";
    } else {
        $URL .= "&return=success0&editID=$tawasulCalendarEventID";
    }

    header("Location: {$URL}");
}
