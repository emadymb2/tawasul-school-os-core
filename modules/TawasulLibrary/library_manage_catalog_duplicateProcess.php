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
use TawasulOS\Domain\Library\LibraryGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

include './moduleFunctions.php';

$tawasulLibraryItemID = $_POST['tawasulLibraryItemID'] ?? '';
$address = $_POST['address'] ?? '';
$name = $_GET['name'] ?? '';
$tawasulLibraryTypeID = $_GET['tawasulLibraryTypeID'] ?? '';
$tawasulSpaceID = $_GET['tawasulSpaceID'] ?? '';
$status = $_GET['status'] ?? '';
$tawasulPersonIDOwnership = $_GET['tawasulPersonIDOwnership'] ?? '';
$typeSpecificFields = $_GET['typeSpecificFields'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/library_manage_catalog_duplicate.php&tawasulLibraryItemID=$tawasulLibraryItemID&name=$name&tawasulLibraryTypeID=$tawasulLibraryTypeID&tawasulSpaceID=$tawasulSpaceID&status=$status&tawasulPersonIDOwnership=$tawasulPersonIDOwnership&typeSpecificFields=$typeSpecificFields";

if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/library_manage_catalog_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulLibraryItemID specified
    if (empty($tawasulLibraryItemID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $libraryGateway = $container->get(LibraryGateway::class);
    $row = $libraryGateway->getLibraryItemDetails($tawasulLibraryItemID);

    if (empty($row)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $status = 'Available';
    $attach = $_POST['attach'] ?? 'N';

    $imageType = $row['imageType'];
    $imageLocation = $row['imageLocation'];
    $tawasulLibraryTypeID = $row['tawasulLibraryTypeID'];
    $name = $row['name'];
    $producer = $row['producer'];
    $vendor = $row['vendor'];
    $purchaseDate = $row['purchaseDate'];
    $invoiceNumber = $row['invoiceNumber'];
    $cost = $row['cost'];
    $replacement = $row['replacement'];
    $tawasulSchoolYearIDReplacement = $row['tawasulSchoolYearIDReplacement'];
    $replacementCost = $row['replacementCost'];
    $comment = $row['comment'];
    $tawasulSpaceID = $row['tawasulSpaceID'];
    $locationDetail = $row['locationDetail'];
    $ownershipType = $row['ownershipType'];
    $tawasulPersonIDOwnership = $row['tawasulPersonIDOwnership'];
    $tawasulDepartmentID = $row['tawasulDepartmentID'];
    $borrowable = $row['borrowable'];
    $bookable = $row['bookable'];
    $fields = $row['fields'];
    $count = $_POST['count'] ?? '';

    if ($tawasulLibraryTypeID == '' or $name == '' or $producer == '' or $borrowable == '' or $count == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }
    
    $partialFail = false;

    for ($i = 1; $i <= $count; ++$i) {
        $id = $_POST['id'.$i];

        if (empty($id)) {
            $partialFail = true;
        }

        $data = ['tawasulLibraryTypeID' => $tawasulLibraryTypeID, 'id' => $id, 'name' => $name, 'producer' => $producer, 'fields' => $fields, 'vendor' => $vendor, 'purchaseDate' => $purchaseDate, 'invoiceNumber' => $invoiceNumber,  'cost' => $cost, 'imageType' => $imageType, 'imageLocation' => $imageLocation, 'replacement' => $replacement, 'tawasulSchoolYearIDReplacement' => $tawasulSchoolYearIDReplacement, 'replacementCost' => $replacementCost, 'comment' => $comment, 'tawasulSpaceID' => $tawasulSpaceID, 'locationDetail' => $locationDetail, 'ownershipType' => $ownershipType, 'tawasulPersonIDOwnership' => $tawasulPersonIDOwnership, 'tawasulDepartmentID' => $tawasulDepartmentID, 'borrowable' => $borrowable, 'bookable' => $bookable, 'status' => $status, 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'), 'timestampCreator' => date('Y-m-d H:i:s', time())];

        if ($attach == 'Y') {
            $data['tawasulLibraryItemIDParent'] = $tawasulLibraryItemID;
        }

        if (!$libraryGateway->unique($data, ['id'])) {
            $partialFail = true;
        } else {
            $inserted = $libraryGateway->insert($data);

            if (!$inserted) {
                $partialFail = true;
            }
        }

        if ($attach == 'Y') {
            $libraryGateway->updateFromParentRecord($tawasulLibraryItemID);
        }
    }

    $URL .= $partialFail
        ? '&return=error2'
        : '&return=success0';
    header("Location: {$URL}");
}
