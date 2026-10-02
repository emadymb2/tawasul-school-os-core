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

$tawasulFileExtensionID = $_GET['tawasulFileExtensionID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/fileExtensions_manage_edit.php&tawasulFileExtensionID='.$tawasulFileExtensionID;

if (isActionAccessible($guid, $connection2, '/modules/TawasulSchoolAdmin/fileExtensions_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulFileExtensionID specified
    if ($tawasulFileExtensionID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        try {
            $data = array('tawasulFileExtensionID' => $tawasulFileExtensionID);
            $sql = 'SELECT * FROM tawasulFileExtension WHERE tawasulFileExtensionID=:tawasulFileExtensionID';
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
            $extension = strtolower($_POST['extension']);
            $name = $_POST['name'] ?? '';
            $type = $_POST['type'] ?? '';

            $illegalFileExtensions = TawasulOS\FileUploader::getIllegalFileExtensions();

            if ($extension == '' or $name == '' or $type == '' or in_array($extension, $illegalFileExtensions)) {
                $URL .= '&return=error3';
                header("Location: {$URL}");
            } else {
                //Check unique inputs for uniquness
                try {
                    $data = array('name' => $name, 'tawasulFileExtensionID' => $tawasulFileExtensionID);
                    $sql = 'SELECT * FROM tawasulFileExtension WHERE (name=:name) AND NOT tawasulFileExtensionID=:tawasulFileExtensionID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($result->rowCount() > 0) {
                    $URL .= '&return=error3';
                    header("Location: {$URL}");
                } else {
                    //Write to database
                    try {
                        $data = array('extension' => $extension, 'name' => $name, 'type' => $type, 'tawasulFileExtensionID' => $tawasulFileExtensionID);
                        $sql = 'UPDATE tawasulFileExtension SET extension=:extension, name=:name, type=:type WHERE tawasulFileExtensionID=:tawasulFileExtensionID';
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
