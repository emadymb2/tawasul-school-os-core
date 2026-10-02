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

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_exception.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Check if tawasulTTDayID, tawasulTTID, tawasulSchoolYearID, tawasulTTColumnRowID, and tawasulCourseClassID specified
    $tawasulTTDayID = $_GET['tawasulTTDayID'] ?? '';
    $tawasulTTID = $_GET['tawasulTTID'] ?? '';
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $tawasulTTColumnRowID = $_GET['tawasulTTColumnRowID'] ?? '';
    $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';

    if ($tawasulTTDayID == '' or $tawasulTTID == '' or $tawasulSchoolYearID == '' or $tawasulTTColumnRowID == '' or $tawasulCourseClassID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

        $timetableDayGateway = $container->get(TimetableDayGateway::class);
        $values = $timetableDayGateway->getTTDayRowClassByID($tawasulTTDayID, $tawasulTTColumnRowID, $tawasulCourseClassID);

        if (empty($values)) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $tawasulTTDayRowClassID = $values['tawasulTTDayRowClassID'];

            $urlParams = [
                'tawasulTTDayID' => $tawasulTTDayID,
                'tawasulTTID' => $tawasulTTID,
                'tawasulSchoolYearID' => $tawasulSchoolYearID,
                'tawasulTTColumnRowID' => $tawasulTTColumnRowID
            ];

            $page->breadcrumbs
                ->add(__('Manage Timetables'), 'tt.php', $urlParams)
                ->add(__('Edit Timetable'), 'tt_edit.php', $urlParams)
                ->add(__('Edit Timetable Day'), 'tt_edit_day_edit.php', $urlParams)
                ->add(__('Classes in Period'), 'tt_edit_day_edit_class.php', $urlParams)
                ->add(__('Class List Exception'));

            $ttDayRowClassExceptions = $timetableDayGateway->selectTTDayRowClassExceptionsByID($tawasulTTDayRowClassID);

            // DATA TABLE
            $table = DataTable::create('timetableDayRowClassExceptions');

            $table->addHeaderAction('add', __('Add'))
                ->setURL('/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_exception_add.php')
                ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
                ->addParam('tawasulTTID', $tawasulTTID)
                ->addParam('tawasulTTDayID', $tawasulTTDayID)
                ->addParam('tawasulTTColumnRowID', $tawasulTTColumnRowID)
                ->addParam('tawasulCourseClassID', $tawasulCourseClassID)
                ->addParam('tawasulTTDayRowClassID', $tawasulTTDayRowClassID)
                ->displayLabel();

            $table->addColumn('name', __('Name'))->format(Format::using('name', ['', 'preferredName', 'surname', 'Student', true]));

            // ACTIONS
            $table->addActionColumn()
                ->addParam('tawasulSchoolYearID', $tawasulSchoolYearID)
                ->addParam('tawasulTTID', $tawasulTTID)
                ->addParam('tawasulTTDayID', $tawasulTTDayID)
                ->addParam('tawasulTTColumnRowID', $tawasulTTColumnRowID)
                ->addParam('tawasulCourseClassID', $tawasulCourseClassID)
                ->addParam('tawasulTTDayRowClassID', $tawasulTTDayRowClassID)
                ->addParam('tawasulTTDayRowClassExceptionID')
                ->format(function ($values, $actions) {
                    $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_exception_delete.php');
                });

            echo $table->render($ttDayRowClassExceptions->toDataSet());
        }
    }
}
