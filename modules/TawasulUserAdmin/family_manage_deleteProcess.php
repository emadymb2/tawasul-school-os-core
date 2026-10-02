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

$tawasulFamilyID = $_POST['tawasulFamilyID'] ?? '';
$search = $_GET['search'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/family_manage_delete.php&tawasulFamilyID=$tawasulFamilyID&search=$search";
$URLDelete = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/family_manage.php&search=$search";

if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/family_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if family specified
    if ($tawasulFamilyID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulFamilyID' => $tawasulFamilyID);
            $sql = 'SELECT * FROM tawasulFamily WHERE tawasulFamilyID=:tawasulFamilyID';
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
            //Delete children

                $dataDelete = array('tawasulFamilyID' => $tawasulFamilyID);
                $sqlDelete = 'DELETE FROM tawasulFamilyChild WHERE tawasulFamilyID=:tawasulFamilyID';
                $resultDelete = $connection2->prepare($sqlDelete);
                $resultDelete->execute($dataDelete);

            //Delete adults

                $dataDelete = array('tawasulFamilyID' => $tawasulFamilyID);
                $sqlDelete = 'DELETE FROM tawasulFamilyAdult WHERE tawasulFamilyID=:tawasulFamilyID';
                $resultDelete = $connection2->prepare($sqlDelete);
                $resultDelete->execute($dataDelete);

            //Delete Family
            try {
                $dataDelete = array('tawasulFamilyID' => $tawasulFamilyID);
                $sqlDelete = 'DELETE FROM tawasulFamily WHERE tawasulFamilyID=:tawasulFamilyID';
                $resultDelete = $connection2->prepare($sqlDelete);
                $resultDelete->execute($dataDelete);
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
