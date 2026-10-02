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

//TawasulOS system-wide includes

use TawasulOS\Http\Url;
use TawasulOS\Domain\User\PersonPhotoGateway;
use TawasulOS\Contracts\Filesystem\FileHandler;

require_once __DIR__ . '/tawasul.php';

$tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
$URL = Url::fromRoute();

//Proceed!
//Check if planner specified
if ($tawasulPersonID == '' or $tawasulPersonID != $session->get('tawasulPersonID') or $_FILES['file1']['tmp_name'] == '') {
    header("Location: {$URL->withReturn('error1')}");
    exit();
} else {
    try {
        $data = array('tawasulPersonID' => $tawasulPersonID);
        $sql = 'SELECT * FROM tawasulPerson WHERE tawasulPersonID=:tawasulPersonID';
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        header("Location: {$URL->withReturn('error2')}");
        exit();
    }

    if ($result->rowCount() != 1) {
        header("Location: {$URL->withReturn('error2')}");
        exit();
    } else {
        $attachment1 = null;
        $fileMetaData = null;
        if (!empty($_FILES['file1']['tmp_name'])) {
            $fileUploader = new TawasulOS\FileUploader($pdo, $session);
            $fileUploader->setFileSuffixType(TawasulOS\FileUploader::FILE_SUFFIX_INCREMENTAL);

            $file = $_FILES['file1'] ?? null;

            // Upload the file, return the /uploads relative path
            $attachment1 = $fileUploader->uploadFromPost($file, $session->get('username').'_240');

            if (empty($attachment1)) {
                header("Location: {$URL->withReturn('warning1')}");
                exit();
            }

            // Capture file metadata for tracking
            $fileMetaData = $fileUploader->getFileMetaData($attachment1);
        }

        $path = $session->get('absolutePath');

        //Check for reasonable image
        $size = getimagesize($path.'/'.$attachment1);
        $width = $size[0];
        $height = $size[1];
        if ($width < 240 or $height < 320) {
            header("Location: {$URL->withReturn('error6')}");
            exit();
        } elseif ($width > 480 or $height > 640) {
            header("Location: {$URL->withReturn('error6')}");
            exit();
        } elseif (($width / $height) < 0.60 or ($width / $height) > 0.8) {
            header("Location: {$URL->withReturn('error6')}");
            exit();
        } else {
            //UPDATE
            try {
                $data = array('tawasulPersonID' => $tawasulPersonID, 'attachment1' => $attachment1);
                $sql = 'UPDATE tawasulPerson SET image_240=:attachment1 WHERE tawasulPersonID=:tawasulPersonID';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                header("Location: {$URL->withReturn('error2')}");
                exit();
            }

            
            if (!empty($attachment1)) {
                $personPhotoGateway = $container->get(PersonPhotoGateway::class);
                // Update/insert the photo into the backup table
                $photoUpdated = $personPhotoGateway->insertAndUpdate([
                    'tawasulPersonID' => $tawasulPersonID,
                    'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'),
                    'personImage' => $attachment1,
                    'tawasulPersonIDCreated' => $session->get('tawasulPersonID'),
                ], [
                    'personImage' => $attachment1,
                    'tawasulPersonIDCreated' => $session->get('tawasulPersonID'),
                ]);

                // Record file tracking 
                if (!empty($fileMetaData) && !empty($tawasulPersonID)) {
                    $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulPerson', $tawasulPersonID, 'image_240');
                    
                    if (empty($tawasulFileID)) {
                        header("Location: {$URL->withReturn('warning1')}");
                        exit();
                    }
                }
            }

            //Update session variables
            $session->set('image_240', $attachment1);

            //Clear cusotm sidebar
            $session->remove('index_customSidebar.php');

            header("Location: {$URL->withReturn('success0')}");
        }
    }
}
