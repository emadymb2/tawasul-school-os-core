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

$tawasulCalendarEventTypeID = $_POST['tawasulCalendarEventTypeID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulCalendar/calendar_eventTypes_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulCalendar/calendar_eventTypes_manage.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} elseif (empty($tawasulCalendarEventTypeID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
  } else {
    // Proceed!
    $calendarEventTypeGateway = $container->get(CalendarEventTypeGateway::class);

    // Validate the database relationships exist
    $values = $calendarEventTypeGateway->getByID($tawasulCalendarEventTypeID);

    if (empty($values)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $deleted = $calendarEventTypeGateway->delete($tawasulCalendarEventTypeID);

    $URL .= !$deleted
        ? '&return=error2'
        : '&return=success0';

    header("Location: {$URL}");
}
