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

use TawasulOS\Services\Format;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Forms\PersonalDocumentHandler;
use TawasulOS\Domain\User\PersonalDocumentGateway;
use TawasulOS\Data\Validator;
use TawasulOS\Domain\User\RoleGateway;
use TawasulOS\Domain\System\SettingGateway;

require_once __DIR__ . '/../../tawasul.php';

$validator = $container->get(Validator::class);
$_POST = $validator->sanitize($_POST);

//Module includes for User Admin (for custom fields)
include '../User Admin/moduleFunctions.php';

$tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
$address = $_POST['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/data_personal.php&tawasulPersonID=$tawasulPersonID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulDataUpdater/data_personal.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulPersonID specified
    if ($tawasulPersonID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        //Get action with highest precendence
        $highestAction = getHighestGroupedAction($guid, $address, $connection2);
        if ($highestAction == false) {
            $URL .= "&return=error0$params";
            header("Location: {$URL}");
        } else {
            //Check access to person
            $partialFail = false;
            $checkCount = 0;
            $self = false;

            $settingGateway = $container->get(SettingGateway::class);

            if ($highestAction == 'Update Personal Data_any') {
                $URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/TawasulDataUpdater/data_personal.php&tawasulPersonID='.$tawasulPersonID;


                    $dataSelect = array('tawasulPersonID' => $tawasulPersonID);
                    $sqlSelect = "SELECT surname, preferredName, tawasulPerson.tawasulPersonID, tawasulRoleIDAll FROM tawasulPerson WHERE status='Full' AND tawasulPersonID=:tawasulPersonID ORDER BY surname, preferredName";
                    $resultSelect = $connection2->prepare($sqlSelect);
                    $resultSelect->execute($dataSelect);
                $checkCount = $resultSelect->rowCount();
                $self = true;
            } else {
                $URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/TawasulDataUpdater/data_updates.php&tawasulPersonID='.$tawasulPersonID;

                try {
                    $dataCheck = array('tawasulPersonID' => $session->get('tawasulPersonID'));
                    $sqlCheck = "SELECT tawasulFamilyAdult.tawasulFamilyID, name FROM tawasulFamilyAdult JOIN tawasulFamily ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) WHERE tawasulPersonID=:tawasulPersonID AND childDataAccess='Y' ORDER BY name";
                    $resultCheck = $connection2->prepare($sqlCheck);
                    $resultCheck->execute($dataCheck);
                } catch (PDOException $e) {
                }
                while ($rowCheck = $resultCheck->fetch()) {

                        $dataCheck2 = array('tawasulFamilyID1' => $rowCheck['tawasulFamilyID'], 'tawasulFamilyID2' => $rowCheck['tawasulFamilyID']);
                        $sqlCheck2 = "(SELECT surname, preferredName, tawasulPerson.tawasulPersonID, tawasulFamilyID, tawasulRoleIDAll FROM tawasulFamilyChild JOIN tawasulPerson ON (tawasulFamilyChild.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulPerson.status='Full' AND tawasulFamilyID=:tawasulFamilyID1) UNION (SELECT surname, preferredName, tawasulPerson.tawasulPersonID, tawasulFamilyID, tawasulRoleIDAll FROM tawasulFamilyAdult JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulPerson.status='Full' AND tawasulFamilyID=:tawasulFamilyID2)";
                        $resultCheck2 = $connection2->prepare($sqlCheck2);
                        $resultCheck2->execute($dataCheck2);
                    while ($rowCheck2 = $resultCheck2->fetch()) {
                        if ($tawasulPersonID == $rowCheck2['tawasulPersonID']) {
                            ++$checkCount;
                        }
                        //Check for self
                        if ($rowCheck2['tawasulPersonID'] == $session->get('tawasulPersonID')) {
                            $self = true;
                        }
                    }
                }
            }

            if ($self == false and $tawasulPersonID == $session->get('tawasulPersonID')) {
                ++$checkCount;
            }

            if ($checkCount < 1) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                //Get user data
                try {
                    $data = array('tawasulPersonID' => $tawasulPersonID);
                    $sql = "SELECT * FROM tawasulPerson WHERE status='Full' AND tawasulPersonID=:tawasulPersonID ORDER BY surname, preferredName";
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
                    exit();
                } else {
                    $values = $result->fetch();

                    //Get categories
                    $staff = $student = $parent = $other = false;
                    $roles = explode(',', $values['tawasulRoleIDAll']);

                    /** @var RoleGateway */
                    $roleGateway = $container->get(RoleGateway::class);

                    foreach ($roles as $role) {
                        $roleCategory = $roleGateway->getRoleCategory($role);
                        $staff = $staff || ($roleCategory == 'Staff');
                        $student = $student || ($roleCategory == 'Student');
                        $parent = $parent || ($roleCategory == 'Parent');
                        $other = $other || ($roleCategory == 'Other');
                    }

                    //Proceed!
                    $data = [
                        'tawasulPersonID'             => $tawasulPersonID,
                        'title'                      => $_POST['title'] ?? $values['title'],
                        'surname'                    => $validator->sanitizeName($_POST['surname'] ?? $values['surname']),
                        'firstName'                  => $validator->sanitizeName($_POST['firstName'] ?? $values['firstName']),
                        'preferredName'              => $validator->sanitizeName($_POST['preferredName'] ?? $values['preferredName']),
                        'officialName'               => $validator->sanitizeName($_POST['officialName'] ?? $values['officialName']),
                        'nameInCharacters'           => $_POST['nameInCharacters'] ?? $values['nameInCharacters'],
                        'dob'                        => isset($_POST['dob']) ? Format::dateConvert($_POST['dob']) : $values['dob'],
                        'email'                      => filter_var(trim($_POST['email'] ?? $values['email']), FILTER_SANITIZE_EMAIL),
                        'emailAlternate'             => filter_var(trim($_POST['emailAlternate'] ?? $values['emailAlternate']), FILTER_SANITIZE_EMAIL),
                        'address1'                   => $_POST['address1'] ?? $values['address1'],
                        'address1District'           => $_POST['address1District'] ?? $values['address1District'],
                        'address1Country'            => $_POST['address1Country'] ?? $values['address1Country'],
                        'address2'                   => $_POST['address2'] ?? $values['address2'],
                        'address2District'           => $_POST['address2District'] ?? $values['address2District'],
                        'address2Country'            => $_POST['address2Country'] ?? $values['address2Country'],
                        'phone1Type'                 => $_POST['phone1Type'] ?? $values['phone1Type'],
                        'phone1CountryCode'          => $_POST['phone1CountryCode'] ?? $values['phone1CountryCode'],
                        'phone1'                     => $_POST['phone1'] ?? $values['phone1'],
                        'phone2Type'                 => $_POST['phone2Type'] ?? $values['phone2Type'],
                        'phone2CountryCode'          => $_POST['phone2CountryCode'] ?? $values['phone2CountryCode'],
                        'phone2'                     => $_POST['phone2'] ?? $values['phone2'],
                        'phone3Type'                 => $_POST['phone3Type'] ?? $values['phone3Type'],
                        'phone3CountryCode'          => $_POST['phone3CountryCode'] ?? $values['phone3CountryCode'],
                        'phone3'                     => $_POST['phone3'] ?? $values['phone3'],
                        'phone4Type'                 => $_POST['phone4Type'] ?? $values['phone4Type'],
                        'phone4CountryCode'          => $_POST['phone4CountryCode'] ?? $values['phone4CountryCode'],
                        'phone4'                     => $_POST['phone4'] ?? $values['phone4'],
                        'languageFirst'              => $_POST['languageFirst'] ?? $values['languageFirst'],
                        'languageSecond'             => $_POST['languageSecond'] ?? $values['languageSecond'],
                        'languageThird'              => $_POST['languageThird'] ?? $values['languageThird'],
                        'countryOfBirth'             => $_POST['countryOfBirth'] ?? $values['countryOfBirth'],
                        'ethnicity'                  => $_POST['ethnicity'] ?? $values['ethnicity'],
                        'religion'                   => $_POST['religion'] ?? $values['religion'],
                        'emergency1Name'             => $_POST['emergency1Name'] ?? $values['emergency1Name'],
                        'emergency1Number1'          => $_POST['emergency1Number1'] ?? $values['emergency1Number1'],
                        'emergency1Number2'          => $_POST['emergency1Number2'] ?? $values['emergency1Number2'],
                        'emergency1Relationship'     => $_POST['emergency1Relationship'] ?? $values['emergency1Relationship'],
                        'emergency2Name'             => $_POST['emergency2Name'] ?? $values['emergency2Name'],
                        'emergency2Number1'          => $_POST['emergency2Number1'] ?? $values['emergency2Number1'],
                        'emergency2Number2'          => $_POST['emergency2Number2'] ?? $values['emergency2Number2'],
                        'emergency2Relationship'     => $_POST['emergency2Relationship'] ?? $values['emergency2Relationship'],
                        'profession'                 => $_POST['profession'] ?? $values['profession'],
                        'employer'                   => $_POST['employer'] ?? $values['employer'],
                        'jobTitle'                   => $_POST['jobTitle'] ?? $values['jobTitle'],
                        'vehicleRegistration'        => $_POST['vehicleRegistration'] ?? $values['vehicleRegistration'],
                    ];

                    $data = array_map('trim', $data);

                    // Date handling - ensure NULL value
                    if (empty($data['dob'])) $data['dob'] = null;

                    // Matching addresses
                    $matchAddressCount = $_POST['matchAddressCount'] ?? 0;

                    // Phone number filtering
                    for ($i = 1; $i <= 4; $i++) {
                        $data["phone{$i}"] = preg_replace('/[^0-9+]/', '', $data["phone{$i}"]);
                        if (!empty($data["phone{$i}"]) && empty($data["phone{$i}Type"])) {
                            $data["phone{$i}Type"] = 'Other';
                        }
                    }

                    // Student privacy settings
                    $privacyOptionVisibility = $settingGateway->getSettingByScope('User Admin', 'privacyOptionVisibility');
                    if ($privacyOptionVisibility == 'Y') {
                        $data['privacy'] = !empty($_POST['privacyOptions']) && is_array($_POST['privacyOptions'])
                            ? implode(',', $_POST['privacyOptions'])
                            : '';
                    } else {
                        $data['privacy'] = $values['privacy'];
                    }

                    // COMPARE VALUES: Has the data changed?
                    $dataChanged = $matchAddressCount > 0 ? true : false;
                    foreach ($values as $key => $value) {
                        if (!isset($data[$key])) continue; // Skip fields we don't plan to update
                        if (empty($data[$key]) && empty($value)) continue; // Nulls, false and empty strings should cause no change

                        if ($data[$key] != $value) {
                            $dataChanged = true;
                        }
                    }

                    // CUSTOM FIELDS
                    $customRequireFail = false;
                    $params = compact('student', 'staff', 'parent', 'other') + ['dataUpdater' => true];
                    $fields = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('User', $params, $customRequireFail);

                    // Check for data changed
                    $existingFields = json_decode($values['fields'], true);
                    $newFields = json_decode($fields, true);
                    foreach ($newFields as $key => $fieldValue) {
                        if (empty($existingFields[$key]) || $existingFields[$key] != $fieldValue) {
                            $dataChanged = true;
                        }
                    }

                    // Don't require fields for users with _any permission
                    if ($highestAction == 'Update Personal Data_any') {
                        $customRequireFail = false;
                    }

                    // PERSONAL DOCUMENTS - data changed
                    $params = compact('student', 'staff', 'parent', 'other') + ['dataUpdater' => true];
                    $documents = $container->get(PersonalDocumentGateway::class)->selectPersonalDocuments('tawasulPerson', $tawasulPersonID, $params);
                    foreach ($documents as $document) {
                        $documentFields = json_decode($document['fields']);
                        foreach ($documentFields as $field) {
                            $value = !empty($_POST['document'][$document['tawasulPersonalDocumentTypeID']][$field]) ? $_POST['document'][$document['tawasulPersonalDocumentTypeID']][$field] : null;

                            if ($field == 'filePath' && !empty($_FILES['document'.$document['tawasulPersonalDocumentTypeID'].$field]['tmp_name'])) {
                                $dataChanged = true;
                            }

                            if ($field == 'dateExpiry' || $field == 'dateIssue') {
                                $value = Format::dateConvert($value);
                            }

                            if ($document[$field] != $value) {
                                $dataChanged = true;
                            }
                        }
                    }

                    if ($customRequireFail) {
                        $URL .= '&return=error1';
                        header("Location: {$URL}");
                    } else {
                        $data['fields'] = $fields;

                        //Write to database
                        $existing = $_POST['existing'] ?? 'N';

                        // Fetch old record for file comparison if updating
                        $oldPersonUpdateRecord = null;
                        if ($existing != 'N') {
                            try {
                                $dataOld = ['tawasulPersonUpdateID' => $existing];
                                $sqlOld = 'SELECT fields FROM tawasulPersonUpdate WHERE tawasulPersonUpdateID=:tawasulPersonUpdateID';
                                $resultOld = $connection2->prepare($sqlOld);
                                $resultOld->execute($dataOld);
                                $oldPersonUpdateRecord = $resultOld->fetch();
                            } catch (PDOException $e) {
                                $oldPersonUpdateRecord = null;
                            }
                        }

                        // Auto-accept updates where no data had changed
                        $data['status'] = $dataChanged ? 'Pending' : 'Complete';
                        $data['tawasulSchoolYearID'] = $session->get('tawasulSchoolYearID');
                        $data['tawasulPersonIDUpdater'] = $session->get('tawasulPersonID');
                        $data['timestamp'] = date('Y-m-d H:i:s');

                        if ($existing != 'N') {
                            $tawasulPersonUpdateID = $existing;
                            $data['tawasulPersonUpdateID'] = $tawasulPersonUpdateID;
                            $sql = 'UPDATE tawasulPersonUpdate SET `status`=:status, tawasulSchoolYearID=:tawasulSchoolYearID, tawasulPersonID=:tawasulPersonID, title=:title, surname=:surname, firstName=:firstName, preferredName=:preferredName, officialName=:officialName, nameInCharacters=:nameInCharacters, dob=:dob, email=:email, emailAlternate=:emailAlternate, address1=:address1, address1District=:address1District, address1Country=:address1Country, address2=:address2, address2District=:address2District, address2Country=:address2Country, phone1Type=:phone1Type, phone1CountryCode=:phone1CountryCode, phone1=:phone1, phone2Type=:phone2Type, phone2CountryCode=:phone2CountryCode, phone2=:phone2, phone3Type=:phone3Type, phone3CountryCode=:phone3CountryCode, phone3=:phone3, phone4Type=:phone4Type, phone4CountryCode=:phone4CountryCode, phone4=:phone4, languageFirst=:languageFirst, languageSecond=:languageSecond, languageThird=:languageThird, countryOfBirth=:countryOfBirth, ethnicity=:ethnicity, religion=:religion, emergency1Name=:emergency1Name, emergency1Number1=:emergency1Number1, emergency1Number2=:emergency1Number2, emergency1Relationship=:emergency1Relationship, emergency2Name=:emergency2Name, emergency2Number1=:emergency2Number1, emergency2Number2=:emergency2Number2, emergency2Relationship=:emergency2Relationship, profession=:profession, employer=:employer, jobTitle=:jobTitle, vehicleRegistration=:vehicleRegistration, tawasulPersonIDUpdater=:tawasulPersonIDUpdater, privacy=:privacy, fields=:fields, timestamp=:timestamp WHERE tawasulPersonUpdateID=:tawasulPersonUpdateID';

                            $pdo->update($sql, $data);
                        } else {
                            $sql = 'INSERT INTO tawasulPersonUpdate SET `status`=:status, tawasulSchoolYearID=:tawasulSchoolYearID, tawasulPersonID=:tawasulPersonID, title=:title, surname=:surname, firstName=:firstName, preferredName=:preferredName, officialName=:officialName, nameInCharacters=:nameInCharacters, dob=:dob, email=:email, emailAlternate=:emailAlternate, address1=:address1, address1District=:address1District, address1Country=:address1Country, address2=:address2, address2District=:address2District, address2Country=:address2Country, phone1Type=:phone1Type, phone1CountryCode=:phone1CountryCode, phone1=:phone1, phone2Type=:phone2Type, phone2CountryCode=:phone2CountryCode, phone2=:phone2, phone3Type=:phone3Type, phone3CountryCode=:phone3CountryCode, phone3=:phone3, phone4Type=:phone4Type, phone4CountryCode=:phone4CountryCode, phone4=:phone4, languageFirst=:languageFirst, languageSecond=:languageSecond, languageThird=:languageThird, countryOfBirth=:countryOfBirth, ethnicity=:ethnicity, religion=:religion, emergency1Name=:emergency1Name, emergency1Number1=:emergency1Number1, emergency1Number2=:emergency1Number2, emergency1Relationship=:emergency1Relationship, emergency2Name=:emergency2Name, emergency2Number1=:emergency2Number1, emergency2Number2=:emergency2Number2, emergency2Relationship=:emergency2Relationship, profession=:profession, employer=:employer, jobTitle=:jobTitle, vehicleRegistration=:vehicleRegistration, tawasulPersonIDUpdater=:tawasulPersonIDUpdater, privacy=:privacy, fields=:fields, timestamp=:timestamp';

                            $tawasulPersonUpdateID = $pdo->insert($sql, $data);
                        }

                        // PERSONAL DOCUMENTS
                        if ($dataChanged) {
                            $personalDocumentFail = false;
                            $params = compact('student', 'staff', 'parent', 'other') + ['dataUpdater' => true];
                            $container->get(PersonalDocumentHandler::class)->updateDocumentsFromPOST('tawasulPersonUpdate', $tawasulPersonUpdateID, $params , $personalDocumentFail);

                            $partialFail &= $personalDocumentFail;
                        }


                        // Manage custom field file uploads
                        if (!empty($fields)) {
                            $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('User', ['dataUpdater' => true], $fields, 'tawasulPersonUpdate', $tawasulPersonUpdateID, $oldPersonUpdateRecord['fields'] ?? null);
                        }

                        //Update matching addresses
                        if ($matchAddressCount > 0) {
                            for ($i = 0; $i < $matchAddressCount; ++$i) {
                                if (!empty($_POST[$i.'-matchAddress'])) {
                                    $sqlAddress = '';
                                    try {
                                        $dataCheck = array('tawasulPersonID' => $_POST[$i.'-matchAddress'], 'tawasulPersonIDUpdater' => $session->get('tawasulPersonID'));
                                        $sqlCheck = "SELECT * FROM tawasulPersonUpdate WHERE tawasulPersonID=:tawasulPersonID AND tawasulPersonIDUpdater=:tawasulPersonIDUpdater AND status='Pending'";
                                        $resultCheck = $connection2->prepare($sqlCheck);
                                        $resultCheck->execute($dataCheck);
                                    } catch (PDOException $e) {
                                        $partialFail = true;
                                    }

                                    if ($resultCheck->rowCount() > 1) {
                                        $partialFail = true;
                                    } elseif ($resultCheck->rowCount() == 1) {
                                        $rowCheck = $resultCheck->fetch();
                                        $dataAddress = array('tawasulPersonID' => $_POST[$i.'-matchAddress'], 'address1' => $address1, 'address1District' => $address1District, 'address1Country' => $address1Country, 'tawasulPersonIDUpdater' => $session->get('tawasulPersonID'), 'tawasulPersonUpdateID' => $rowCheck['tawasulPersonUpdateID']);
                                        $sqlAddress = 'UPDATE tawasulPersonUpdate SET tawasulPersonID=:tawasulPersonID, address1=:address1, address1District=:address1District, address1Country=:address1Country, tawasulPersonIDUpdater=:tawasulPersonIDUpdater WHERE tawasulPersonUpdateID=:tawasulPersonUpdateID';
                                    } else {
                                        $dataAddress = array('tawasulPersonID' => $_POST[$i.'-matchAddress'], 'address1' => $address1, 'address1District' => $address1District, 'address1Country' => $address1Country, 'tawasulPersonIDUpdater' => $session->get('tawasulPersonID'));
                                        $sqlAddress = 'INSERT INTO tawasulPersonUpdate SET tawasulPersonID=:tawasulPersonID, address1=:address1, address1District=:address1District, address1Country=:address1Country, tawasulPersonIDUpdater=:tawasulPersonIDUpdater';
                                    }
                                    if ($sqlAddress != '') {
                                        try {
                                            $resultAddress = $connection2->prepare($sqlAddress);
                                            $resultAddress->execute($dataAddress);
                                        } catch (PDOException $e) {
                                            $partialFail = true;
                                        }
                                    }
                                }
                            }
                        }

                        if ($dataChanged) {
                            // Raise a new notification event
                            $event = new NotificationEvent('Data Updater', 'Personal Data Updates');

                            $event->addRecipient($session->get('organisationDBA'));
                            $event->setNotificationText(__('A personal data update request has been submitted.'));
                            $event->setActionLink('/index.php?q=/modules/TawasulDataUpdater/data_personal_manage.php');

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
    }
}
