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
use TawasulOS\Domain\Departments\DepartmentGateway;
use TawasulOS\Contracts\Filesystem\FileHandler;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['blurb' => 'HTML', 'url*' => 'URL']);

//Module includes
include './moduleFunctions.php';

$tawasulDepartmentID = $_GET['tawasulDepartmentID'] ?? '';
$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/department_edit.php&tawasulDepartmentID=$tawasulDepartmentID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulDepartments/department_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    if (empty($_POST)) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Validate Inputs
        $blurb = $_POST['blurb'] ?? '';

        if ($tawasulDepartmentID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            //Check access to specified course
            try {

                $result = $container->get(DepartmentGateway::class)->selectBy(['tawasulDepartmentID' => $tawasulDepartmentID]);
                                
            } catch (PDOException $e) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            if ($result->rowCount() != 1) {
                $URL .= '&return=error1';
                header("Location: {$URL}");
            } else {
                //Get role within learning area
                $role = getRole($session->get('tawasulPersonID'), $tawasulDepartmentID, $connection2);

                if ($role != 'Coordinator' and $role != 'Assistant Coordinator' and $role != 'Teacher (Curriculum)' and $role != 'Director' and $role != 'Manager') {
                    $URL .= '&return=error0';
                    header("Location: {$URL}");
                } else {
                    //Scan through resources
                    $partialFail = false;
                    for ($i = 1; $i < 4; ++$i) {
                        $resourceName = $_POST["name$i"] ?? '';
                        $resourceType = $_POST["type$i"] ?? '';
                        $resourceURL = $_POST["url$i"] ?? '';

                        if ($resourceName != '' and $resourceType != '' and ($resourceType == 'File' or $resourceType == 'Link')) {
                            if (($resourceType == 'Link' and $resourceURL != '') or ($resourceType == 'File' and !empty($_FILES['file'.$i]['tmp_name']))) {
                                if ($resourceType == 'Link') {
                                    try {
                                        $data = array('tawasulDepartmentID' => $tawasulDepartmentID, 'resourceType' => $resourceType, 'resourceName' => $resourceName, 'resourceURL' => $resourceURL);
                                        $sql = 'INSERT INTO tawasulDepartmentResource SET tawasulDepartmentID=:tawasulDepartmentID, type=:resourceType, name=:resourceName, url=:resourceURL';
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                    } catch (PDOException $e) {
                                        $partialFail = true;
                                    }
                                } elseif ($resourceType == 'File') {
                                    $fileUploader = new TawasulOS\FileUploader($pdo, $session);
                                    $fileMetaData = null;

                                    // Handle the attached file, if there is one
                                    if (!empty($_FILES['file'.$i]['tmp_name'])) {
                                        $file = (isset($_FILES['file'.$i]))? $_FILES['file'.$i] : null;

                                        // Upload the file, return the /uploads relative path
                                        $attachment = $fileUploader->uploadFromPost($file, $resourceName);

                                        if (empty($attachment)) {
                                            $URL .= '&return=warning1';
                                            header("Location: {$URL}");
                                            exit();
                                        } else {
                                            $fileMetaData = $fileUploader->getFileMetaData($attachment);

                                            try {
                                                $data = array('tawasulDepartmentID' => $tawasulDepartmentID, 'resourceType' => $resourceType, 'resourceName' => $resourceName, 'attachment' => $attachment);
                                                $sql = 'INSERT INTO tawasulDepartmentResource SET tawasulDepartmentID=:tawasulDepartmentID, type=:resourceType, name=:resourceName, url=:attachment';
                                                $result = $connection2->prepare($sql);
                                                $result->execute($data);
                                            } catch (PDOException $e) {
                                                $partialFail = true;
                                            }

                                            $tawasulDepartmentResourceID = $connection2->lastInsertID();

                                            // Record file tracking
                                            if (!empty($fileMetaData) && !empty($tawasulDepartmentResourceID)) {
                                                $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulDepartmentResource', $tawasulDepartmentResourceID, 'url');

                                                if (empty($tawasulFileID)) {
                                                    $partialFail = true;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    //Write to database
                    try {
                        $data = array('blurb' => $blurb, 'tawasulDepartmentID' => $tawasulDepartmentID);
                        $sql = 'UPDATE tawasulDepartment SET blurb=:blurb WHERE tawasulDepartmentID=:tawasulDepartmentID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    if ($partialFail == true) {
                        $URL .= '&return=error3';
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
