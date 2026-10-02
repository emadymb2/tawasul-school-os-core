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
use TawasulOS\Domain\Calendar\CalendarEventTypeGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulCalendarEventTypeID = $_REQUEST['tawasulCalendarEventTypeID'] ?? null;

$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulCalendar/calendar_eventTypes_manage_addEdit.php&tawasulCalendarEventTypeID=$tawasulCalendarEventTypeID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulCalendar/calendar_eventTypes_manage_addEdit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $calendarEventTypeGateway = $container->get(CalendarEventTypeGateway::class);

    // Update the type
    $data = [
        'type'               => $_POST['type'] ?? '',
        'color'              => '',
    ];

    // Validate the required values are present
    if (empty($data['type'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

     // Validate that this record is unique
    if (!$calendarEventTypeGateway->unique($data, ['type'], $tawasulCalendarEventTypeID)) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

     // Create the record
    if (!empty($tawasulCalendarEventTypeID)) {
        $calendarEventTypeGateway->update($tawasulCalendarEventTypeID, $data);
    } else {
        $tawasulCalendarEventTypeID = $calendarEventTypeGateway->insert($data);
    }

    if (empty($tawasulCalendarEventTypeID)) {
        $URL .= "&return=error2";
        header("Location: {$URL}");
        exit;
    }

     $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0&editID=$tawasulCalendarEventTypeID";

    header("Location: {$URL}");
}
