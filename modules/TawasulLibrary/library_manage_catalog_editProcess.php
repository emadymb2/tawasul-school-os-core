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

use TawasulOS\Contracts\Filesystem\FileHandler;
use TawasulOS\Data\Validator;
use TawasulOS\Domain\Library\LibraryGateway;
use TawasulOS\Domain\Library\LibraryTypeGateway;
use TawasulOS\Services\Format;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['imageLink' => 'URL', 'fieldLink' => 'URL']);

include './moduleFunctions.php';

$tawasulLibraryItemID = $_POST['tawasulLibraryItemID'] ?? '';
$address = $_POST['address'] ?? '';
$name = $_GET['name'] ?? '';
$tawasulLibraryTypeID = $_GET['tawasulLibraryTypeID'] ?? '';
$tawasulSpaceID = $_GET['tawasulSpaceID'] ?? '';
$status = $_GET['status'] ?? '';
$tawasulPersonIDOwnership = $_GET['tawasulPersonIDOwnership'] ?? '';
$typeSpecificFields = $_GET['typeSpecificFields'] ?? '';
$isParentRecord = $_POST['isParentRecord'] ?? '';
$isChildRecord = $_POST['isChildRecord'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/library_manage_catalog_edit.php&tawasulLibraryItemID=$tawasulLibraryItemID&name=$name&tawasulLibraryTypeID=$tawasulLibraryTypeID&tawasulSpaceID=$tawasulSpaceID&status=$status&tawasulPersonIDOwnership=$tawasulPersonIDOwnership&typeSpecificFields=$typeSpecificFields";

if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/library_manage_catalog_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulLibraryItemID specified
    if ($tawasulLibraryItemID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } 

    $libraryGateway = $container->get(LibraryGateway::class);
    $libraryTypeGateway = $container->get(LibraryTypeGateway::class);

    $row = $libraryGateway->getByID($tawasulLibraryItemID);

    if (empty($row)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    } 

    //Proceed!
    //Get general fields
    $tawasulLibraryTypeID = $_POST['tawasulLibraryTypeID'] ?? '';
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $producer = $_POST['producer'] ?? '';
    $vendor = $_POST['vendor'] ?? '';
    $detach = $_POST['detach'] ?? '';
    $attach = $_POST['attach'] ?? '';
    $purchaseDate = !empty($_POST['purchaseDate']) ? Format::dateConvert($_POST['purchaseDate']) : null;

    $invoiceNumber = $_POST['invoiceNumber'] ?? '';
    $cost = !empty($_POST['cost']) ? $_POST['cost'] : null;
    $imageType = $_POST['imageType'] ?? '';
    if ($imageType == 'Link') {
        $imageLocation = $_POST['imageLink'] ?? '';
    } elseif ($imageType == 'File') {
        $imageLocation = $row['imageLocation'];
    } else {
        $imageLocation = '';
    }
    $replacement = $_POST['replacement'] ?? '';
    $tawasulSchoolYearIDReplacement = null;
    $replacementCost = null;
    if ($replacement == 'Y') {
        if ($_POST['tawasulSchoolYearIDReplacement'] != '') {
            $tawasulSchoolYearIDReplacement = $_POST['tawasulSchoolYearIDReplacement'] ?? '';
        }
        if ($_POST['replacementCost'] != '') {
            $replacementCost = $_POST['replacementCost'] ?? '';
        }
    } else {
        $replacement == 'N';
    }
    $comment = $_POST['comment'] ?? '';
    $tawasulSpaceID = $_POST['tawasulSpaceID'] ?? null;
    $locationDetail = $_POST['locationDetail'] ?? '';
    $ownershipType = $_POST['ownershipType'] ?? '';
    $tawasulPersonIDOwnership = null;
    if ($ownershipType == 'School' and $_POST['tawasulPersonIDOwnershipSchool'] != '') {
        $tawasulPersonIDOwnership = $_POST['tawasulPersonIDOwnershipSchool'] ?? '';
    } elseif ($ownershipType == 'Individual' and $_POST['tawasulPersonIDOwnershipIndividual'] != '') {
        $tawasulPersonIDOwnership = $_POST['tawasulPersonIDOwnershipIndividual'] ?? '';
    }
    $tawasulDepartmentID = $_POST['tawasulDepartmentID'] ?? '';
    $bookable = $_POST['bookable'] ?? '';
    $borrowable = $_POST['borrowable'] ?? '';
    if ($borrowable == 'Y') {
        $status = $_POST['statusBorrowable'] ?? '';
    } else {
        $status = $_POST['statusNotBorrowable'] ?? '';
    }
    $physicalCondition = $_POST['physicalCondition'] ?? '';

    //Get type-specific fields
    $typeDetails = $libraryTypeGateway->getByID($tawasulLibraryTypeID);

    if (!empty($typeDetails)) {
        $fieldsIn = json_decode($typeDetails['fields'], true);
        $fieldsOut = [];
        foreach ($fieldsIn as $field) {
            $fieldName = preg_replace('/ |\(|\)/', '', $field['name']);
            if ($field['type'] == 'Date') {
                $fieldsOut[$field['name']] = !empty($_POST['field'.$fieldName]) ? Format::dateConvert($_POST['field'.$fieldName]) : null;
            } else {
                $fieldsOut[$field['name']] = $_POST['field'.$fieldName] ?? null;
            }
        }
    }

    if ($tawasulLibraryTypeID == '' or $name == '' or $id == '' or $producer == '' or $bookable == '' or $borrowable == '' or $replacement == '') {
        $URL .= '&return=error3';
        header("Location: {$URL}");
        exit;
    }
    
    // Enable detaching and attaching child records
    if ($isChildRecord && $detach == 'Y') {
        $tawasulLibraryItemIDParent = null;
    } elseif (!$isChildRecord && !$isParentRecord && !empty($attach)) {
        $parent = $libraryGateway->getByRecordID($attach);
        $tawasulLibraryItemIDParent = $parent['tawasulLibraryItemID'] ?? null;
        $isChildRecord = true;
    } else {
        $tawasulLibraryItemIDParent = $row['tawasulLibraryItemIDParent'] ?? null;
    }

    if ($isChildRecord) {
        $data = ['id' => $id, 'vendor' => $vendor, 'purchaseDate' => $purchaseDate, 'invoiceNumber' => $invoiceNumber, 'cost' => $cost, 'replacement' => $replacement, 'tawasulSchoolYearIDReplacement' => $tawasulSchoolYearIDReplacement, 'replacementCost' => $replacementCost, 'comment' => $comment, 'tawasulSpaceID' => $tawasulSpaceID, 'locationDetail' => $locationDetail, 'ownershipType' => $ownershipType, 'tawasulPersonIDOwnership' => $tawasulPersonIDOwnership, 'bookable' => $bookable, 'borrowable' => $borrowable, 'status' => $status, 'physicalCondition' => $physicalCondition, 'tawasulPersonIDUpdate' => $session->get('tawasulPersonID'), 'tawasulLibraryItemIDParent' => $tawasulLibraryItemIDParent, 'timestampUpdate' => date('Y-m-d H:i:s')];
    } else {
        $data = ['id' => $id, 'name' => $name, 'producer' => $producer, 'fields' => json_encode($fieldsOut), 'vendor' => $vendor, 'purchaseDate' => $purchaseDate, 'invoiceNumber' => $invoiceNumber, 'cost' => $cost, 'imageType' => $imageType, 'imageLocation' => $imageLocation, 'replacement' => $replacement, 'tawasulSchoolYearIDReplacement' => $tawasulSchoolYearIDReplacement, 'replacementCost' => $replacementCost, 'comment' => $comment, 'tawasulSpaceID' => $tawasulSpaceID, 'locationDetail' => $locationDetail, 'ownershipType' => $ownershipType, 'tawasulPersonIDOwnership' => $tawasulPersonIDOwnership, 'tawasulDepartmentID' => $tawasulDepartmentID, 'bookable' => $bookable, 'borrowable' => $borrowable, 'status' => $status, 'physicalCondition' => $physicalCondition, 'tawasulPersonIDUpdate' => $session->get('tawasulPersonID'), 'tawasulLibraryItemIDParent' => $tawasulLibraryItemIDParent, 'timestampUpdate' => date('Y-m-d H:i:s')];
    }

    // Check unique inputs for uniqueness
    if (!$libraryGateway->unique($data, ['id'], $tawasulLibraryItemID)) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    } 

    $partialFail = false;
    $fileMetaData = null;

    // Move attached image  file, if there is one
    if (!empty($_FILES['imageFile']['tmp_name']) && $imageType == 'File') {
        $fileUploader = new TawasulOS\FileUploader($pdo, $session);
        $fileUploader->getFileExtensions('Graphics/Design');

        $file = (isset($_FILES['imageFile']))? $_FILES['imageFile'] : null;

        // Upload the file, return the /uploads relative path
        $data['imageLocation'] = $fileUploader->uploadFromPost($file, $id);

        if (empty($data['imageLocation'])) {
            $partialFail = true;
        } else {
            $fileMetaData = $fileUploader->getFileMetaData($data['imageLocation']);
        }
    }

    // Write to database
    $updated = $libraryGateway->update($tawasulLibraryItemID, $data);

    // Record file tracking
    if (!empty($fileMetaData) && !empty($tawasulLibraryItemID)) {
        $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulLibraryItem', $tawasulLibraryItemID, 'imageLocation');

        if (empty($tawasulFileID)) {
            $partialFail = true;
        }
    }

    if (!$updated) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit; 
    }

    // Update child records
    if ($isParentRecord) {
        $libraryGateway->updateChildRecords($tawasulLibraryItemID);
    } else if ($isChildRecord) {
        $libraryGateway->updateFromParentRecord($tawasulLibraryItemID);
    }

    $URL .= $partialFail
        ? '&return=warning1'
        : '&return=success0';
    header("Location: {$URL}");

}
