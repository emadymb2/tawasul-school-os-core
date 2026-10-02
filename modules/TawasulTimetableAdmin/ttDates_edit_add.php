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

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetableAdmin/ttDates_edit_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $dateStamp = $_GET['dateStamp'] ?? '';

    if ($tawasulSchoolYearID == '' or $dateStamp == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        if (isSchoolOpen($guid, date('Y-m-d', $dateStamp), $connection2, true) != true) {
            echo "<div class='error'>";
            echo __('School is not open on the specified day.');
            echo '</div>';
        } else {
            
                $data = array('tawasulSchoolYearID' => $tawasulSchoolYearID);
                $sql = 'SELECT * FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearID';
                $result = $connection2->prepare($sql);
                $result->execute($data);

            if ($result->rowCount() != 1) {
                $page->addError(__('The specified record does not exist.'));
            } else {
                $values = $result->fetch();

                //Proceed!
                $page->breadcrumbs
                    ->add(__('Tie Days to Dates'), 'ttDates.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID])
                    ->add(__('Edit Days in Date'), 'ttDates_edit.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID, 'dateStamp' => $dateStamp])
                    ->add(__('Add Day to Date'));

				$form = Form::create('addTTDate', $session->get('absoluteURL').'/modules/'.$session->get('module').'/ttDates_edit_addProcess.php');

				$form->addHiddenValue('address', $session->get('address'));
				$form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);
				$form->addHiddenValue('dateStamp', $dateStamp);

				$row = $form->addRow();
					$row->addLabel('schoolYearName', __('School Year'));
					$row->addTextField('schoolYearName')->readonly()->setValue($values['name']);

				$row = $form->addRow();
                    $row->addLabel('dateName', __('Date'));
					$row->addTextField('dateName')->readonly()->setValue(date('d/m/Y l', $dateStamp));

				$data = array('tawasulSchoolYearID' => $tawasulSchoolYearID, 'date' => date('Y-m-d', $dateStamp));
				$sql = "SELECT tawasulTTDay.tawasulTTDayID as value, CONCAT(tawasulTT.name, ': ', tawasulTTDay.nameShort) as name
						FROM tawasulTT
						JOIN tawasulTTDay ON (tawasulTTDay.tawasulTTID=tawasulTT.tawasulTTID)
						LEFT JOIN (SELECT tawasulTTDay.tawasulTTID, tawasulTTDayDate.date
                        	FROM tawasulTTDay
                        	JOIN tawasulTTDayDate ON (tawasulTTDay.tawasulTTDayID=tawasulTTDayDate.tawasulTTDayID)
                        ) AS dateCheck ON (dateCheck.tawasulTTID=tawasulTT.tawasulTTID AND dateCheck.date=:date)
						WHERE tawasulTT.tawasulSchoolYearID=:tawasulSchoolYearID
						AND dateCheck.tawasulTTID IS NULL
						ORDER BY name";

				$row = $form->addRow();
                    $row->addLabel('tawasulTTDayID', __('Day'));
                    $row->addSearchSelect('tawasulTTDayID')->fromQuery($pdo, $sql, $data)->required()->placeholder();

				$row = $form->addRow();
					$row->addFooter();
					$row->addSubmit();

				echo $form->getOutput();
            }
        }
    }
}
