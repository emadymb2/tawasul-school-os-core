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

require_once __DIR__ . '/../../tawasul.php';

$tawasulTTSpaceBookingID = $_POST['tawasulTTSpaceBookingID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/spaceBooking_manage_delete.php&tawasulTTSpaceBookingID='.$tawasulTTSpaceBookingID;
$URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/spaceBooking_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulTimetable/spaceBooking_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if tawasulTTSpaceBookingID specified
        if ($tawasulTTSpaceBookingID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
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
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            if ($result->rowCount() != 1) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                //Write to database
                try {
                    $data = array('tawasulTTSpaceBookingID' => $tawasulTTSpaceBookingID);
                    $sql = 'DELETE FROM tawasulTTSpaceBooking WHERE tawasulTTSpaceBookingID=:tawasulTTSpaceBookingID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                $URLDelete = $URLDelete.'&return=success0';
                header("Location: {$URLDelete}");
            }
        }
    }
}
