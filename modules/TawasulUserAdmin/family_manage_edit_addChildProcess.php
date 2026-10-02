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

$tawasulFamilyID = $_GET['tawasulFamilyID'] ?? '';
$tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
$search = $_GET['search'] ?? '';

if ($tawasulFamilyID == '') { echo 'Fatal error loading this page!';
} else {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/family_manage_edit.php&tawasulFamilyID=$tawasulFamilyID&search=$search";

    if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/family_manage_edit.php') == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if person specified
        if ($tawasulPersonID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            try {
                $data = array('tawasulPersonID' => $tawasulPersonID);
                $sql = 'SELECT * FROM tawasulPerson WHERE tawasulPersonID=:tawasulPersonID';
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
                //Check for an existing child or parent record in this family
                try {
                    $dataCheck = array('tawasulPersonID1' => $tawasulPersonID, 'tawasulFamilyID1' => $tawasulFamilyID, 'tawasulPersonID2' => $tawasulPersonID, 'tawasulFamilyID2' => $tawasulFamilyID);
                    $sqlCheck = 'SELECT tawasulPersonID FROM tawasulFamilyChild WHERE tawasulPersonID=:tawasulPersonID1 AND tawasulFamilyID=:tawasulFamilyID2 UNION SELECT tawasulPersonID FROM tawasulFamilyAdult WHERE tawasulPersonID=:tawasulPersonID2 AND tawasulFamilyID=:tawasulFamilyID2';
                    $resultCheck = $connection2->prepare($sqlCheck);
                    $resultCheck->execute($dataCheck);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($resultCheck->rowCount() > 0) {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                } else {
                    //Validate Inputs
                    $comment = $_POST['comment'] ?? '';

                    //Write to database
                    try {
                        $data = array('tawasulFamilyID' => $tawasulFamilyID, 'tawasulPersonID' => $tawasulPersonID, 'comment' => $comment);
                        $sql = 'INSERT INTO tawasulFamilyChild SET tawasulFamilyID=:tawasulFamilyID, tawasulPersonID=:tawasulPersonID, comment=:comment';
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
}
