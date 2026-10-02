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
use TawasulOS\Services\Format;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Activities\ActivityGateway;
use TawasulOS\Domain\Activities\ActivitySlotGateway;
use TawasulOS\Domain\Activities\ActivityStaffGateway;
use TawasulOS\Domain\Activities\ActivityPhotoGateway;
use TawasulOS\Contracts\Filesystem\FileHandler;
use TawasulOS\FileUploader;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['description' => 'HTML']);

$tawasulActivityID = $_POST['tawasulActivityID'] ?? '';
$search = $_POST['search'] ?? '';
$tawasulSchoolYearTermID = $_POST['tawasulSchoolYearTermID'] ?? '';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module') . "/activities_manage_edit.php&tawasulActivityID=$tawasulActivityID&search=$search&tawasulSchoolYearTermID=$tawasulSchoolYearTermID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulActivities/activities_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulActivityID specified
    $activityGateway = $container->get(ActivityGateway::class);
    $activityPhotoGateway = $container->get(ActivityPhotoGateway::class);

    if (!$activityGateway->exists($tawasulActivityID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        //Validate Inputs
        $name = $_POST['name'] ?? '';
        $provider = $_POST['provider'] ?? '';
        $active = $_POST['active'] ?? '';
        $registration = $_POST['registration'] ?? '';
        $dateType = $_POST['dateType'] ?? '';

        if ($dateType == 'Term') {
            $tawasulSchoolYearTermIDList = $_POST['tawasulSchoolYearTermIDList'] ?? [];
            $tawasulSchoolYearTermIDList = implode(',', $tawasulSchoolYearTermIDList);
        } elseif ($dateType == 'Date') {
            $listingStart = Format::dateConvert($_POST['listingStart'] ?? '');
            $listingEnd = Format::dateConvert($_POST['listingEnd'] ?? '');
            $programStart = Format::dateConvert($_POST['programStart'] ?? '');
            $programEnd = Format::dateConvert($_POST['programEnd'] ?? '');
        }

        $tawasulYearGroupIDList = $_POST['tawasulYearGroupIDList'] ?? [];
        $tawasulYearGroupIDList = implode(',', $tawasulYearGroupIDList);

        $maxParticipants = $_POST['maxParticipants'] ?? '';

        $settingGateway = $container->get(SettingGateway::class);
        $paymentMethod = $settingGateway->getSettingByScope('Activities', 'payment');
        if ($paymentMethod == 'None' || $paymentMethod == 'Single') {
            $paymentOn = false;
            $payment = null;
            $paymentType = null;
            $paymentFirmness = null;
            $paymentDescription = null;
        } else {
            $paymentOn = true;
            $payment = $_POST['payment'] ?? '';
            $paymentType = $_POST['paymentType'] ?? '';
            $paymentFirmness = $_POST['paymentFirmness'] ?? '';
            $paymentDescription = $_POST['paymentDescription'] ?? '';
        }
        $description = $_POST['description'] ?? '';

        if ($dateType == '' || $name == '' || $provider == '' || $active == '' || $registration == '' || $maxParticipants == '' || ($paymentOn && ($payment == '' || $paymentType == '' || $paymentFirmness == '')) || ($dateType == 'Date' && ($listingStart == '' || $listingEnd == '' || $programStart == '' || $programEnd == ''))) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            $partialFail = false;

            $activitySlotGateway = $container->get(ActivitySlotGateway::class);
            $activitySlots = [];

            $timeSlotOrder = $_POST['order'] ?? [];
            foreach ($timeSlotOrder as $order) {
                $slot = $_POST['timeSlots'][$order];

                if (empty($slot['tawasulDaysOfWeekID']) || empty($slot['timeStart']) || empty('timeEnd')) {
                    continue;
                }

                //If start is after end, swap times.
                if ($slot['timeStart'] > $slot['timeEnd']) {
                    $temp = $slot['timeStart'];
                    $slot['timeStart'] = $slot['timeEnd'];
                    $slot['timeEnd'] = $temp;
                }

                $slot['tawasulActivityID'] = $tawasulActivityID;

                $type = $slot['location'] ?? 'Internal';
                if ($type == 'Internal') {
                    $slot['locationExternal'] = '';
                } else {
                    $slot['tawasulSpaceID'] = null;
                }

                unset($slot['location']);

                if (!empty($slot['tawasulActivitySlotID'])) {
                    $tawasulActivitySlotID = $slot['tawasulActivitySlotID'];
                    $activitySlotGateway->update($tawasulActivitySlotID, $slot);
                } else {
                    $tawasulActivitySlotID = $activitySlotGateway->insert($slot);
                }

                $activitySlots[] = str_pad($tawasulActivitySlotID, 10, 0, STR_PAD_LEFT);
            }

            $activitySlotGateway->deleteActivitySlotsNotInList($tawasulActivityID, $activitySlots);

            // Scan through staff
            $staff = $_POST['staff'] ?? [];
            $role = $_POST['role'] ?? 'Other';

            // make sure that staff is an array
            if (!is_array($staff)) {
                $staff = [strval($staff)];
            }

            $activityStaffGateway = $container->get(ActivityStaffGateway::class);
            if (count($staff) > 0) {
                foreach ($staff as $staffPersonID) {
                    //Check to see if person is already registered in this activity
                    $resultGuest = $activityStaffGateway->selectActivityStaffByID($tawasulActivityID, $staffPersonID);

                    if ($resultGuest->isEmpty()) {
                        if (!$activityStaffGateway->insertActivityStaff($tawasulActivityID, $staffPersonID, $role)) {
                            $partialFail = true;
                        }
                    }
                }
            }

            $fileUploader = $container->get(FileUploader::class);
            $fileUploader->getFileExtensions('Graphics/Design');
    
            // Update the photos
            $photos = $_POST['photos'] ?? [];
            $photoOrder = $_POST['photoOrder'] ?? [];
            $photoSequence = !empty($photoOrder) ? max($photoOrder) + 1 : 0;
            $photoIDs = [];

            foreach ($photos as $index => $photo) {

                $photoData = [
                    'tawasulActivityID' => $tawasulActivityID,
                    'filePath'           => $photo['filePath'] ?? '',
                    'caption'            => $photo['caption'] ?? '',
                    'sequenceNumber'     => array_search($index, $photoOrder) ?? false,
                ];

                $fileMetaData = null;
                if (!empty($_FILES['photos']['tmp_name'][$index]['fileUpload'])) {
                    $file = [
                        'name' => $_FILES['photos']['name'][$index]['fileUpload'] ?? '',
                        'type' => $_FILES['photos']['type'][$index]['fileUpload'] ?? '',
                        'tmp_name' => $_FILES['photos']['tmp_name'][$index]['fileUpload'] ?? '',
                        'error' => $_FILES['photos']['error'][$index]['fileUpload'] ?? '',
                        'size' => $_FILES['photos']['size'][$index]['fileUpload'] ?? '',
                    ];
            
                    // Upload the file, return the /uploads relative path
                    $activityName = str_replace(' ', '-', $name);
                    $photoData['filePath'] = $fileUploader->uploadAndResizeImage($file, $activityName, 1024, 80);
                }

                if (empty($photoData['filePath'])) {
                    $partialFail = true;
                    continue;
                } else {
                    $fileMetaData = $fileUploader->getFileMetaData($photoData['filePath']);
                }

                if ($photoData['sequenceNumber'] === false) {
                    $photoData['sequenceNumber'] = $photoSequence;
                    $photoSequence++;
                }

                $tawasulActivityPhotoID = $photo['tawasulActivityPhotoID'] ?? '';

                if (!empty($tawasulActivityPhotoID)) {
                    $partialFail &= !$activityPhotoGateway->update($tawasulActivityPhotoID, $photoData);
                } else {
                    $tawasulActivityPhotoID = $activityPhotoGateway->insert($photoData);
                    $partialFail &= !$tawasulActivityPhotoID;
                }

                $photoIDs[] = str_pad($tawasulActivityPhotoID, 12, '0', STR_PAD_LEFT);

                // Record file tracking
                if (!empty($fileMetaData) && !empty($tawasulActivityPhotoID)) {
                    $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulActivityPhoto', $tawasulActivityPhotoID, 'filePath');
                    
                    if (empty($tawasulFileID)) {
                        $partialFail = true;
                    }
                }
            }

            // Remove photos that have been deleted from the filesystem
            $cleanupPhotos = $activityPhotoGateway->selectPhotosNotInList($tawasulActivityID, $photoIDs)->fetchAll();
            foreach ($cleanupPhotos as $photo) {
                $photoPath = $session->get('absolutePath').'/'.$photo['filePath'];
                if (!empty($photo['filePath']) && file_exists($photoPath)) {
                    $deleted = $container->get(FileHandler::class)->deleteFile('tawasulActivityPhoto', $photo['tawasulActivityPhotoID'], 'filePath');
                }

                $activityPhotoGateway->delete($photo['tawasulActivityPhotoID']);
            }

            //Write to database
            $type = $_POST['type'] ?? '';

            $data = [
                'tawasulSchoolYearID'       => $session->get('tawasulSchoolYearID'),
                'tawasulActivityCategoryID' => $_POST['tawasulActivityCategoryID'] ?? '',
                'name'                     => $name,
                'provider'                 => $provider,
                'type'                     => $type,
                'active'                   => $active,
                'registration'             => $registration,
                'tawasulYearGroupIDList'    => $tawasulYearGroupIDList,
                'maxParticipants'          => $maxParticipants,
                'payment'                  => $payment,
                'paymentType'              => $paymentType,
                'paymentFirmness'          => $paymentFirmness,
                'paymentDescription'       => $paymentDescription,
                'description'              => $description
            ];

            if ($dateType == 'Date') {
                $data['tawasulSchoolYearTermIDList'] = '';
                $data['listingStart'] = $listingStart;
                $data['listingEnd'] = $listingEnd;
                $data['programStart'] = $programStart;
                $data['programEnd'] = $programEnd;
            } else {
                $data['tawasulSchoolYearTermIDList'] = $tawasulSchoolYearTermIDList;
                $data['listingStart'] = null;
                $data['listingEnd'] = null;
                $data['programStart'] = null;
                $data['programEnd'] = null;
            }

            if (!$activityGateway->update($tawasulActivityID, $data)) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                $return = $partialFail ? 'error3' : 'success0';
                $URL .= "&return=$return";
                header("Location: {$URL}");
            }
        }
    }
}
