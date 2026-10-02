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

use TawasulOS\Domain\Library\LibraryShelfGateway;
use TawasulOS\Domain\Library\LibraryShelfItemGateway;

require_once __DIR__ . '/../../tawasul.php';

$tawasulLibraryShelfID = $_POST['tawasulLibraryShelfID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulLibrary/library_manage_shelves.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/library_manage_shelves_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} elseif (empty($tawasulLibraryShelfID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $shelfGateway = $container->get(LibraryShelfGateway::class);
    $itemGateway = $container->get(LibraryShelfItemGateway::class);
    $values = $shelfGateway->getByID($tawasulLibraryShelfID);

    if (empty($values)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $partialFail = false;
    $criteria = $itemGateway->newQueryCriteria(true)
    ->fromPOST();

    $shelfItems = $itemGateway->queryItemsByShelfID($tawasulLibraryShelfID, $criteria);
    if(count($shelfItems) >= 1) {
        try {
            $data = array('tawasulLibraryShelfID' => $tawasulLibraryShelfID);
            $sql = 'DELETE FROM tawasulLibraryShelfItem WHERE tawasulLibraryShelfID=:tawasulLibraryShelfID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }
    }

    $partialFail &= $shelfGateway->delete($tawasulLibraryShelfID);

    $URL .= $partialFail
        ? '&return=warning1'
        : '&return=success0';

    header("Location: {$URL}");
}
