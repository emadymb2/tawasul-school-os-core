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

use TawasulOS\Domain\Calendar\CalendarEventGateway;
use TawasulOS\Forms\Prefab\DeleteForm;

if (isActionAccessible($guid, $connection2, '/modules/TawasulCalendar/calendar_event_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
   $tawasulCalendarEventID = $_GET['tawasulCalendarEventID'] ?? '';

    if (empty($tawasulCalendarEventID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }
    
    $calendarEventGateway = $container->get(CalendarEventGateway::class);

    $event = $calendarEventGateway->getByID($tawasulCalendarEventID);

    if (empty($event)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/TawasulCalendar/calendar_event_deleteProcess.php', true, false);
    $form->addRow()->addConfirmSubmit();

    echo $form->getOutput();
}