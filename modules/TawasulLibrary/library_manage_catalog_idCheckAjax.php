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

//TawasulOS system-wide include
require_once __DIR__ . '/../../tawasul.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/library_manage_catalog.php') == false) {
    die(__('Your request failed because you do not have access to this action.'));
} else {
    $tawasulLibraryItemID = $_POST['tawasulLibraryItemID'] ?? '';
    $id = isset($_POST['id'])? $_POST['id'] : (isset($_POST['idCheck'])? $_POST['idCheck'] : '');

    $data = array('tawasulLibraryItemID' => $tawasulLibraryItemID, 'id' => $id);
    $sql = "SELECT COUNT(*) FROM tawasulLibraryItem WHERE tawasulLibraryItemID<>:tawasulLibraryItemID AND id=:id";
    $result = $pdo->executeQuery($data, $sql);

    echo ($result && $result->rowCount() == 1)? $result->fetchColumn(0) : -1;
}
