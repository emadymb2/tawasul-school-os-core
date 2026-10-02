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

use TawasulOS\Forms\Prefab\DeleteForm;
use TawasulOS\Domain\Timetable\TimetableDayGateway;

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_exception_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Check if tawasulTTDayID, tawasulTTID, tawasulSchoolYearID, tawasulTTColumnRowID, tawasulCourseClassID, tawasulTTDayRowClassID, and tawasulTTDayRowClassExceptionID specified
    $tawasulTTDayID = $_GET['tawasulTTDayID'] ?? '';
    $tawasulTTID = $_GET['tawasulTTID'] ?? '';
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $tawasulTTColumnRowID = $_GET['tawasulTTColumnRowID'] ?? '';
    $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
    $tawasulTTDayRowClassID = $_GET['tawasulTTDayRowClassID'] ?? '';
    $tawasulTTDayRowClassExceptionID = $_GET['tawasulTTDayRowClassExceptionID'] ?? '';

    if ($tawasulTTDayID == '' or $tawasulTTID == '' or $tawasulSchoolYearID == '' or $tawasulTTColumnRowID == '' or $tawasulCourseClassID == '' or $tawasulTTDayRowClassID == '' or $tawasulTTDayRowClassExceptionID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        $timetableDayGateway = $container->get(TimetableDayGateway::class);
        $values = $timetableDayGateway->getTTDayRowClassByID($tawasulTTDayID, $tawasulTTColumnRowID, $tawasulCourseClassID);

        if (empty($values)) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $timetableDayGateway->getTTDayRowClassExceptionByID($tawasulTTDayRowClassExceptionID);

            if (empty($values)) {
                $page->addError(__('The specified record cannot be found.'));
            } else {
                $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module')."/tt_edit_day_edit_class_exception_deleteProcess.php");
                $form->addHiddenValue('tawasulTTDayID', $tawasulTTDayID);
                $form->addHiddenValue('tawasulTTID', $tawasulTTID);
                $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);
                $form->addHiddenValue('tawasulTTColumnRowID', $tawasulTTColumnRowID);
                $form->addHiddenValue('tawasulTTDayRowClassID', $tawasulTTDayRowClassID);
                $form->addHiddenValue('tawasulCourseClassID', $tawasulCourseClassID);
                $form->addHiddenValue('tawasulTTDayRowClassExceptionID', $tawasulTTDayRowClassExceptionID);
                echo $form->getOutput();
            }
        }
    }
}
