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

//Module includes
include './moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address']).'/report_catalogSummary.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulLibrary/report_catalogSummary.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $naownershipTypeme = trim($_GET['ownershipType']);
    $tawasulLibraryTypeID = trim($_GET['tawasulLibraryTypeID']);
    $tawasulSpaceID = trim($_GET['tawasulSpaceID']);
    $status = trim($_GET['status']);

    $ownershipType = null;
    if (isset($_GET['ownershipType'])) {
        $ownershipType = trim($_GET['ownershipType']);
    }
    $tawasulLibraryTypeID = null;
    if (isset($_GET['tawasulLibraryTypeID'])) {
        $tawasulLibraryTypeID = trim($_GET['tawasulLibraryTypeID']);
    }
    $tawasulSpaceID = null;
    if (isset($_GET['tawasulSpaceID'])) {
        $tawasulSpaceID = trim($_GET['tawasulSpaceID']);
    }
    $status = null;
    if (isset($_GET['status'])) {
        $status = trim($_GET['status']);
    }

    try {
        $data = array();
        $sqlWhere = 'WHERE ';
        if ($ownershipType != '') {
            $data['ownershipType'] = $ownershipType;
            $sqlWhere .= 'ownershipType=:ownershipType AND ';
        }
        if ($tawasulLibraryTypeID != '') {
            $data['tawasulLibraryTypeID'] = $tawasulLibraryTypeID;
            $sqlWhere .= 'tawasulLibraryTypeID=:tawasulLibraryTypeID AND ';
        }
        if ($tawasulSpaceID != '') {
            $data['tawasulSpaceID'] = $tawasulSpaceID;
            $sqlWhere .= 'tawasulSpaceID=:tawasulSpaceID AND ';
        }
        if ($status != '') {
            $data['status'] = $status;
            $sqlWhere .= 'status=:status AND ';
        }
        if ($sqlWhere == 'WHERE ') {
            $sqlWhere = '';
        } else {
            $sqlWhere = substr($sqlWhere, 0, -5);
        }
        $sql = "SELECT * FROM tawasulLibraryItem $sqlWhere ORDER BY id";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }

    if ($result->rowCount() < 1) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
    } else {
        //Proceed!
		include './report_catalogSummaryExportContents.php';
    }
}
