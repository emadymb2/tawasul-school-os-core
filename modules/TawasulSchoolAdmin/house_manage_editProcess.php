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
use TawasulOS\Contracts\Filesystem\FileHandler;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulHouseID = $_GET['tawasulHouseID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/house_manage_edit.php&tawasulHouseID='.$tawasulHouseID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulSchoolAdmin/house_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulHouseID specified
    if ($tawasulHouseID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulHouseID' => $tawasulHouseID);
            $sql = 'SELECT * FROM tawasulHouse WHERE tawasulHouseID=:tawasulHouseID';
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
            $name = $_POST['name'] ?? '';
            $nameShort = $_POST['nameShort'] ?? '';

            if ($name == '' or $nameShort == '') {
                $URL .= '&return=error3';
                header("Location: {$URL}");
            } else {
                //Check unique inputs for uniquness
                try {
                    $dataCheck = array('name' => $name, 'nameShort' => $nameShort, 'tawasulHouseID' => $tawasulHouseID);
                    $sqlCheck = 'SELECT * FROM tawasulHouse WHERE (name=:name OR nameShort=:nameShort) AND NOT tawasulHouseID=:tawasulHouseID';
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
                    $row = $result->fetch();

                    //Sort out logo
                    $imageFail = false;
                    $fileMetaData = null;
                    if (!empty($_FILES['file1']['tmp_name'])) {
                        $fileUploader = new TawasulOS\FileUploader($pdo, $session);

                        $file = (isset($_FILES['file1']))? $_FILES['file1'] : null;

                        // Upload the file, return the /uploads relative path
                        $logo = $fileUploader->uploadFromPost($file, $name);

                        if (empty($logo)) {
                            $imageFail = true;
                        } else {
                            $fileMetaData = $fileUploader->getFileMetaData($logo);
                        }
                    } else {
                        // Remove the attachment if it has been deleted, otherwise retain the original value
                        $logo = empty($_POST['logo']) ? '' : $row['logo'];
                    }

                    //Write to database
                    try {
                        $data = array('name' => $name, 'nameShort' => $nameShort, 'logo' => $logo, 'tawasulHouseID' => $tawasulHouseID);
                        $sql = 'UPDATE tawasulHouse SET name=:name, nameShort=:nameShort, logo=:logo WHERE tawasulHouseID=:tawasulHouseID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    // Handle file deletion when user removes attachment
                    if (empty($logo) && !empty($row['logo'])) {
                        $deleted = $container->get(FileHandler::class)->deleteFile('tawasulHouse', $tawasulHouseID, 'logo');
                    }

                    // Record file tracking
                    if (!empty($fileMetaData) && !empty($tawasulHouseID)) {
                        $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulHouse', $tawasulHouseID, 'logo');
                        
                        if (empty($tawasulFileID)) {
                            $imageFail = true;
                        }
                    }

                    if ($imageFail) {
                        $URL .= '&return=warning1';
                        header("Location: {$URL}");
                    } else {
                        $URL .= '&return=success0';
                        header("Location: {$URL}");
                    }
                }
            }
        }
    }
}
