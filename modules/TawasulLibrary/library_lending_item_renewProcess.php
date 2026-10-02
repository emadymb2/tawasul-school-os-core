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

use TawasulOS\Services\Format;
use TawasulOS\Data\Validator;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulLibraryItemEventID = $_GET['tawasulLibraryItemEventID'] ?? '';
$address = $_POST['address'] ?? '';
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
        : $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/library_lending_item_renew.php&tawasulLibraryItemID=$tawasulLibraryItemID&tawasulLibraryItemEventID=$tawasulLibraryItemEventID&name=$name&tawasulLibraryTypeID=$tawasulLibraryTypeID&tawasulSpaceID=$tawasulSpaceID&status=$status";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/library_lending_item_renew.php') == false) {
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

            if ($result->rowCount() != 1) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                //Validate Inputs
                $returnExpected = !empty($_POST['returnExpected']) ? Format::dateConvert($_POST['returnExpected']) : null;

                //Write to database
                try {
                    $data = array('tawasulLibraryItemEventID' => $tawasulLibraryItemEventID, 'returnExpected' => $returnExpected);
                    $sql = 'UPDATE tawasulLibraryItemEvent SET returnExpected=:returnExpected WHERE tawasulLibraryItemEventID=:tawasulLibraryItemEventID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                try {
                    $data = array('tawasulLibraryItemID' => $tawasulLibraryItemID, 'returnExpected' => $returnExpected);
                    $sql = 'UPDATE tawasulLibraryItem SET returnExpected=:returnExpected WHERE tawasulLibraryItemID=:tawasulLibraryItemID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                $URL .= '&return=success0';
                header("Location: {$URL}");
            }
        }
    }
}
