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

use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\Messenger\GroupGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Calendar\CalendarEventGateway;
use TawasulOS\Domain\Calendar\CalendarEventPersonGateway;
use TawasulOS\Support\Facades\Access;


if (isActionAccessible($guid, $connection2, '/modules/TawasulCalendar/calendar_event_participants.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $tawasulCalendarEventID = (isset($_GET['tawasulCalendarEventID'])) ? $_GET['tawasulCalendarEventID'] : null;
    $calendarEventGateway = $container->get(CalendarEventGateway::class);
    $calendarEventPersonGateway = $container->get(CalendarEventPersonGateway::class);

    $urlParams = ['tawasulCalendarEventID' => $_GET['tawasulCalendarEventID']];

    $page->breadcrumbs
        ->add(__('Manage Activities'), 'calendar_event_manage.php')
        ->add(__('Edit Participants'), 'calendar_event_participants.php',  $urlParams)
        ->add(__('Add Participants'));

    if (empty($tawasulCalendarEventID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Get event details
    $event = $calendarEventGateway->getEventDetailsByID($tawasulCalendarEventID, $session->get('tawasulPersonID'));
    if (empty($tawasulCalendarEventID) || empty($event)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Check for access to edit this event
    $canEditEvent = $event['editor'] == 'Y' && Access::allows('Calendar', 'calendar_event_edit');
    if (!$canEditEvent && !Access::allows('Calendar', 'calendar_event_edit', 'Manage Events_all')) {
        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        return;
    }

    $form = Form::create('studentEnrolment', $session->get('absoluteURL').'/modules/'.$session->get('module')."/calendar_event_participants_addProcess.php?tawasulCalendarEventID=".$tawasulCalendarEventID);
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
    
    $form->setTitle(__('Choose Participant'));

    $targetOptions = [
        'Messenger'    => __('Messenger Group'),
        'Activity' => __('Activity Enrolment'),
        'Class'   => __('Class Enrolment'),
        'Individual'   => __('Select Manually'),
    ];

    $row = $form->addRow();
        $row->addLabel('target', __('Target'));
        $row->addSelect('target')->fromArray($targetOptions)->required()->placeholder();

    $form->toggleVisibilityByClass('targetActivity')->onSelect('target')->when('Activity');
    $form->toggleVisibilityByClass('targetMessenger')->onSelect('target')->when('Messenger');
    $form->toggleVisibilityByClass('targetClass')->onSelect('target')->when('Class');
    $form->toggleVisibilityByClass('targetSelect')->onSelect('target')->when('Individual');

    // Activity
    $activities = $container->get(ActivityGateway::class)->selectActivitiesBySchoolYear($session->get('tawasulSchoolYearID'))->fetchKeyPair();
    $row = $form->addRow()->addClass('targetActivity');
        $row->addLabel('tawasulActivityID', __('Activity'));
        $row->addSelect('tawasulActivityID')->fromArray($activities)->required()->placeholder();

    // Messenger Groups
    $groups = $container->get(GroupGateway::class)->selectGroupsBySchoolYear($session->get('tawasulSchoolYearID'))->fetchKeyPair();
    $row = $form->addRow()->addClass('targetMessenger');
        $row->addLabel('tawasulGroupID', __('Messenger Group'));
        $row->addSelect('tawasulGroupID')->fromArray($groups)->required()->placeholder();

    // Class Enrolments
    $row = $form->addRow()->addClass('targetClass');
        $row->addLabel('tawasulCourseClassID', __('Class'));
        $row->addSelectClass('tawasulCourseClassID', $session->get('tawasulSchoolYearID'), $session->get('tawasulPersonID'))
        ->required()
        ->placeholder();

    // Select Attendees
    $row = $form->addRow()->addClass('targetSelect');
        $col = $row->addColumn();
            $col->addLabel('participants', __('Attendees'));
            $col->addSelectUsers('participants', $session->get('tawasulSchoolYearID'), ['includeStudents' => true, 'useMultiSelect' => true])
                ->required()
                ->mergeGroupings();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
