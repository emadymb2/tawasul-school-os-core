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

use TawasulOS\Forms\Form;
use TawasulOS\Forms\DatabaseFormFactory;
use TawasulOS\Domain\Calendar\CalendarGateway;
use TawasulOS\Domain\Calendar\CalendarEditorGateway;
use TawasulOS\Services\Format;

if (isActionAccessible($guid, $connection2, '/modules/TawasulCalendar/calendar_manage_addEdit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $tawasulSchoolYearID = $_REQUEST['tawasulSchoolYearID'] ?? $session->get('tawasulSchoolYearID');
    $tawasulCalendarID = $_GET['tawasulCalendarID'] ?? '';
    $action = !empty($tawasulCalendarID)? 'edit' : 'add';

    $page->breadcrumbs
        ->add(__('Manage Calendars'), 'calendar_manage.php')
        ->add($action == 'edit' ? __('Edit Calendar') : __('Add Calendar'));

    if (empty($tawasulCalendarID) && isset($_GET['editID'])) {
        $page->return->setEditLink($session->get('absoluteURL').'/index.php?q=/modules/TawasulCalendar/calendar_manage_addEdit.php&tawasulCalendarID='.$_GET['editID']);
    }
    
    $calendarGateway = $container->get(CalendarGateway::class);
    $values = $calendarGateway->getByID($tawasulCalendarID);

    if (!empty($tawasulCalendarID) && empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // FORM
    $form = Form::create('calendar', $session->get('absoluteURL').'/modules/TawasulCalendar/calendar_manage_addEditProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addMeta()->addDefaultContent($action);
    $form->enableQuickSave($action == 'edit');

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('tawasulCalendarID', $tawasulCalendarID);
    $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

    $form->addRow()->addHeading(__('Basic Details'));

    $row = $form->addRow();
        $row->addLabel('name', __('Name'))->description(__('Must be unique for this school year.'));
        $row->addTextField('name')->required()->maxLength(60);

    $row = $form->addRow();
        $row->addLabel('description', __('Description'));
        $row->addTextField('description')->maxLength(255);
        
    $row = $form->addRow();
        $row->addLabel('color', __('Colour'));
        $row->addColor('color')->setPalette('background');
        
    // ACCESS
    $form->addRow()->addHeading(__('Access'));

    $form->toggleVisibilityByClass('viewable')->onRadio('public')->when('N');
    $row = $form->addRow();
        $row->addLabel('public', __('Public'))->description(__('If yes, members of the public can see events on this calendar without logging in.'));
        $row->addYesNo('public')->selected('N');

    $row = $form->addRow()->addClass('viewable');
        $row->addLabel('viewableParticipants', __('Viewable by Participants'))->description(__('If yes, participants can always see events they have been added to, regardless of other permissions.'));
        $row->addYesNo('viewableParticipants')->selected('Y');

    $row = $form->addRow()->addClass('viewable');
        $row->addLabel('viewableStaff', __('Viewable by Staff'));
        $row->addYesNo('viewableStaff')->selected('N');

    $row = $form->addRow()->addClass('viewable');
        $row->addLabel('viewableStudents', __('Viewable by Students'));
        $row->addYesNo('viewableStudents')->selected('N');

    $row = $form->addRow()->addClass('viewable');
        $row->addLabel('viewableParents', __('Viewable by Parents'));
        $row->addYesNo('viewableParents')->selected('N');

    $row = $form->addRow()->addClass('viewable');
        $row->addLabel('viewableOther', __('Viewable by Other'));
        $row->addYesNo('viewableOther')->selected('N');


    // EDITORS
    $form->addRow()->addHeading(__('Editors'));

    $row = $form->addRow();
        $row->addLabel('editableStaff', __('All Staff'))->description(__('Staff can add and edit their own events. They cannot edit other events without editor access.'));
        $row->addYesNo('editableStaff')->selected('N');
    

    // Custom Block Template
    $addBlockButton = $form->getFactory()->createButton(__m('Add'))->addClass('addBlock');

    $blockTemplate = $form->getFactory()->createTable()->setClass('blank');
    $row = $blockTemplate->addRow()->addClass('w-full max-w-lg flex justify-between items-center mt-1 ml-2');
        $row->addSelectStaff('tawasulPersonID')->photo(false)->setClass('flex-1 mr-1')->required()->placeholder();
        $row->addCheckbox('editAllEvents')->setLabelClass('w-32')->alignLeft()->setValue('Y')->description(__('Edit All Events?'));

    // Custom Blocks
    $col = $form->addRow()->addColumn();
    $col->addLabel('editors', __('Editors'));
    $customBlocks = $col->addCustomBlocks('editors', $session)
        ->fromTemplate($blockTemplate)
        ->settings(['inputNameStrategy' => 'object', 'addOnEvent' => 'click', 'uniqueID' => 'tawasulCalendarEditorID'])
        ->placeholder(__('Add a person...'))
        ->addToolInput($addBlockButton);

    $editors = $container->get(CalendarEditorGateway::class)->selectEditorsByCalendar($tawasulCalendarID);
    while ($person = $editors->fetch()) {
        $customBlocks->addBlock($person['tawasulCalendarEditorID'], [
            'tawasulCalendarEditorID' => $person['tawasulCalendarEditorID'],
            'tawasulPersonID'         => $person['tawasulPersonID'],
            'editAllEvents'          => $person['editAllEvents'] ?? 'N',
            'primaryInput' => Format::name('', $person['preferredName'], $person['surname'], 'Staff', false, true)
        ]);
    }

    $row = $form->addRow();
        $row->addSubmit();

    $form->loadAllValuesFrom($values);

    echo $form->getOutput();
}
