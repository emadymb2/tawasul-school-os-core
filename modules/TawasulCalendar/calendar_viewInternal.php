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
use TawasulOS\UI\Timetable\Palette;
use TawasulOS\Services\Format;
use TawasulOS\UI\Timetable\Layers\SchoolCalendarLayer;

require_once __DIR__ . '/../../tawasul.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulCalendar/calendar_view.php') == false) {
    // Access denied
    die(__('You do not have access to this action.'));
} else {
    // Proceed!

    $dateStart = $_GET['start'] ?? '';
    $dateEnd = $_GET['end'] ?? '';

    if (empty($dateStart) || empty($dateEnd)) return '[]';

    // From FullCalendar ISO dates
    $dateStart = date('Y-m-d', strtotime($dateStart));
    $dateEnd = date('Y-m-d', strtotime($dateEnd)-86400);

    $tawasulPersonID = $session->get('tawasulPersonID');
    $roleCategory = $session->get('tawasulRoleIDCurrentCategory');

    $palette = $container->get(Palette::class);
    $eventGateway = $container->get(CalendarEventGateway::class);

    $events = $eventGateway->selectVisibleEventsByPerson($tawasulPersonID, $roleCategory, $dateStart, $dateEnd)->fetchAll();

    foreach ($events as $index => $event) {
        $color = $event['color'] ?? '#6a6bef';
        $contrast = $palette->getHexContrastColor($color);

        $events[$index]['description'] = substr(strip_tags($event['description']), 0, 140);
        $events[$index]['timeRange'] = $event['allDay'] == 'Y' ? __('All Day') : Format::timeRange($event['timeStart'], $event['timeEnd']);
        $events[$index]['type'] = $event['type'] ?? __('Event');
        $events[$index]['allDay'] = $event['allDay'] == 'Y';
        $events[$index]['palette'] = $palette->getPalette($color);
        $events[$index]['backgroundColor'] = $color;
        $events[$index]['borderColor'] = $palette->adjustHexColor($event['color'], -0.1);
        $events[$index]['textColor'] = $contrast == 'white'
            ? $palette->adjustHexColor($event['color'], 0.7)
            : $palette->adjustHexColor($event['color'], -0.7);
    }

    echo json_encode($events);
}

