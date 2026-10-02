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

use TawasulOS\FileUploader;
use TawasulOS\Services\Format;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Domain\Students\MedicalGateway;
use TawasulOS\Domain\DataUpdater\MedicalUpdateGateway;
use TawasulOS\Data\Validator;
use TawasulOS\Contracts\Filesystem\FileHandler;
use TawasulOS\Domain\DataUpdater\MedicalConditionUpdateGateway;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/data_medical.php&tawasulPersonID=$tawasulPersonID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulDataUpdater/data_medical.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $address, $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Check if tawasulPersonID specified
        if ($tawasulPersonID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            //Check access to person
            $checkCount = 0;
            if ($highestAction == 'Update Medical Data_any') {
                $URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/TawasulDataUpdater/data_medical.php&tawasulPersonID='.$tawasulPersonID;

                try {
                    $dataSelect = array();
                    $sqlSelect = "SELECT surname, preferredName, tawasulPerson.tawasulPersonID FROM tawasulPerson WHERE status='Full' ORDER BY surname, preferredName";
                    $resultSelect = $connection2->prepare($sqlSelect);
                    $resultSelect->execute($dataSelect);
                } catch (PDOException $e) {
                    $URL .= "&return=error2$params";
                    header("Location: {$URL}");
                    exit();
                }
                $checkCount = $resultSelect->rowCount();
            } else {
                $URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/TawasulDataUpdater/data_updates.php&tawasulPersonID='.$tawasulPersonID;

                try {
                    $dataCheck = array('tawasulPersonID' => $session->get('tawasulPersonID'));
                    $sqlCheck = "SELECT tawasulFamilyAdult.tawasulFamilyID, name FROM tawasulFamilyAdult JOIN tawasulFamily ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID AND childDataAccess='Y' ORDER BY name";
                    $resultCheck = $connection2->prepare($sqlCheck);
                    $resultCheck->execute($dataCheck);
                } catch (PDOException $e) {
                    $URL .= "&return=error2$params";
                    header("Location: {$URL}");
                    exit();
                }
                while ($rowCheck = $resultCheck->fetch()) {
                    try {
                        $dataCheck2 = array('tawasulFamilyID' => $rowCheck['tawasulFamilyID'], 'tawasulFamilyID2' => $rowCheck['tawasulFamilyID']);
                        $sqlCheck2 = '(SELECT surname, preferredName, tawasulPerson.tawasulPersonID, tawasulFamilyID FROM tawasulFamilyChild JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulFamilyID=:tawasulFamilyID) UNION (SELECT surname, preferredName, tawasulPerson.tawasulPersonID, tawasulFamilyID FROM tawasulFamilyAdult JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulFamilyID=:tawasulFamilyID2)';
                        $resultCheck2 = $connection2->prepare($sqlCheck2);
                        $resultCheck2->execute($dataCheck2);
                    } catch (PDOException $e) {
                        $URL .= "&return=error2$params";
                        header("Location: {$URL}");
                        exit();
                    }
                    while ($rowCheck2 = $resultCheck2->fetch()) {
                        if ($tawasulPersonID == $rowCheck2['tawasulPersonID']) {
                            ++$checkCount;
                        }
                    }
                }
            }
            if ($checkCount < 1) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                // Proceed!
                $tawasulPersonMedicalID = $_POST['tawasulPersonMedicalID'] ?? null;
                $data = [
                    'tawasulPersonMedicalID'     => $tawasulPersonMedicalID,
                    'tawasulPersonID'            => $tawasulPersonID,
                    'longTermMedication'        => $_POST['longTermMedication'] ?? 'N',
                    'longTermMedicationDetails' => $_POST['longTermMedicationDetails'] ?? '',
                    'comment'                   => $_POST['comment'] ?? '',
                ];

                // Get medical form fields
                $medicalGateway = $container->get(MedicalGateway::class);
                $values = $medicalGateway->getByID($tawasulPersonMedicalID);

                // COMPARE VALUES: Has the data changed?
                $dataChanged = empty($values);
                foreach ($values as $key => $value) {
                    if (!isset($data[$key])) continue; // Skip fields we don't plan to update
                    if (empty($data[$key]) && empty($value)) continue; // Nulls, false and empty strings should cause no change

                    if ($data[$key] != $value) {
                        $dataChanged = true;
                    }
                }

                // CUSTOM FIELDS
                $customRequireFail = false;
                $fields = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('Medical Form', ['dataUpdater' => 1], $customRequireFail);

                // Check for data changed
                $existingFields = isset($values['fields']) ? json_decode($values['fields'], true) : [];
                $existingFields = is_array($existingFields) ? $existingFields : []; // make sure this is an array
                $newFields = json_decode($fields, true);
                $newFields = is_array($newFields) ? $newFields : []; // make sure this is an array
                foreach ($newFields as $key => $fieldValue) {
                    if (empty($existingFields[$key]) && empty($fieldValue)) continue; // Nulls, false and empty strings should cause no change

                    if ((empty($existingFields[$key]) && !empty($fieldValue)) || $existingFields[$key] != $fieldValue) {
                        $dataChanged = true;
                    }
                }

                // Write to database
                $existing = $_POST['existing'] ?? 'N';
                $data['tawasulSchoolYearID'] = $session->get('tawasulSchoolYearID');
                $data['tawasulPersonIDUpdater'] = $session->get('tawasulPersonID');
                $data['timestamp'] = date('Y-m-d H:i:s');
                $data['fields'] = $fields;

                $oldUpdateRecord = null;
                if ($existing != 'N') {
                    // Fetch the old update record for comparison
                    $tawasulPersonMedicalUpdateID = $existing;
                    $oldUpdateRecord = $container->get(MedicalUpdateGateway::class)->getByID($tawasulPersonMedicalUpdateID);

                    $data['tawasulPersonMedicalUpdateID'] = $tawasulPersonMedicalUpdateID;
                    $sql = 'UPDATE tawasulPersonMedicalUpdate SET tawasulSchoolYearID=:tawasulSchoolYearID, tawasulPersonMedicalID=:tawasulPersonMedicalID, tawasulPersonID=:tawasulPersonID, longTermMedication=:longTermMedication, longTermMedicationDetails=:longTermMedicationDetails, fields=:fields, comment=:comment, tawasulPersonIDUpdater=:tawasulPersonIDUpdater, timestamp=:timestamp WHERE tawasulPersonMedicalUpdateID=:tawasulPersonMedicalUpdateID';
                    $pdo->update($sql, $data);
                } else {
                    $sql = 'INSERT INTO tawasulPersonMedicalUpdate SET tawasulSchoolYearID=:tawasulSchoolYearID, tawasulPersonMedicalID=:tawasulPersonMedicalID, tawasulPersonID=:tawasulPersonID, longTermMedication=:longTermMedication, longTermMedicationDetails=:longTermMedicationDetails, fields=:fields, comment=:comment, tawasulPersonIDUpdater=:tawasulPersonIDUpdater, timestamp=:timestamp';
                    $tawasulPersonMedicalUpdateID = $pdo->insert($sql, $data);
                }

                // Manage custom field file uploads
                if (!empty($fields) && !empty($tawasulPersonMedicalUpdateID)) {
                    $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('Medical Form', ['dataUpdater' => true], $fields, 'tawasulPersonMedicalUpdate', $tawasulPersonMedicalUpdateID, $oldUpdateRecord['fields'] ?? null);
                }
                
                // Update existing medical conditions
                $partialFail = false;
                $count = $_POST['count'] ?? 0;

                for ($i = 0; $i < $count; ++$i) {
                    // Get the values of the current condition
                    $tawasulPersonMedicalConditionID = $_POST["tawasulPersonMedicalConditionID$i"] ?? null;
                    $condition = $medicalGateway->getMedicalConditionByID($tawasulPersonMedicalConditionID);
                    if (empty($condition)) {
                        $dataChanged = true;
                    }

                    $data = [
                        'tawasulPersonMedicalID' => $tawasulPersonMedicalID,
                        'tawasulPersonMedicalUpdateID' => $tawasulPersonMedicalUpdateID,
                        'name'                  => $_POST["name$i"] ?? '',
                        'tawasulAlertLevelID'    => $_POST["tawasulAlertLevelID$i"] ?? '',
                        'triggers'              => $_POST["triggers$i"] ?? '',
                        'reaction'              => $_POST["reaction$i"] ?? '',
                        'response'              => $_POST["response$i"] ?? '',
                        'medication'            => $_POST["medication$i"] ?? '',
                        'lastEpisode'           => !empty($_POST["lastEpisode$i"]) ? Format::dateConvert($_POST["lastEpisode$i"]) : null,
                        'lastEpisodeTreatment'  => $_POST["lastEpisodeTreatment$i"] ?? '',
                        'comment'               => $_POST["commentCond$i"] ?? '',
                        'attachment'            => $_POST["attachment$i"] ?? null,
                        'tawasulPersonIDUpdater' => $session->get('tawasulPersonID'),
                    ];

                    $fileMetaData = null;
                    if (!empty($_FILES["attachment$i"]['tmp_name'])) {
                        // Upload the file, return the /uploads relative path
                        $fileUploader = new FileUploader($pdo, $session);
                        $data['attachment'] = $fileUploader->uploadFromPost($_FILES["attachment$i"]);

                        if (empty($data['attachment'])) {
                            $partialFail = true;
                        } else {
                            $fileMetaData = $fileUploader->getFileMetaData($data['attachment']);
                        }
                    } else {
                        // Remove the attachment if it has been deleted, otherwise retain the original value
                        $data['attachment'] = empty($_POST["attachment$i"]) ? null : $condition['attachment'];
                    }

                    // Check for values that have changed
                    foreach ($condition as $key => $value) {
                        if (!isset($data[$key])) continue; // Skip fields we don't plan to update
                        if (empty($data[$key]) && empty($value)) continue; // Nulls, false and empty strings should cause no change

                        if ($data[$key] != $value) {
                            $dataChanged = true;
                        }
                    }

                    $data['timestamp'] = date('Y-m-d H:i:s');

                    // Get old update record for deletion check
                    $oldUpdateRecord = null;
                    
                    if ($existing != 'N' && !empty($_POST["tawasulPersonMedicalConditionUpdateID$i"])) {
                        $tawasulPersonMedicalConditionUpdateID = $_POST["tawasulPersonMedicalConditionUpdateID$i"];
                        $oldUpdateRecord = $container->get(MedicalConditionUpdateGateway::class)->getByID($tawasulPersonMedicalConditionUpdateID);

                        $data['tawasulPersonMedicalConditionUpdateID'] = $tawasulPersonMedicalConditionUpdateID ?? '';
                        $sql = 'UPDATE tawasulPersonMedicalConditionUpdate SET tawasulPersonMedicalUpdateID=:tawasulPersonMedicalUpdateID, tawasulPersonMedicalID=:tawasulPersonMedicalID, name=:name, tawasulAlertLevelID=:tawasulAlertLevelID, triggers=:triggers, reaction=:reaction, response=:response, medication=:medication, lastEpisode=:lastEpisode, lastEpisodeTreatment=:lastEpisodeTreatment, comment=:comment, attachment=:attachment, tawasulPersonIDUpdater=:tawasulPersonIDUpdater, timestamp=:timestamp WHERE tawasulPersonMedicalConditionUpdateID=:tawasulPersonMedicalConditionUpdateID';
                        $updated = $pdo->update($sql, $data);
                    } else {
                        $data['tawasulPersonMedicalConditionID'] = $tawasulPersonMedicalConditionID;
                        $sql = 'INSERT INTO tawasulPersonMedicalConditionUpdate SET tawasulPersonMedicalUpdateID=:tawasulPersonMedicalUpdateID, tawasulPersonMedicalConditionID=:tawasulPersonMedicalConditionID, tawasulPersonMedicalID=:tawasulPersonMedicalID, name=:name, tawasulAlertLevelID=:tawasulAlertLevelID, triggers=:triggers, reaction=:reaction, response=:response, medication=:medication, lastEpisode=:lastEpisode, lastEpisodeTreatment=:lastEpisodeTreatment, comment=:comment, attachment=:attachment, tawasulPersonIDUpdater=:tawasulPersonIDUpdater, timestamp=:timestamp';
                        $tawasulPersonMedicalConditionUpdateID = $pdo->insert($sql, $data);
                    }

                     // Record file tracking
                    if (!empty($fileMetaData) && !empty($tawasulPersonMedicalConditionUpdateID)) {
                        $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulPersonMedicalConditionUpdate', $tawasulPersonMedicalConditionUpdateID, 'attachment'
                        );
                        
                        if (empty($tawasulFileID)) {
                            $partialFail = true;
                        }
                    }

                    // Handle file deletion when user removes file
                    if (empty($data['attachment']) && !empty($oldUpdateRecord['attachment'])) {
                        $deleted = $container->get(FileHandler::class)->deleteFile('tawasulPersonMedicalConditionUpdate', $tawasulPersonMedicalConditionUpdateID, 'attachment');
                    }
                }

                //Add new medical condition
                if (isset($_POST['addCondition']) && $_POST['addCondition'] == 'Yes') {
                    $dataChanged = true;
                    $data = [
                        'tawasulPersonMedicalUpdateID' => $tawasulPersonMedicalUpdateID,
                        'tawasulPersonMedicalID'       => $tawasulPersonMedicalID,
                        'name'                        => $_POST['name'] ?? '',
                        'tawasulAlertLevelID'          => $_POST['tawasulAlertLevelID'] ?? '',
                        'triggers'                    => $_POST['triggers'] ?? '',
                        'reaction'                    => $_POST['reaction'] ?? '',
                        'response'                    => $_POST['response'] ?? '',
                        'medication'                  => $_POST['medication'] ?? '',
                        'lastEpisode'                 => !empty($_POST['lastEpisode']) ? Format::dateConvert($_POST['lastEpisode']) :  null,
                        'lastEpisodeTreatment'        => $_POST['lastEpisodeTreatment'] ?? '',
                        'comment'                     => $_POST['commentCond'] ?? '',
                        'attachment'                  => $_POST['attachment'] ?? '',
                        'tawasulPersonIDUpdater'       => $session->get('tawasulPersonID'),
                        'timestamp'                   => date('Y-m-d H:i:s'),
                    ];

                    $newFileMetaData = null;
                    if (!empty($_FILES['attachment']['tmp_name'])) {
                        // Upload the file, return the /uploads relative path
                        $fileUploader = new FileUploader($pdo, $session);
                        $data['attachment'] = $fileUploader->uploadFromPost($_FILES['attachment']);

                        if (empty($data['attachment'])) {
                            $partialFail = true;
                        } else {
                            $newFileMetaData = $fileUploader->getFileMetaData($data['attachment']);
                        }
                    }

                    if (!empty($data['name']) and !empty($data['tawasulAlertLevelID'])) {
                        $sql = 'INSERT INTO tawasulPersonMedicalConditionUpdate SET tawasulPersonMedicalUpdateID=:tawasulPersonMedicalUpdateID, tawasulPersonMedicalID=:tawasulPersonMedicalID, name=:name, tawasulAlertLevelID=:tawasulAlertLevelID, triggers=:triggers, reaction=:reaction, response=:response, medication=:medication, lastEpisode=:lastEpisode, lastEpisodeTreatment=:lastEpisodeTreatment, comment=:comment, attachment=:attachment, tawasulPersonIDUpdater=:tawasulPersonIDUpdater, timestamp=:timestamp';
                        $tawasulPersonMedicalConditionUpdateID = $pdo->insert($sql, $data);
                        
                        // Record file tracking
                        if (!empty($newFileMetaData) && !empty($tawasulPersonMedicalConditionUpdateID)) {
                            $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($newFileMetaData, 'tawasulPersonMedicalConditionUpdate', $tawasulPersonMedicalConditionUpdateID, 'attachment');
                            
                            if (empty($tawasulFileID)) {
                                $partialFail = true;
                            }
                        }
                    } else {
                        $partialFail = true;
                    }
                }

                // If no data has changed in the medical form and any conditions, then auto-accept the changes
                if ($dataChanged == false) {
                    $container->get(MedicalUpdateGateway::class)->update($tawasulPersonMedicalUpdateID, ['status' => 'Complete']);
                } else {
                    // Raise a new notification event
                    $event = new NotificationEvent('Data Updater', 'Medical Form Updates');

                    $event->addRecipient($session->get('organisationDBA'));
                    $event->setNotificationText(__('A medical data update request has been submitted.'));
                    $event->setActionLink('/index.php?q=/modules/TawasulDataUpdater/data_medical_manage.php');

                    $event->sendNotifications($pdo, $session);
                }

                if ($partialFail == true) {
                    $URL .= '&return=warning1';
                    header("Location: {$URL}");
                } else {
                    $URLSuccess .= '&return=success0';
                    header("Location: {$URLSuccess}");
                }
            }
        }
    }
}
