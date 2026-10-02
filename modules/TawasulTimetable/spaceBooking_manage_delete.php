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

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/spaceBooking_manage_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Proceed!
        //Check if tawasulTTSpaceBookingID specified
        $tawasulTTSpaceBookingID = $_GET['tawasulTTSpaceBookingID'] ?? '';
        if ($tawasulTTSpaceBookingID == '') {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {
            try {
                if ($highestAction == 'Manage Facility Bookings_allBookings') {
                    $data = array('tawasulTTSpaceBookingID1' => $tawasulTTSpaceBookingID, 'tawasulTTSpaceBookingID2' => $tawasulTTSpaceBookingID);
                    $sql = "(SELECT tawasulTTSpaceBooking.*, tawasulSpace.name AS name, surname, preferredName FROM tawasulTTSpaceBooking JOIN tawasulSpace ON (tawasulTTSpaceBooking.foreignKeyID=tawasulSpace.tawasulSpaceID) JOIN tawasulPerson ON (tawasulTTSpaceBooking.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE foreignKey='tawasulSpaceID' AND tawasulTTSpaceBookingID=:tawasulTTSpaceBookingID1) UNION (SELECT tawasulTTSpaceBooking.*, tawasulLibraryItem.name AS name, surname, preferredName FROM tawasulTTSpaceBooking JOIN tawasulLibraryItem ON (tawasulTTSpaceBooking.foreignKeyID=tawasulLibraryItem.tawasulLibraryItemID) JOIN tawasulPerson ON (tawasulTTSpaceBooking.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE foreignKey='tawasulLibraryItemID' AND tawasulTTSpaceBookingID=:tawasulTTSpaceBookingID2) ORDER BY date, name";
                } else {
                    $data = array('tawasulPersonID1' => $session->get('tawasulPersonID'), 'tawasulPersonID2' => $session->get('tawasulPersonID'), 'tawasulTTSpaceBookingID1' => $tawasulTTSpaceBookingID, 'tawasulTTSpaceBookingID2' => $tawasulTTSpaceBookingID);
                    $sql = "(SELECT tawasulTTSpaceBooking.*, tawasulSpace.name AS name, surname, preferredName FROM tawasulTTSpaceBooking JOIN tawasulSpace ON (tawasulTTSpaceBooking.foreignKeyID=tawasulSpace.tawasulSpaceID) JOIN tawasulPerson ON (tawasulTTSpaceBooking.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE foreignKey='tawasulSpaceID' AND tawasulTTSpaceBooking.tawasulPersonID=:tawasulPersonID1 AND tawasulTTSpaceBookingID=:tawasulTTSpaceBookingID1) UNION (SELECT tawasulTTSpaceBooking.*, tawasulLibraryItem.name AS name, surname, preferredName FROM tawasulTTSpaceBooking JOIN tawasulLibraryItem ON (tawasulTTSpaceBooking.foreignKeyID=tawasulLibraryItem.tawasulLibraryItemID) JOIN tawasulPerson ON (tawasulTTSpaceBooking.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE foreignKey='tawasulLibraryItemID' AND tawasulTTSpaceBooking.tawasulPersonID=:tawasulPersonID2 AND tawasulTTSpaceBookingID=:tawasulTTSpaceBookingID2) ORDER BY date, name";
                }
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
            }

            if ($result->rowCount() != 1) {
                $page->addError(__('The specified record cannot be found.'));
            } else {
                $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module')."/spaceBooking_manage_deleteProcess.php");
                $form->addHiddenValue('tawasulTTSpaceBookingID', $tawasulTTSpaceBookingID);
                echo $form->getOutput();
            }
        }
    }
}
