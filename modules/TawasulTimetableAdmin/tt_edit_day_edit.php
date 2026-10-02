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
use TawasulOS\Tables\DataTable;
use TawasulOS\Services\Format;
use TawasulOS\Domain\Timetable\TimetableDayGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/tt_edit_day_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Check if tawasulTTDayID, tawasulTTID, and tawasulSchoolYearID specified
    $tawasulTTDayID = $_GET['tawasulTTDayID'] ?? '';
    $tawasulTTID = $_GET['tawasulTTID'] ?? '';
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    if ($tawasulTTDayID == '' or $tawasulTTID == '' or $tawasulSchoolYearID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

        $timetableDayGateway = $container->get(TimetableDayGateway::class);
        $values = $timetableDayGateway->getTTDayByID($tawasulTTDayID);

        if (empty($values)) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $page->breadcrumbs
                ->add(__('Manage Timetables'), 'tt.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID])
                ->add(__('Edit Timetable'), 'tt_edit.php', ['tawasulTTID' => $tawasulTTID, 'tawasulSchoolYearID' => $tawasulSchoolYearID])
                ->add(__('Edit Timetable Day'));

            $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/tt_edit_day_editProcess.php?tawasulTTDayID=$tawasulTTDayID&tawasulTTID=$tawasulTTID&tawasulSchoolYearID=$tawasulSchoolYearID");

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulTTID', $tawasulTTID);
            $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);
            $form->addHiddenValue('tawasulTTColumnID', $values['tawasulTTColumnID']);

            $row = $form->addRow();
                $row->addLabel('schoolYear', __('School Year'));
                $row->addTextField('schoolYear')->maxLength(20)->required()->readonly()->setValue($values['schoolYear']);

            $row = $form->addRow();
                $row->addLabel('ttName', __('Timetable'));
                $row->addTextField('ttName')->maxLength(20)->required()->readonly()->setValue($values['ttName']);

            $row = $form->addRow();
                $row->addLabel('name', __('Name'))->description(__('Must be unique for this school year.'));
                $row->addTextField('name')->maxLength(12)->required();

            $row = $form->addRow();
                $row->addLabel('nameShort', __('Short Name'))->description(__('Must be unique for this school year.'));
                $row->addTextField('nameShort')->maxLength(4)->required();

            $row = $form->addRow();
                $row->addLabel('color', __('Header Background Colour'))->description(__('Click to select a colour.'));
                $row->addColor("color");

            $row = $form->addRow();
                $row->addLabel('fontColor', __('Header Font Colour'))->description(__('Click to select a colour.'));
                $row->addColor("fontColor");

            $row = $form->addRow();
                $row->addLabel('columnName', __('Timetable Column'));
                $row->addTextField('columnName')->maxLength(30)->required()->readonly();

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            $form->loadAllValuesFrom($values);

            echo $form->getOutput();

            echo '<h2>';
            echo __('Edit Classes by Period');
            echo '</h2>';

            $ttDayRows = $timetableDayGateway->selectTTDayRowsByID($tawasulTTDayID);

            // DATA TABLE
            $table = DataTable::create('timetableDayRows');

            $table->addColumn('name', __('Name'));
            $table->addColumn('nameShort', __('Short Name'));
            $table->addColumn('time', __('Time'))->format(Format::using('timeRange', ['timeStart', 'timeEnd']));
            $table->addColumn('type', __('Type'))->translatable();
            $table->addColumn('classCount', __('Classes'));

            // ACTIONS
            $table->addActionColumn()
                ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
                ->addParam('tawasulTTID', $tawasulTTID)
                ->addParam('tawasulTTDayID', $tawasulTTDayID)
                ->addParam('tawasulTTColumnRowID')
                ->format(function ($values, $actions) {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/TawasulTimetableAdmin/tt_edit_day_edit_class.php');
                });

            echo $table->render($ttDayRows->toDataSet());
        }
    }
}
