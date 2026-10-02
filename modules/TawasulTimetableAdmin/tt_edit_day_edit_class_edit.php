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
use TawasulOS\Support\Facades\Access;

if (!Access::allows('Timetable Admin', 'tt_edit_day_edit_class_edit') && !Access::allows('Timetable', 'tt_space_edit')) {
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
        
            $data = array('tawasulTTColumnRowID' => $tawasulTTColumnRowID, 'tawasulTTDayID' => $tawasulTTDayID, 'tawasulTTColumnRowID' => $tawasulTTColumnRowID, 'tawasulCourseClassID' => $tawasulCourseClassID);
            $sql = 'SELECT tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class, tawasulTTDayRowClassID, tawasulSpaceID FROM tawasulTTDayRowClass JOIN tawasulCourseClass ON (tawasulTTDayRowClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE tawasulTTColumnRowID=:tawasulTTColumnRowID AND tawasulTTDayID=:tawasulTTDayID AND tawasulTTColumnRowID=:tawasulTTColumnRowID AND tawasulCourseClass.tawasulCourseClassID=:tawasulCourseClassID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() < 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $row = $result->fetch();
            $course = $row['course'];
            $class = $row['class'];
            $tawasulSpaceID = $row['tawasulSpaceID'];
            $tawasulTTDayRowClassID = $row['tawasulTTDayRowClassID'];

            
                $data = array('tawasulTTDayID' => $tawasulTTDayID, 'tawasulTTID' => $tawasulTTID, 'tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulTTColumnRowID' => $tawasulTTColumnRowID);
                $sql = 'SELECT tawasulTT.name AS ttName, tawasulTTDay.name AS dayName, tawasulTTColumnRow.name AS rowName, tawasulYearGroupIDList FROM tawasulTT JOIN tawasulTTDay ON (tawasulTT.tawasulTTID=tawasulTTDay.tawasulTTID) JOIN tawasulTTColumn ON (tawasulTTDay.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID) JOIN tawasulTTColumnRow ON (tawasulTTColumn.tawasulTTColumnID=tawasulTTColumnRow.tawasulTTColumnID) WHERE tawasulTTDay.tawasulTTDayID=:tawasulTTDayID AND tawasulTT.tawasulTTID=:tawasulTTID AND tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulTTColumnRowID=:tawasulTTColumnRowID';
                $result = $connection2->prepare($sql);
                $result->execute($data);

            if ($result->rowCount() != 1) {
                $page->addError(__('The specified record cannot be found.'));
            } else {
                $values = $result->fetch();

                $urlParams = ['tawasulTTDayID' => $tawasulTTDayID, 'tawasulTTID' => $tawasulTTID, 'tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulTTColumnRowID' => $tawasulTTColumnRowID];

                $page->breadcrumbs
                    ->add(__('Manage Timetables'), 'tt.php', $urlParams)
                    ->add(__('Edit Timetable'), 'tt_edit.php', $urlParams)
                    ->add(__('Edit Timetable Day'), 'tt_edit_day_edit.php', $urlParams)
                    ->add(__('Classes in Period'), 'tt_edit_day_edit_class.php', $urlParams)
                    ->add(__('Edit Class in Period'));

                $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/tt_edit_day_edit_class_editProcess.php?&tawasulTTDayID=$tawasulTTDayID&tawasulTTID=$tawasulTTID&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulTTColumnRowID=$tawasulTTColumnRowID&tawasulTTDayRowClassID=$tawasulTTDayRowClassID&tawasulCourseClassID=$tawasulCourseClassID");

                $form->addHiddenValue('address', $session->get('address'));
                $form->addHiddenValue('tawasulTTID', $tawasulTTID);
                $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

                $row = $form->addRow();
                    $row->addLabel('ttName', __('Timetable'));
                    $row->addTextField('ttName')->maxLength(20)->required()->readonly()->setValue($values['ttName']);

                $row = $form->addRow();
                    $row->addLabel('dayName', __('Day'));
                    $row->addTextField('dayName')->maxLength(20)->required()->readonly()->setValue($values['dayName']);

                $row = $form->addRow();
                    $row->addLabel('rowName', __('Period'));
                    $row->addTextField('rowName')->maxLength(20)->required()->readonly()->setValue($values['rowName']);

                $row = $form->addRow();
                    $row->addLabel('class', __('Class'));
                    $row->addTextField('class')->maxLength(20)->required()->readonly()->setValue($course.'.'.$class);

                $locations = array() ;
                
                    $dataSelect = array();
                    $sqlSelect = 'SELECT * FROM tawasulSpace ORDER BY name';
                    $resultSelect = $connection2->prepare($sqlSelect);
                    $resultSelect->execute($dataSelect);
                while ($rowSelect = $resultSelect->fetch()) {
                    
                        $dataUnique = array('tawasulTTDayID' => $tawasulTTDayID, 'tawasulTTColumnRowID' => $tawasulTTColumnRowID, 'tawasulSpaceID' => $rowSelect['tawasulSpaceID']);
                        $sqlUnique = 'SELECT * FROM tawasulTTDayRowClass WHERE tawasulTTDayID=:tawasulTTDayID AND tawasulTTColumnRowID=:tawasulTTColumnRowID AND tawasulSpaceID=:tawasulSpaceID';
                        $resultUnique = $connection2->prepare($sqlUnique);
                        $resultUnique->execute($dataUnique);
                    if ($resultUnique->rowCount() < 1 || $rowSelect['tawasulSpaceID'] == $tawasulSpaceID) {
                        $locations[$rowSelect['tawasulSpaceID']] = htmlPrep($rowSelect['name']);
                    }
                }
                $row = $form->addRow();
                    $row->addLabel('tawasulSpaceID', __('Location'));
                    $row->addSelect('tawasulSpaceID')->fromArray($locations)->placeholder()->selected($tawasulSpaceID);

                $row = $form->addRow();
                    $row->addFooter();
                    $row->addSubmit();

                echo $form->getOutput();
            }
        }
    }
}
