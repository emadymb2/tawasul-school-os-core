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

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulAttendance/attendance_take_byPerson_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {

	$tawasulAttendanceLogPersonID = $_GET['tawasulAttendanceLogPersonID'] ?? '';
	$tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
	$currentDate = $_GET['currentDate'] ?? '';

	if ( empty($tawasulAttendanceLogPersonID) || empty($tawasulPersonID) || empty($currentDate) ) {
		$page->addError(__('You have not specified one or more required parameters.'));
	} else {
	    //Proceed!

			$dataPerson = array('tawasulPersonID' => $tawasulPersonID, 'tawasulAttendanceLogPersonID' => $tawasulAttendanceLogPersonID );
			$sqlPerson = "SELECT tawasulAttendanceLogPersonID FROM tawasulAttendanceLogPerson WHERE tawasulPersonID=:tawasulPersonID AND tawasulAttendanceLogPersonID=:tawasulAttendanceLogPersonID ";
			$resultPerson = $connection2->prepare($sqlPerson);
			$resultPerson->execute($dataPerson);

	    if ($resultPerson->rowCount() != 1) {
	    	$page->addError(__('The specified record does not exist.'));
	    } else {
			$form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module'). '/attendance_take_byPerson_deleteProcess.php');
			$form->addHiddenValue('tawasulAttendanceLogPersonID', $tawasulAttendanceLogPersonID);
			$form->addHiddenValue('tawasulPersonID', $tawasulPersonID);
			$form->addHiddenValue('currentDate', $currentDate);

			echo $form->getOutput();
	    }
	}
}
