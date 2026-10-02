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

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/tt_edit_day_edit_class.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Check if tawasulTTDayID, tawasulTTID, tawasulSchoolYearID, and tawasulTTColumnRowID specified
    $tawasulTTDayID = $_GET['tawasulTTDayID'] ?? '';
    $tawasulTTID = $_GET['tawasulTTID'] ?? '';
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $tawasulTTColumnRowID = $_GET['tawasulTTColumnRowID'] ?? '';

    if ($tawasulTTDayID == '' or $tawasulTTID == '' or $tawasulSchoolYearID == '' or $tawasulTTColumnRowID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        //Timetable, day, period

        $timetableDayGateway = $container->get(TimetableDayGateway::class);
        $values = $timetableDayGateway->getTTDayRowByID($tawasulTTDayID, $tawasulTTColumnRowID);

        if (empty($values)) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            $urlParams = ['tawasulTTDayID' => $tawasulTTDayID, 'tawasulTTID' => $tawasulTTID, 'tawasulSchoolYearID' => $tawasulSchoolYearID];

            $page->breadcrumbs
                ->add(__('Manage Timetables'), 'tt.php', $urlParams)
                ->add(__('Edit Timetable'), 'tt_edit.php', $urlParams)
                ->add(__('Edit Timetable Day'), 'tt_edit_day_edit.php', $urlParams)
                ->add(__('Classes in Period'));


            // DISPLAY TIMETABLE DATA
            $table = DataTable::createDetails('ttDay');

            $table->addColumn('ttName', __('Timetable'));
            $table->addColumn('dayName', __('Day'));
            $table->addColumn('rowName', __('Period'));

            echo $table->render([$values]);

            $ttDayRowClasses = $timetableDayGateway->selectTTDayRowClassesByID($tawasulTTDayID, $tawasulTTColumnRowID);

            // DATA TABLE
            $table = DataTable::create('timetableDayRowClasses');
            $table->setTitle(__('Classes in Period'));

            $table->addHeaderAction('add', __('Add'))
                ->setURL('/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_add.php')
                ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
                ->addParam('tawasulTTID', $tawasulTTID)
                ->addParam('tawasulTTDayID', $tawasulTTDayID)
                ->addParam('tawasulTTColumnRowID', $tawasulTTColumnRowID)
                ->displayLabel();

            $table->addColumn('class', __('Class'))->format(Format::using('courseClassName', ['courseName', 'className']));
            $table->addColumn('location', __('Location'));

            // ACTIONS
            $table->addActionColumn()
                ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
                ->addParam('tawasulTTID', $tawasulTTID)
                ->addParam('tawasulTTDayID', $tawasulTTDayID)
                ->addParam('tawasulTTColumnRowID', $tawasulTTColumnRowID)
                ->addParam('tawasulTTDayRowClassID')
                ->addParam('tawasulCourseClassID')
                ->format(function ($values, $actions) {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_edit.php');
                        
                    $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_delete.php');

                    $actions->addAction('exceptions', __('Exceptions'))
                        ->setIcon('user-minus')
                        ->setURL('/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_exception.php');
                });

            echo $table->render($ttDayRowClasses->toDataSet());
        }
    }
}
