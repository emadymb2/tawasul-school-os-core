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
use TawasulOS\Domain\System\CustomFieldGateway;
use TawasulOS\Domain\User\PersonalDocumentGateway;
use TawasulOS\Domain\User\PersonPhotoGateway;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\FileUploader;
use TawasulOS\Contracts\Filesystem\FileHandler;


require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/TawasulSystemAdmin/file_upload.php&step=3';

if (isActionAccessible($guid, $connection2, '/modules/TawasulSystemAdmin/file_upload.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $type = $_POST['type'] ?? '';
    $identifier = $_POST['identifier'] ?? '';
    $zipFile = $_POST['file'] ?? '';
    $fileSeparator = $_POST['fileSeparator'] ?? '';
    $fileSection = $_POST['fileSection'] ?? '';
    $tawasulPersonalDocumentTypeID = $_POST['tawasulPersonalDocumentTypeID'] ?? '';
    $tawasulCustomFieldID = $_POST['tawasulCustomFieldID'] ?? '';
    $overwrite = $_POST['overwrite'] ?? 'N';
    $deleteFiles = $_POST['deleteFiles'] ?? 'N';
    $zoom = $_POST['zoom'] ?? '100';
    $focalX = $_POST['focalX'] ?? '50';
    $focalY = $_POST['focalY'] ?? '50';

    if (empty($identifier) || empty($type) || empty($zipFile)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    if (($type == 'customFields' && empty($tawasulCustomFieldID)) || ($type == 'personalDocuments' && empty($tawasulPersonalDocumentTypeID))) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    if ($identifier != 'username' && $identifier != 'studentID') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $absolutePath = $session->get('absolutePath');
    if (!is_file($absolutePath.'/'.$zipFile)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $userGateway = $container->get(UserGateway::class);
    $customFieldGateway = $container->get(CustomFieldGateway::class);
    $personalDocumentGateway = $container->get(PersonalDocumentGateway::class);
    
    $fileHandler = $container->get(FileHandler::class);
    $fileUploader = $container->get(FileUploader::class);
    $files = $fileUploader->uploadFromZIP($absolutePath.'/'.$zipFile);

    $partialFail = false;
    $count = 0;

    foreach ($files as $file) {
        // Optionally split the filenames by a separator character
        if (!empty($fileSeparator) && !empty($fileSection)) {
            $fileParts = explode($fileSeparator, mb_strrchr($file['originalName'], '.', true));
            $identifierValue = $fileParts[$fileSection-1] ?? '';
        } else {
            $identifierValue = mb_strrchr($file['originalName'], '.', true);
        }

        // Get the user data by identifier
        $userData = $userGateway->selectBy([$identifier => $identifierValue], [
            'tawasulPersonID',
            'username',
            'image_240',
        ])->fetch();

        if (empty($identifierValue) || empty($userData) || empty($file['relativePath'])) {
            $partialFail = true;
            continue;
        }

        // File tracking state
        $updateBackupPhoto = false;
        $fileTrackingTable = $fileTrackingID = $fileTrackingColumn = null;

        // Check if there is an existing value for this upload
        if ($type == 'customFields') {
            $fields = $customFieldGateway->getCustomFieldDataByUser($tawasulCustomFieldID, $userData['tawasulPersonID']);
            $existingFile = $fields[$tawasulCustomFieldID] ?? '';
        } elseif ($type == 'personalDocuments') {
            $document = $personalDocumentGateway->getPersonalDocumentDataByID($tawasulPersonalDocumentTypeID, 'tawasulPerson', $userData['tawasulPersonID']);
            $existingFile = $document['filePath'] ?? '';
        } else {
            $existingFile = $userData['image_240'];
            $updateBackupPhoto = true;
        }

        // Optionally overwrite and delete exiting files
        if (!empty($existingFile) && $overwrite == 'Y' && $deleteFiles == 'Y') {
            unlink($absolutePath.'/'.$existingFile);
        }
        
        // Skip uploading files if the file exists and overwrite is not on
        if (!empty($existingFile) && is_file($absolutePath.'/'.$existingFile) && $overwrite == 'N') {
            unlink($file['absolutePath']);
            $updateBackupPhoto = false;
            continue;
        }

        // Update the files for this user
        if ($type == 'customFields') {
            $fields[$tawasulCustomFieldID] = $file['relativePath'];
            $updated = $customFieldGateway->updateCustomFieldDataByUser($tawasulCustomFieldID, $userData['tawasulPersonID'], $fields);
            $contextInfo = $customFieldGateway->getContextTableByPerson($tawasulCustomFieldID, $userData['tawasulPersonID']);
            $fileTrackingTable = $contextInfo['foreignTable'] ?? null;
            $fileTrackingID = $contextInfo['foreignTableID'] ?? null;
            $fileTrackingColumn = "fields[{$tawasulCustomFieldID}]";

        } elseif ($type == 'personalDocuments') {
            if (empty($document['tawasulPersonalDocumentID'])) {
                unlink($file['absolutePath']);
                $partialFail = true;
                continue;
            }
            $updated = $personalDocumentGateway->update($document['tawasulPersonalDocumentID'], [
                'filePath'              => $file['relativePath'],
                'tawasulPersonIDUpdater' => $session->get('tawasulPersonID'),
                'timestamp'             => date('Y-m-d H:i:s'),
            ]);
            $fileTrackingTable = 'tawasulPersonalDocument';
            $fileTrackingID = $document['tawasulPersonalDocumentID'];
            $fileTrackingColumn = 'filePath';

        } else {
            // Rename the file to match the identifier for this user, then resize & crop, and upload
            $renameFilename = $userData['username'].'.'.$file['extension'];
            $renameFilePath = str_replace($file['filename'], $renameFilename, $file['absolutePath']);

            rename($file['absolutePath'], $renameFilePath);

            $file['absolutePath'] = $fileUploader->resizeImage($renameFilePath, $renameFilePath, 480, 100, $zoom, $focalX, $focalY);
            $file['relativePath'] = str_replace($file['filename'], $renameFilename, $file['relativePath']);
            
            $updated = $userGateway->update($userData['tawasulPersonID'], [
                'image_240' => $file['relativePath'],
            ]);
            $fileTrackingTable = 'tawasulPerson';
            $fileTrackingID = $userData['tawasulPersonID'];
            $fileTrackingColumn = 'image_240';
        }

        if ($updated) {
            $count++;

            // Record file in the central file tracking system
            if (!empty($fileTrackingTable) && !empty($fileTrackingID)) {
                $fileMetaData = $fileUploader->getFileMetaData($file['relativePath']);
                if (!empty($fileMetaData)) {
                    $tawasulFileID = $fileHandler->recordFileUpload($fileMetaData, $fileTrackingTable, $fileTrackingID, $fileTrackingColumn);
                    if (empty($tawasulFileID)) {
                        $partialFail = true;
                    }
                }
            }

            // For user photos, also update/insert into the backup photo table
            if ($updateBackupPhoto && !empty($file['relativePath'])) {
                $personPhotoGateway = $container->get(PersonPhotoGateway::class);
                $photoUpdated = $personPhotoGateway->insertAndUpdate([
                    'tawasulPersonID' => $userData['tawasulPersonID'],
                    'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'),
                    'personImage' => $file['relativePath'],
                    'tawasulPersonIDCreated' => $session->get('tawasulPersonID'),
                ], [
                    'personImage' => $file['relativePath'],
                    'tawasulPersonIDCreated' => $session->get('tawasulPersonID'),
                ]);
                
                $partialFail = $partialFail || !$photoUpdated;
            }
        }
    }

    unlink($absolutePath.'/'.$zipFile);

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URL}&imported={$count}");
}
