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

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/tt_edit_day_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $tawasulTTID = $_GET['tawasulTTID'] ?? '';

    if ($tawasulSchoolYearID == '' or $tawasulTTID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $data = array('tawasulTTID' => $tawasulTTID, 'tawasulSchoolYearID' => $tawasulSchoolYearID);
            $sql = 'SELECT tawasulTTID, tawasulSchoolYear.name AS schoolYear, tawasulTT.name AS ttName FROM tawasulTT JOIN tawasulSchoolYear ON (tawasulTT.tawasulSchoolYearID=tawasulSchoolYear.tawasulSchoolYearID) WHERE tawasulTTID=:tawasulTTID AND tawasulTT.tawasulSchoolYearID=:tawasulSchoolYearID';
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            $page->addError(__('The specified record does not exist.'));
        } else {
            $values = $result->fetch();

            $page->breadcrumbs
                ->add(__('Manage Timetables'), 'tt.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID])
                ->add(__('Edit Timetable'), 'tt_edit.php', ['tawasulTTID' => $tawasulTTID, 'tawasulSchoolYearID' => $tawasulSchoolYearID])
                ->add(__('Add Timetable Day'));

            $editLink = '';
            if (isset($_GET['editID'])) {
                $editLink = $session->get('absoluteURL').'/index.php?q=/modules/TawasulTimetableAdmin/tt_edit_day_edit.php&tawasulTTDayID='.$_GET['editID'].'&tawasulSchoolYearID='.$_GET['tawasulSchoolYearID'].'&tawasulTTID='.$_GET['tawasulTTID'];
            }
            $page->return->setEditLink($editLink);


            $form = Form::create('action', $session->get('absoluteURL').'/modules/'.$session->get('module').'/tt_edit_day_addProcess.php');

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('tawasulTTID', $tawasulTTID);
            $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

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
                $row->addColor('color');

            $row = $form->addRow();
                $row->addLabel('fontColor', __('Header Font Colour'))->description(__('Click to select a colour.'));
                $row->addColor('fontColor');

            $data = array();
            $sql = "SELECT tawasulTTColumnID as value, name FROM tawasulTTColumn ORDER BY name";
            $row = $form->addRow();
                $row->addLabel('tawasulTTColumnID', __('Timetable Column'));
                $row->addSelect('tawasulTTColumnID')->fromQuery($pdo, $sql, $data)->required()->placeholder();

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();
        }
    }
}
