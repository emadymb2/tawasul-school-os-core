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

use TawasulOS\Domain\Timetable\TimetableDayGateway;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_exception_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Check if tawasulTTDayID, tawasulTTID, tawasulSchoolYearID, tawasulTTColumnRowID, and tawasulCourseClassID specified
    $tawasulTTDayID = $_GET['tawasulTTDayID'] ?? '';
    $tawasulTTID = $_GET['tawasulTTID'] ?? '';
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $tawasulTTColumnRowID = $_GET['tawasulTTColumnRowID'] ?? '';
    $tawasulCourseClassID = $_GET['tawasulCourseClassID'] ?? '';
    $tawasulPersonID = $_GET['tawasulPersonID'] ?? '';

    if ($tawasulTTDayID == '' or $tawasulTTID == '' or $tawasulSchoolYearID == '' or $tawasulTTColumnRowID == '' or $tawasulCourseClassID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        $urlParams = [
            'tawasulTTDayID' => $tawasulTTDayID,
            'tawasulTTID' => $tawasulTTID,
            'tawasulSchoolYearID' => $tawasulSchoolYearID,
            'tawasulTTColumnRowID' => $tawasulTTColumnRowID,
            'tawasulCourseClassID' => $tawasulCourseClassID,
        ];

        $page->breadcrumbs
            ->add(__('Manage Timetables'), 'tt.php', $urlParams)
            ->add(__('Edit Timetable'), 'tt_edit.php', $urlParams)
            ->add(__('Edit Timetable Day'), 'tt_edit_day_edit.php', $urlParams)
            ->add(__('Classes in Period'), 'tt_edit_day_edit_class.php', $urlParams)
            ->add(__('Class List Exception'), 'tt_edit_day_edit_class_exception.php', $urlParams)
            ->add(__('Add Exception'));

        $timetableDayGateway = $container->get(TimetableDayGateway::class);
        $values = $timetableDayGateway->getTTDayRowClassByID($tawasulTTDayID, $tawasulTTColumnRowID, $tawasulCourseClassID);

        if (empty($values)) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $tawasulTTDayRowClassID = $values['tawasulTTDayRowClassID'];

            $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/tt_edit_day_edit_class_exception_addProcess.php?tawasulTTDayID=$tawasulTTDayID&tawasulTTID=$tawasulTTID&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulTTColumnRowID=$tawasulTTColumnRowID&tawasulTTDayRowClass=$tawasulTTDayRowClassID&tawasulCourseClassID=$tawasulCourseClassID&tawasulTTDayRowClassID=$tawasulTTDayRowClassID");

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulTTID', $tawasulTTID);
            $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

            $participants = array();
            try {
                $dataSelect = array('tawasulCourseClassID' => $tawasulCourseClassID, 'tawasulTTDayRowClassID' => $tawasulTTDayRowClassID);
                $sqlSelect = "SELECT tawasulPerson.tawasulPersonID, preferredName, surname, tawasulCourseClassPerson.role
                    FROM tawasulPerson
                        JOIN tawasulCourseClassPerson ON (tawasulCourseClassPerson.tawasulPersonID=tawasulPerson.tawasulPersonID)
                        LEFT JOIN tawasulTTDayRowClassException ON (tawasulTTDayRowClassException.tawasulPersonID=tawasulPerson.tawasulPersonID AND tawasulTTDayRowClassException.tawasulTTDayRowClassID=:tawasulTTDayRowClassID)
                    WHERE tawasulCourseClassID=:tawasulCourseClassID
                        AND NOT role='Student - Left'
                        AND NOT role='Teacher - Left'
                        AND NOT tawasulPerson.status='Left'
                        AND tawasulTTDayRowClassExceptionID IS NULL
                    ORDER BY surname, preferredName";
                $resultSelect = $connection2->prepare($sqlSelect);
                $resultSelect->execute($dataSelect);
            } catch (PDOException $e) {}
            while ($rowSelect = $resultSelect->fetch()) {
                $participants[$rowSelect['tawasulPersonID']] = Format::name('', htmlPrep($rowSelect['preferredName']), htmlPrep($rowSelect['surname']), 'Student', true).' ('.__($rowSelect['role'].')');
            }

            $row = $form->addRow();
                $row->addLabel('Members', __('Participants'));
                $row->addSelect('Members')->fromArray($participants)->selectMultiple()->required()->setSize(8)->selected($tawasulPersonID);

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();
        }
    }
}
