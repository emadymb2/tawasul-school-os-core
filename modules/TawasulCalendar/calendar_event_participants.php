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

use TawasulOS\Http\Url;
use TawasulOS\Services\Format;
use TawasulOS\Tables\DataTable;
use TawasulOS\Support\Facades\Access;
use TawasulOS\Forms\Prefab\BulkActionForm;
use TawasulOS\Domain\Calendar\CalendarGateway;
use TawasulOS\Domain\Calendar\CalendarEventGateway;
use TawasulOS\Domain\Calendar\CalendarEventTypeGateway;
use TawasulOS\Domain\Calendar\CalendarEventPersonGateway;
use TawasulOS\Domain\Attendance\AttendanceLogPersonGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulCalendar/calendar_event_participants.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $tawasulCalendarEventID = $_GET['tawasulCalendarEventID'] ?? '';

    $calendarGateway = $container->get(CalendarGateway::class);
    $calendarEventGateway = $container->get(CalendarEventGateway::class);
    $calendarEventPersonGateway = $container->get(CalendarEventPersonGateway::class);
    $calendarEventTypeGateway = $container->get(CalendarEventTypeGateway::class);
    $attendanceLogGateway = $container->get(AttendanceLogPersonGateway::class);

    $page->breadcrumbs
        ->add(__('Manage Event'), 'calendar_event_manage.php')
        ->add(__('Edit Participants'));

    // Check required parameters
    if (empty($tawasulCalendarEventID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Get event details
    $event = $calendarEventGateway->getEventDetailsByID($tawasulCalendarEventID, $session->get('tawasulPersonID'));
    if (empty($event)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Check for access to edit this event
    $canEditEvent = $event['editor'] == 'Y' && Access::allows('Calendar', 'calendar_event_edit');
    if (!$canEditEvent && !Access::allows('Calendar', 'calendar_event_edit', 'Manage Events_all')) {
        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        return;
    }
    
    // FORM
    $table = DataTable::createDetails('viewEvent');

    $table->addHeaderAction('view', __('View Event'))
        ->setURL('/modules/TawasulCalendar/calendar_event_view.php')
        ->addParam('tawasulCalendarEventID', $tawasulCalendarEventID)
        ->displayLabel();

    if (Access::allows('Calendar', 'calendar_event_edit') && $canEditEvent) {
        $table->addHeaderAction('edit', __('Edit Event'))
            ->setURL('/modules/TawasulCalendar/calendar_event_edit.php')
            ->addParam('tawasulCalendarEventID', $tawasulCalendarEventID)
            ->displayLabel();
    }

    if (Access::allows('Calendar', 'calendar_event_edit') && $canEditEvent) {
        $table->addHeaderAction('notify', __('Notify Staff'))
            ->setURL('/modules/TawasulCalendar/calendar_event_notify.php')
            ->addParam('tawasulCalendarEventID', $tawasulCalendarEventID)
            ->setIcon('notify')
            ->displayLabel();
    }
    $table->addColumn('name', __('Event Name'))->addClass('col-span-2');

    $table->addColumn('status', __('Event Status'));

    $table->addColumn('dateStart', __('Date'))->format(Format::using('dateRange', ['dateStart', 'dateEnd']));

    $table->addColumn('allDay', __('When'))
        ->format(function($values) {
            if ($values['allDay'] == 'N') return Format::timeRange($values['timeStart'], $values['timeEnd']);
            return __('All Day');
        });

    if (!empty($event['locationType'])) {
        $table->addColumn('location', __('Location'))->format(function($values)  {
            if ($values['locationType'] == 'Internal') {
                return $values['space']; 
            }

            return !empty($values['locationURL'])
                ? Format::link($values['locationURL'], $values['locationDetail'])
                : $values['locationDetail'];
        });
    }

    echo $table->render([$event]);

    // QUERY
    $criteria = $calendarEventPersonGateway->newQueryCriteria()
        ->sortBy(['roleCategory', 'surname', 'preferredName'])
        ->fromPOST();

    $participants = $calendarEventPersonGateway->queryEventAttendees($criteria, $tawasulCalendarEventID);

    // Query all attendance logs for future absence records on the event date and time
    $futureAbsences = $event['allDay'] == 'Y'
        ? $attendanceLogGateway->selectFutureAttendanceLogsByDate($event['dateStart'], $event['dateEnd'])->fetchGroupedUnique()
        : $attendanceLogGateway->selectFutureAttendanceLogsByDateAndTime($event['dateStart'], $event['dateEnd'], $event['timeStart'], $event['timeEnd'])->fetchGroupedUnique();

    $futureAbsenceStudents = array_reduce($participants->toArray(), function ($group, $item) {
        if ($item['roleCategory'] == 'Student') $group[] = $item['tawasulPersonID'];
        return $group;
    }, []);

    // Find conflicts with any other events
    $conflicts = $calendarEventPersonGateway->selectEventParticipantConflicts($tawasulCalendarEventID)->fetchGroupedUnique();

    // BULK ACTION FORM
    $form = BulkActionForm::create('bulkAction', $session->get('absoluteURL').'/modules/TawasulCalendar/calendar_event_participantsProcessBulk.php');
    $form->addHiddenValue('tawasulCalendarEventID', $tawasulCalendarEventID);

    $col = $form->createBulkActionColumn([
        'Delete' => __('Delete'),
    ]);
    $col->addSubmit(__('Go'));

    // DATA TABLE FOR PARTICIPANTS
    $table = $form->addRow()->addDataTable('participants', $criteria)->withData($participants);
    $table->setTitle(__('Participants'));

    $table->addMetaData('bulkActions', $col);

    if (Access::allows('Attendance', 'attendance_take_adHoc') && $canEditEvent) {
        $table->addHeaderAction('setFutureAbsence', __('Set Future Absence'))
            ->setURL('/modules/TawasulAttendance/attendance_future_byPerson.php')
            ->addParams([
                'scope'              => 'multiple',
                'target'             => 'Select',
                'absenceType'        => $event['allDay'] == 'Y' ? 'full' : 'partial',
                'date'               => $event['dateStart'],
                'dateStart'          => $event['dateStart'],
                'dateEnd'            => $event['dateEnd'],
                'timeStart'          => $event['timeStart'],
                'timeEnd'            => $event['timeEnd'],
                'tawasulPersonIDList' => implode(',', $futureAbsenceStudents),
                'foreignTable'       => 'tawasulCalendarEvent',
                'foreignTableID'     => $tawasulCalendarEventID
            ])
            ->setIcon('user-plus')
            ->setAttribute('target', '_blank')
            ->displayLabel();
    }

    $table->addHeaderAction('add', __('Add Participants'))
        ->setURL('/modules/TawasulCalendar/calendar_event_participants_add.php')
        ->addParam('tawasulCalendarEventID', $tawasulCalendarEventID)
        ->displayLabel();

    $table->addColumn('image_240', __('Photo'))
        ->context('primary')
        ->width('7%')
        ->notSortable()
        ->format(Format::using('userPhoto', ['image_240', 'xs']));

    $table->addColumn('name', __('Name'))
        ->description(__('Role'))
        ->sortable(['surname', 'preferredName'])
        ->context('primary')
        ->format(Format::using('nameLinked', ['tawasulPersonID', '', 'preferredName', 'surname', 'roleCategory', true, true]))
        ->formatDetails(function ($values) {
            return Format::small($values['roleCategory']);
        });

    $table->addColumn('formGroup', __('Form Group'))->context('primary');

    $table->addColumn('role', __('Event Role'))
        ->description(__('Added On'))
        ->context('secondary')
        ->format(function ($values) {
            $status = $values['role'] != 'Attendee' ? 'message' : 'dull';
            return Format::tag(__($values['role']), $status);
        })
        ->formatDetails(function ($values) {
            return Format::small(Format::dateTime($values['timestampCreated']));
        });

    $table->addColumn('futureAbsenceStatus', __('Future Absence'))
        ->notSortable()
        ->format(function ($values) use ($futureAbsences) {
            if ($values['roleCategory'] != 'Student') return '';
            if (isset($futureAbsences[$values['tawasulPersonID']]) && !empty($futureAbsences[$values['tawasulPersonID']])) {
                $absenceType = $futureAbsences[$values['tawasulPersonID']]['type'] ?? '';
                $absenceReason = $futureAbsences[$values['tawasulPersonID']]['reason'] ?? '';
                $absenceComment = $futureAbsences[$values['tawasulPersonID']]['comment'] ?? '';
                return Format::tag(__($absenceType), 'success', !empty($absenceComment) ? $absenceReason.': '.$absenceComment : $absenceReason  );
            }
            return Format::tag(__('N/A'), 'dull');
        });

    if (!empty($conflicts)) {
        $table->addColumn('conflict', __('Status'))
            ->format(function ($values) use ($conflicts) {
                if (empty($conflicts[$values['tawasulPersonID']])) return '';

                $conflict = $conflicts[$values['tawasulPersonID']];
                $url = Url::fromModuleRoute('TawasulCalendar', 'calendar_event_view')->withQueryParams(['tawasulCalendarEventID' => $conflict['tawasulCalendarEventID']]);
                return Format::link($url, Format::tag(__('Conflict'), 'warning', $conflict['event']));
            });
    }

    // ACTIONS
    $table->addActionColumn()
        ->addParam('tawasulCalendarEventPersonID')
        ->addParam('tawasulCalendarEventID', $tawasulCalendarEventID)
        ->addParam('tawasulPersonID')
        ->format(function ($event, $actions) {
            $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/TawasulCalendar/calendar_event_participants_delete.php');
        });

    $table->addCheckboxColumn('tawasulCalendarEventPersonID');

    echo $form->getOutput();
}
