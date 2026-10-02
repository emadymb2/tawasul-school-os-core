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
use TawasulOS\Domain\Library\LibraryShelfGateway;
use TawasulOS\Domain\Library\LibraryShelfItemGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulLibraryShelfID = $_POST['tawasulLibraryShelfID'];

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulLibrary/library_manage_shelves_edit.php&tawasulLibraryShelfID='.$tawasulLibraryShelfID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/library_manage_shelves_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} elseif (empty($tawasulLibraryShelfID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $libraryShelfGateway = $container->get(LibraryShelfGateway::class);
    $libraryShelfItemGateway = $container->get(LibraryShelfItemGateway::class);

    $data = [
        'name'      => $_POST['name'] ?? '',
        'active'    => $_POST['active'] ?? '',
        'type'      => $_POST['type'] ?? '',
        'shuffle'   => $_POST['shuffle'] ?? '',
        'field'     => $_POST['field'] ?? 'Custom',
        'fieldValue' => $_POST['fieldValue'] ?? 'Custom',
        'tawasulLibraryTypeID' => $_POST['tawasulLibraryTypeID'] ?? NULL,
    ];

    if (empty($data['name']) || empty($data['active']) || empty($data['field']) || empty($data['shuffle'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $updated = $libraryShelfGateway->update($tawasulLibraryShelfID, $data);

    $shelfItems = isset($_POST['addItems']) ? explode(',', $_POST['addItems']) : [];

    $criteria = $libraryShelfItemGateway->newQueryCriteria(true)
    ->sortBy('name')
    ->fromPOST();

    $currentItems = $libraryShelfItemGateway->queryItemsByShelfID($tawasulLibraryShelfID, $criteria)->getColumn('tawasulLibraryItemID');
    if(!empty($shelfItems)) {
        foreach($shelfItems as $item) {
            if(!in_array($item, $currentItems)) {
                $libraryShelfItemGateway->insertShelfItem($item, $tawasulLibraryShelfID);
            }
        }
    }

    $URL .= !$updated
        ? '&return=error2'
        : '&return=success0';

    header("Location: {$URL}");
}
