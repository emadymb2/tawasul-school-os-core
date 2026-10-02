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

namespace TawasulOS\UI\Timetable\Layers;

use TawasulOS\Http\Url;
use TawasulOS\Support\Facades\Access;
use TawasulOS\UI\Timetable\TimetableContext;
use TawasulOS\Domain\Calendar\CalendarEventGateway;
use TawasulOS\Contracts\Services\Session;

/**
 * Timetable UI: CalendarEventsLayer
 *
 * @version  v30
 * @since    v30
 */
class CalendarEventsLayer extends AbstractTimetableLayer
{
    protected $session;
    protected $calendarEventGateway;
    protected $calendar;

    public function __construct(Session $session, CalendarEventGateway $calendarEventGateway)
    {
        $this->session = $session;
        $this->calendarEventGateway = $calendarEventGateway;

        $this->name = 'Events';
        $this->color = 'green';
        $this->type = 'calendar';
        $this->order = 10;
    }

    public function setCalendar(array $calendar)
    {
        $this->calendar = $calendar;
        $this->name = $calendar['name'];
        $this->color = $calendar['color'];
        $this->type = 'calendar';
        $this->order = 10 + $calendar['sequenceNumber'];

        return $this;
    }

    public function checkCalendarAccess($checkParticipants = true)
    {
        $roleCategory = $this->session->get('tawasulRoleIDCurrentCategory');

        if ($this->calendar['public'] == 'Y') return true;
        if ($checkParticipants && $this->calendar['viewableParticipants'] == 'Y') return true;
        if ($roleCategory == 'Staff' && $this->calendar['viewableStaff'] == 'Y') return true;
        if ($roleCategory == 'Student' && $this->calendar['viewableStudents'] == 'Y') return true;
        if ($roleCategory == 'Parent' && $this->calendar['viewableParents'] == 'Y') return true;
        if ($roleCategory == 'Other' && $this->calendar['viewableOther'] == 'Y') return true;
        
        return false;
    }

    public function checkAccess(TimetableContext $context) : bool
    {
        return ($context->has('tawasulSpaceID') || $context->has('tawasulPersonID')) && (Access::allows('Calendar', 'calendar_event_manage') || Access::allows('Calendar', 'calendar_view'));
    }
    
    public function loadItems(\DatePeriod $dateRange, TimetableContext $context)
    {
        if (!$context->has('tawasulSchoolYearID')) return;

        if ($context->has('tawasulPersonID')) {
            $eventList = $this->calendarEventGateway->selectEventsByCalendar($this->calendar['tawasulCalendarID'], $context->get('tawasulPersonID'), $dateRange->getStartDate()->format('Y-m-d'), $dateRange->getEndDate()->format('Y-m-d'))->fetchAll();
        } elseif ($context->has('tawasulSpaceID')) {
            $eventList = $this->calendarEventGateway->selectEventsByFacility($this->calendar['tawasulCalendarID'], $context->get('tawasulSpaceID'), $dateRange->getStartDate()->format('Y-m-d'), $dateRange->getEndDate()->format('Y-m-d'))->fetchAll();
        }

        $canViewEvents = Access::allows('Calendar', 'calendar_event_view');
        $canAccessCalendar = $this->checkCalendarAccess(false);
        $viewingSelf = ($context->has('tawasulPersonID') && $context->get('tawasulPersonID') == $this->session->get('tawasulPersonID')) || ($context->has('tawasulSpaceID'));

        foreach ($dateRange as $dateObject) {
            $date = $dateObject->format('Y-m-d');
            foreach ($eventList as $event) {
                // Skip dates outside this event range
                if ($date < $event['dateStart'] || $date > $event['dateEnd']) continue;

                // Allow view access if the current user and target user are in the same event
                if (!$canAccessCalendar && !$viewingSelf && $this->calendar['viewableParticipants'] == 'Y' && $event['participant'] == 'Y') {
                    $viewerEvent = $this->calendarEventGateway->getEventDetailsByID($event['tawasulCalendarEventID'], $this->session->get('tawasulPersonID'));
                    if ($viewerEvent['participant'] == 'Y' && $event['participant'] == 'Y') {
                        $canAccessCalendar = true;
                    } else {
                        continue;
                    }
                }

                // Can only view event with calendar access, or as a participant with participant access
                if (!$canAccessCalendar && !($this->calendar['viewableParticipants'] == 'Y' && $event['participant'] == 'Y' && $viewingSelf)) continue;

                // Hide non-participant events when viewing calendars for other users
                if ($canAccessCalendar && !$viewingSelf && $event['participant'] != 'Y') continue;

                $location = !empty($event['locationType']) ? (!empty($event['space']) ? $event['space'] : $event['locationDetail'] ?? '') : '';

                $item = $this->createItem($date, $event['allDay'] == 'Y')->loadData([
                    'id'          => $event['tawasulCalendarEventID'],
                    'type'        => $event['type'] ?? __('Event'),
                    'title'       => $event['name'],
                    'location' => $location,
                    'subtitle'    => $location ?: $event['role'],
                    'description' => $this->calendar['name'],
                    'link'        => $canViewEvents ? Url::fromModuleRoute('TawasulCalendar', 'calendar_event_view')->withQueryParam('tawasulCalendarEventID', $event['tawasulCalendarEventID']) : '',
                    'allDay'      => $event['allDay'] == 'Y' ?? false,
                    'timeStart'   => $event['timeStart'] ?? null,
                    'timeEnd'     => $event['timeEnd'] ?? null,
                ]);

                if (!empty($event['role'])) {
                    $item->addStatus('myEvent');
                    $item->set('secondaryAction', [
                        'name'      => 'cover',
                        'label'     => $event['role'],
                        'url'       => $canViewEvents ? Url::fromModuleRoute('TawasulCalendar', 'calendar_event_view')->withQueryParam('tawasulCalendarEventID', $event['tawasulCalendarEventID']) : '',
                        'icon'      => 'user',
                        'iconClass' => 'text-gray-600 hover:text-gray-800',
                    ]);
                }
            }
        }
    }
}
