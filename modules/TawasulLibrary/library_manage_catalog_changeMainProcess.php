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
use TawasulOS\Domain\Library\LibraryGateway;
use TawasulOS\Domain\Library\LibraryTypeGateway;

require_once __DIR__ . '/../../tawasul.php';
include './moduleFunctions.php';

$tawasulLibraryItemID = $_GET['tawasulLibraryItemID'] ?? '';
$tawasulLibraryItemIDParent = $_GET['tawasulLibraryItemIDParent'] ?? '';

$name = $_GET['name'] ?? '';
$tawasulLibraryTypeID = $_GET['tawasulLibraryTypeID'] ?? '';
$tawasulSpaceID = $_GET['tawasulSpaceID'] ?? '';
$status = $_GET['status'] ?? '';
$tawasulPersonIDOwnership = $_GET['tawasulPersonIDOwnership'] ?? '';
$typeSpecificFields = $_GET['typeSpecificFields'] ?? '';

$URL = $session->get('absoluteURL')."/index.php?q=/modules/TawasulLibrary/library_manage_catalog_edit.php&tawasulLibraryItemID=$tawasulLibraryItemID&name=$name&tawasulLibraryTypeID=$tawasulLibraryTypeID&tawasulSpaceID=$tawasulSpaceID&status=$status&tawasulPersonIDOwnership=$tawasulPersonIDOwnership&typeSpecificFields=$typeSpecificFields";

if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/library_manage_catalog_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    if (empty($tawasulLibraryItemID) || empty($tawasulLibraryItemIDParent)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } 

    $libraryGateway = $container->get(LibraryGateway::class);

    $values = $libraryGateway->getByID($tawasulLibraryItemID);

    if (empty($values)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Detach this record
    $libraryGateway->update($tawasulLibraryItemID, ['tawasulLibraryItemIDParent' => null]);

    // Update all copies to point to this record
    $libraryGateway->updateWhere(['tawasulLibraryItemIDParent' => $tawasulLibraryItemIDParent], ['tawasulLibraryItemIDParent' => $tawasulLibraryItemID]);

    // Update the previous main record
    $libraryGateway->updateWhere(['tawasulLibraryItemID' => $tawasulLibraryItemIDParent], ['tawasulLibraryItemIDParent' => $tawasulLibraryItemID]);

    // Update all child records to match this one
    $libraryGateway->updateChildRecords($tawasulLibraryItemID);

    $URL .= '&return=success0';
    header("Location: {$URL}");
}
