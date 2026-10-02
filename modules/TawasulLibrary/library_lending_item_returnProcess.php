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
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulLibraryItemEventID = $_GET['tawasulLibraryItemEventID'] ?? '';
$tawasulLibraryItemID = $_GET['tawasulLibraryItemID'] ?? '';
$name = $_GET['name'] ?? '';
$tawasulLibraryTypeID = $_GET['tawasulLibraryTypeID'] ?? '';
$tawasulSpaceID = $_GET['tawasulSpaceID'] ?? '';
$status = $_GET['status'] ?? '';

$tawasulPersonIDStudent = $_REQUEST['tawasulPersonIDStudent'] ?? '';
$lendingAction = $_REQUEST['lendingAction'] ?? '';

if ($tawasulLibraryItemID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = !empty($tawasulPersonIDStudent)
        ? $session->get('absoluteURL')."/index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID=$tawasulPersonIDStudent&search=&search=&allStudents=&subpage=Library Borrowing&lendingAction=$lendingAction"
        : $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/library_lending_item_return.php&tawasulLibraryItemID=$tawasulLibraryItemID&tawasulLibraryItemEventID=$tawasulLibraryItemEventID&name=$name&tawasulLibraryTypeID=$tawasulLibraryTypeID&tawasulSpaceID=$tawasulSpaceID&status=$status";
    $URLSuccess = !empty($tawasulPersonIDStudent)
        ? $session->get('absoluteURL')."/index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID=$tawasulPersonIDStudent&search=&search=&allStudents=&subpage=Library Borrowing&lendingAction=$lendingAction"
        : $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/library_lending_item.php&tawasulLibraryItemID=$tawasulLibraryItemID&tawasulLibraryItemEventID=$tawasulLibraryItemEventID&name=$name&tawasulLibraryTypeID=$tawasulLibraryTypeID&tawasulSpaceID=$tawasulSpaceID&status=$status";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/library_lending_item_return.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if event specified
        if ($tawasulLibraryItemEventID == '' or $tawasulLibraryItemID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulLibraryItemEventID' => $tawasulLibraryItemEventID, 'tawasulLibraryItemID' => $tawasulLibraryItemID);
                $sql = 'SELECT * FROM tawasulLibraryItemEvent JOIN tawasulLibraryItem ON (tawasulLibraryItemEvent.tawasulLibraryItemID=tawasulLibraryItem.tawasulLibraryItemID) WHERE tawasulLibraryItemEventID=:tawasulLibraryItemEventID AND tawasulLibraryItem.tawasulLibraryItemID=:tawasulLibraryItemID';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            if ($result->rowCount() < 1) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                $event = $result->fetch();

                //Validate Inputs
                $returnAction = $_POST['returnAction'] ?? '';
                $status = '';
                if ($returnAction == 'Reserve') {
                    $status = 'Reserved';
                } elseif ($returnAction == 'Decommission') {
                    $status = 'Decommissioned';
                } elseif ($returnAction == 'Repair') {
                    $status = 'Repair';
                }
                $tawasulPersonIDReturnAction = $_POST['tawasulPersonIDReturnAction'] ?? null;


                //Write to database
                if ($event['status'] == 'Reserved') {
                    $data = ['tawasulLibraryItemEventID' => $tawasulLibraryItemEventID];
                    $sql = "DELETE FROM tawasulLibraryItemEvent WHERE tawasulLibraryItemEventID=:tawasulLibraryItemEventID";
                    $deleted = $pdo->delete($sql, $data);
                } else {
                    $data = ['timestampReturn' => date('Y-m-d H:i:s', time()), 'tawasulLibraryItemEventID' => $tawasulLibraryItemEventID, 'tawasulPersonIDIn' => $session->get('tawasulPersonID')];
                    $sql = "UPDATE tawasulLibraryItemEvent SET status='Returned', timestampReturn=:timestampReturn, tawasulPersonIDIn=:tawasulPersonIDIn WHERE tawasulLibraryItemEventID=:tawasulLibraryItemEventID";
                    $updated = $pdo->update($sql, $data);
                }

                //No return action, so just mark the item
                if ($returnAction == '' && $event['status'] != 'Reserved') {
                    $data = array('tawasulLibraryItemID' => $tawasulLibraryItemID, 'tawasulPersonIDStatusRecorder' => $session->get('tawasulPersonID'), 'timestampStatus' => date('Y-m-d H:i:s', time()));
                    $sql = "UPDATE tawasulLibraryItem SET status='Available', tawasulPersonIDStatusResponsible=NULL, tawasulPersonIDStatusRecorder=:tawasulPersonIDStatusRecorder, timestampStatus=:timestampStatus, returnExpected=NULL, returnAction='', tawasulPersonIDReturnAction=NULL WHERE tawasulLibraryItemID=:tawasulLibraryItemID";
                    $updated = $pdo->update($sql, $data);
                }
                //Return action, so mark the item, and create a new event
                else {
                    try {
                        $data = array('tawasulLibraryItemID' => $tawasulLibraryItemID, 'status' => $status, 'tawasulPersonIDStatusResponsible' => $tawasulPersonIDReturnAction, 'tawasulPersonIDOut' => $session->get('tawasulPersonID'), 'timestampOut' => date('Y-m-d H:i:s', time()));
                        $sql = "INSERT INTO tawasulLibraryItemEvent SET tawasulLibraryItemID=:tawasulLibraryItemID, status=:status, tawasulPersonIDStatusResponsible=:tawasulPersonIDStatusResponsible, tawasulPersonIDOut=:tawasulPersonIDOut, timestampOut=:timestampOut, returnExpected=NULL, returnAction='', tawasulPersonIDReturnAction=NULL";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    try {
                        $data = array('tawasulLibraryItemID' => $tawasulLibraryItemID, 'status' => $status, 'tawasulPersonIDStatusResponsible' => $tawasulPersonIDReturnAction, 'tawasulPersonIDStatusRecorder' => $session->get('tawasulPersonID'), 'timestampStatus' => date('Y-m-d H:i:s', time()));
                        $sql = "UPDATE tawasulLibraryItem SET status=:status, tawasulPersonIDStatusResponsible=:tawasulPersonIDStatusResponsible, tawasulPersonIDStatusRecorder=:tawasulPersonIDStatusRecorder, timestampStatus=:timestampStatus, returnExpected=NULL, returnAction='', tawasulPersonIDReturnAction=NULL WHERE tawasulLibraryItemID=:tawasulLibraryItemID";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }
                }

                $URL = $URLSuccess.'&return=success0';
                header("Location: {$URL}");
            }
        }
    }
}
