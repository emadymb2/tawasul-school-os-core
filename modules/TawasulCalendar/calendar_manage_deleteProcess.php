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
use TawasulOS\Domain\Calendar\CalendarGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulCalendarID = $_POST['tawasulCalendarID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulCalendar/calendar_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulCalendar/calendar_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} elseif (empty($tawasulCalendarID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
  } else {
    // Proceed!
    $calendarGateway = $container->get(CalendarGateway::class);

    // Validate the database relationships exist
    if (!$calendarGateway->exists($tawasulCalendarID)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $deleted = $calendarGateway->delete($tawasulCalendarID);

    $URL .= !$deleted
        ? '&return=error2'
        : '&return=success0';

    header("Location: {$URL}");
}