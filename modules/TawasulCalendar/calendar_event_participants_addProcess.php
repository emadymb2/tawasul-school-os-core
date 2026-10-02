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

use TawasulOS\Data\Validator;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Domain\Messenger\GroupGateway;
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Calendar\CalendarEventGateway;
use TawasulOS\Domain\Calendar\CalendarEventPersonGateway;
use TawasulOS\Domain\Timetable\CourseClassGateway;
use TawasulOS\Support\Facades\Access;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulCalendarEventID = $_GET['tawasulCalendarEventID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulCalendar/calendar_event_participants.php&tawasulCalendarEventID='.$tawasulCalendarEventID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulCalendar/calendar_event_participants.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    // Proceed
    $target = $_POST['target'] ?? '';
    $tawasulActivityID = $_POST['tawasulActivityID'] ?? '';
    $tawasulGroupID = $_POST['tawasulGroupID'] ?? '';
    $tawasulCourseClassID = $_POST['tawasulCourseClassID'] ?? '';
    $tawasulPersonIDList = $_POST['participants'] ?? [];
    $foreignTable = '';
    
    $calendarEventGateway = $container->get(CalendarEventGateway::class);
    $calendarEventPersonGateway = $container->get(CalendarEventPersonGateway::class);
    
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

    // Check if required values are specified
    if (empty($target)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    switch ($target) {
        case 'Activity':
            if ($container->get(ActivityGateway::class)->exists($tawasulActivityID)) {
                $foreignTable = 'tawasulActivity';
                $targetID = $tawasulActivityID;
            }
            break;
        case 'Messenger':
            if ($container->get(GroupGateway::class)->getByID($tawasulGroupID)) {
                $targetID = $tawasulGroupID;
                $foreignTable = 'tawasulGroup';
            };
            break;
        case 'Class':
            if ($container->get(CourseClassGateway::class)->getByID($tawasulCourseClassID)) {
                $targetID = $tawasulCourseClassID;
                $foreignTable = 'tawasulCourseClass';
            };
            break;
        case 'Individual':
            $targetID = $tawasulPersonIDList;
            break;
    }

    if (empty($targetID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Get all the participants from the selected target
    $participants = $calendarEventPersonGateway->selectTargetParticipants($session->get('tawasulSchoolYearID'), $target, $targetID)->fetchAll();
    
    $tawasulPersonIDs = array_column($participants, 'tawasulPersonID');
    
    if (empty($tawasulPersonIDs)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $partialFail = false;

    foreach ($tawasulPersonIDs as $tawasulPersonID) {
        $data = [
            'tawasulCalendarEventID' => $tawasulCalendarEventID,
            'tawasulPersonID'        => $tawasulPersonID,
            'role'                  => 'Attendee',
            'timestampCreated'      => date('Y-m-d H:i:s'),
            'timestampModified'     => date('Y-m-d H:i:s'),
            'tawasulPersonIDCreated'   => $session->get('tawasulPersonID'),
            'tawasulPersonIDModified'   => $session->get('tawasulPersonID'),            
        ];

        $inserted = $calendarEventPersonGateway->insertAndUpdate($data, $data);
        $partialFail &= !$inserted;
    }

    if (!($target == 'Individual')) {
        $calendarEventGateway = $container->get(CalendarEventGateway::class);
        $data = ['foreignTable' => $foreignTable, 'foreignTableID' => $targetID];
        $partialFail &= !$calendarEventGateway->update($tawasulCalendarEventID, $data);
    }

    $URL .= $partialFail
        ? '&return=warning1'
        : '&return=success0';

    header("Location: {$URL}");
}
