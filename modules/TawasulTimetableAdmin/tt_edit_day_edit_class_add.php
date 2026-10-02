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

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/tt_edit_day_edit_class_add.php') == false) {
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
        
            $data = array('tawasulTTDayID' => $tawasulTTDayID, 'tawasulTTID' => $tawasulTTID, 'tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulTTColumnRowID' => $tawasulTTColumnRowID);
            $sql = 'SELECT tawasulTT.name AS ttName, tawasulTTDay.name AS dayName, tawasulTTColumnRow.name AS rowName, tawasulYearGroupIDList FROM tawasulTT JOIN tawasulTTDay ON (tawasulTT.tawasulTTID=tawasulTTDay.tawasulTTID) JOIN tawasulTTColumn ON (tawasulTTDay.tawasulTTColumnID=tawasulTTColumn.tawasulTTColumnID) JOIN tawasulTTColumnRow ON (tawasulTTColumn.tawasulTTColumnID=tawasulTTColumnRow.tawasulTTColumnID) WHERE tawasulTTDay.tawasulTTDayID=:tawasulTTDayID AND tawasulTT.tawasulTTID=:tawasulTTID AND tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulTTColumnRowID=:tawasulTTColumnRowID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            //Let's go!
            $values = $result->fetch();

            $urlParams = ['tawasulTTDayID' => $tawasulTTDayID, 'tawasulTTID' => $tawasulTTID, 'tawasulSchoolYearID' => $tawasulSchoolYearID, 'tawasulTTColumnRowID' => $tawasulTTColumnRowID];

            $page->breadcrumbs
                ->add(__('Manage Timetables'), 'tt.php', $urlParams)
                ->add(__('Edit Timetable'), 'tt_edit.php', $urlParams)
                ->add(__('Edit Timetable Day'), 'tt_edit_day_edit.php', $urlParams)
                ->add(__('Classes in Period'), 'tt_edit_day_edit_class.php', $urlParams)
                ->add(__('Add Class to Period'));

            $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module')."/tt_edit_day_edit_class_addProcess.php?&tawasulTTDayID=$tawasulTTDayID&tawasulTTID=$tawasulTTID&tawasulSchoolYearID=$tawasulSchoolYearID&tawasulTTColumnRowID=$tawasulTTColumnRowID");

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

            $classes = array();
            $years = explode(',', $values['tawasulYearGroupIDList']);
            try {
                $dataSelect = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
                if (count($years) > 0) {
                    $sqlSelectWhere = ' AND (';
                    for ($i = 0; $i < count($years); ++$i) {
                        if ($i > 0) {
                            $sqlSelectWhere = $sqlSelectWhere.' OR ';
                        }
                        $dataSelect["year$i"] = '%'.$years[$i].'%';
                        $sqlSelectWhere = $sqlSelectWhere."(tawasulYearGroupIDList LIKE :year$i)";
                    }
                    $sqlSelectWhere = $sqlSelectWhere.')';
                }
                $sqlSelect = "SELECT tawasulCourseClassID, tawasulCourse.nameShort AS course, tawasulCourseClass.nameShort AS class FROM tawasulCourse JOIN tawasulCourseClass ON (tawasulCourse.tawasulCourseID=tawasulCourseClass.tawasulCourseID) WHERE tawasulSchoolYearID=:tawasulSchoolYearID $sqlSelectWhere ORDER BY course, class";
                $resultSelect = $connection2->prepare($sqlSelect);
                $resultSelect->execute($dataSelect);
            } catch (PDOException $e) {}
            while ($rowSelect = $resultSelect->fetch()) {
                
                    $dataUnique = array('tawasulTTDayID' => $tawasulTTDayID, 'tawasulTTColumnRowID' => $tawasulTTColumnRowID, 'tawasulCourseClassID' => $rowSelect['tawasulCourseClassID']);
                    $sqlUnique = 'SELECT * FROM tawasulTTDayRowClass WHERE tawasulTTDayID=:tawasulTTDayID AND tawasulTTColumnRowID=:tawasulTTColumnRowID AND tawasulCourseClassID=:tawasulCourseClassID';
                    $resultUnique = $connection2->prepare($sqlUnique);
                    $resultUnique->execute($dataUnique);
                if ($resultUnique->rowCount() < 1) {
                    $classes[$rowSelect['tawasulCourseClassID']] = htmlPrep($rowSelect['course']).'.'.htmlPrep($rowSelect['class']);
                }
            }
            $row = $form->addRow();
                $row->addLabel('tawasulCourseClassID', __('Class'));
                $row->addSelect('tawasulCourseClassID')->fromArray($classes)->required()->placeholder();

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
                if ($resultUnique->rowCount() < 1) {
                    $locations[$rowSelect['tawasulSpaceID']] = htmlPrep($rowSelect['name']);
                }
            }
            $row = $form->addRow();
                $row->addLabel('tawasulSpaceID', __('Location'));
                $row->addSelect('tawasulSpaceID')->fromArray($locations)->placeholder();

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();
        }
    }
}
