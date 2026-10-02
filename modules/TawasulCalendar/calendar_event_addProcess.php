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
use TawasulOS\Support\Facades\Access;
use TawasulOS\Domain\Calendar\CalendarEventGateway;
use TawasulOS\Domain\Calendar\CalendarEventPersonGateway;
use TawasulOS\Domain\Calendar\CalendarGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['description' => 'HTML']);

$source = $_POST['source'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulCalendar/calendar_event_add.php';
$URLSuccess = $source == 'ajax'
    ? $session->get('absoluteURL').'/index.php?q=/modules/TawasulCalendar/calendar_view.php'
    : $session->get('absoluteURL').'/index.php?q=/modules/TawasulCalendar/calendar_event_add.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulCalendar/calendar_event_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $calendarGateway = $container->get(CalendarGateway::class);
    $calendarEventGateway = $container->get(CalendarEventGateway::class);
    $calendarEventPersonGateway = $container->get(CalendarEventPersonGateway::class);

    $tawasulPersonIDOrganiser = $_POST['tawasulPersonIDOrganiser'] ?? '';

    $data = [
        'tawasulCalendarID'        => $_POST['tawasulCalendarID'] ?? '',
        'tawasulCalendarEventTypeID' => $_POST['tawasulCalendarEventTypeID'] ?? '',
        'name'                    => $_POST['name'] ?? '',
        'description'             => $_POST['description'] ?? '',
        'status'                  => $_POST['status'] ?? 'Tentative',
        'dateStart'               => $_POST['dateStart'] ?? '',
        'dateEnd'                 => $_POST['dateEnd'] ?? $_POST['dateStart'] ?? '',
        'allDay'                  => !empty($_POST['allDay']) ? $_POST['allDay'] : 'N',
        'timeStart'               => $_POST['timeStart'] ?? null,
        'timeEnd'                 => $_POST['timeEnd'] ?? null,   
        'locationType'            => $_POST['locationType'] ?? 'External',
        'locationDetail'          => $_POST['locationDetail'] ?? '',
        'locationURL'             => $_POST['locationURL'] ?? '',
        'tawasulSpaceID'           => $_POST['tawasulSpaceID'] ?? null,
        'tawasulPersonIDOrganiser' => $tawasulPersonIDOrganiser,
        'timestampCreated'        => date('Y-m-d H:i:s'),
        'tawasulPersonIDCreated'   => $session->get('tawasulPersonID') ?? '',
        'timestampModified'       => date('Y-m-d H:i:s'),
        'tawasulPersonIDModified'  => $session->get('tawasulPersonID') ?? '',
    ];
    
    // Validate the required values are present
    if (empty($data['name']) || empty($data['dateStart']) || empty($data['dateEnd'])) {
        header("Location: {$URL}&return=error1");
        exit;
    }

    // Get Calendars of the current school year
    $tawasulPersonIDEditor = Access::allows('Calendar', 'calendar_event_edit', 'Manage Events_all') ? null : $session->get('tawasulPersonID');
    $calendars = $calendarGateway->selectEditableCalendarsByPerson($session->get('tawasulSchoolYearID'), $tawasulPersonIDEditor)->fetchKeyPair();

    if (empty($calendars) || empty($calendars[$data['tawasulCalendarID']])) {
        header("Location: {$URL}&return=error0");
        exit;
    }

    // Create the record
    $tawasulCalendarEventID = $calendarEventGateway->insert($data);

    if (!$tawasulCalendarEventID) {
        header("Location: {$URL}&return=error2");
        exit;
    }

    // Scan through staff
    $staff = $_POST['staff'] ?? [];
    $role = $_POST['role'] ?? 'Other';

    if (!is_array($staff)) {
        $staff = [strval($staff)];
    }

    foreach ($staff as $staffPersonID) {
        $personData = [
            'tawasulCalendarEventID'  => $tawasulCalendarEventID,
            'tawasulPersonID'         => $staffPersonID,
            'role'                   => $role,
            'tawasulPersonIDCreated'  => $session->get('tawasulPersonID') ?? '',
            'timestampCreated'       => date('Y-m-d H:i:s'),
            'tawasulPersonIDModified' => $session->get('tawasulPersonID') ?? '',
            'timestampModified'      => date('Y-m-d H:i:s'),
        ];

        $tawasulCalendarEventPersonID = $calendarEventPersonGateway->insert($personData);
        $partialFail &= !$tawasulCalendarEventPersonID;
    }

    // Add the organiser to the particapants list
    $organiserData = [
            'tawasulCalendarEventID'  => $tawasulCalendarEventID,
            'tawasulPersonID'         => $tawasulPersonIDOrganiser,
            'role'                   => 'Organiser',
            'tawasulPersonIDCreated'  => $session->get('tawasulPersonID') ?? '',
            'timestampCreated'       => date('Y-m-d H:i:s'),
            'tawasulPersonIDModified' => $session->get('tawasulPersonID') ?? '',
            'timestampModified'      => date('Y-m-d H:i:s'),
        ];

    $tawasulCalendarEventPersonID = $calendarEventPersonGateway->insert($organiserData);
    $partialFail &= !$tawasulCalendarEventPersonID;

    $URLSuccess .= $partialFail
        ? "&return=warning1"
        : "&return=success0&editID=$tawasulCalendarEventID";
    header("Location: {$URLSuccess}");
}
