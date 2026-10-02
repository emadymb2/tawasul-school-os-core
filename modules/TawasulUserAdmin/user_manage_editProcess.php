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
use TawasulOS\UI\Components\Alert;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Domain\User\RoleGateway;
use TawasulOS\Comms\NotificationSender;
use TawasulOS\Domain\System\LogGateway;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Forms\PersonalDocumentHandler;
use TawasulOS\Domain\User\PersonPhotoGateway;
use TawasulOS\Domain\User\UserStatusLogGateway;
use TawasulOS\Domain\System\NotificationGateway;
use TawasulOS\Contracts\Filesystem\FileHandler;

require_once __DIR__ . '/../../tawasul.php';

$validator = $container->get(Validator::class);
$_POST = $validator->sanitize($_POST, ['website' => 'URL']);

//Module includes
include './moduleFunctions.php';

$logGateway = $container->get(LogGateway::class);
$tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'])."/user_manage_edit.php&tawasulPersonID=$tawasulPersonID&search=".$_GET['search'];

if (isActionAccessible($guid, $connection2, '/modules/TawasulUserAdmin/user_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if tawasulPersonID specified
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
            $row = $result->fetch();

            //Get categories
            $staff = false;
            $student = false;
            $parent = false;
            $other = false;
            $roles = explode(',', $row['tawasulRoleIDAll']);

            /** @var RoleGateway */
            $roleGateway = $container->get(RoleGateway::class);

            foreach ($roles as $role) {
                $roleCategory = $roleGateway->getRoleCategory($role);
                if ($roleCategory == 'Staff') {
                    $staff = true;
                }
                if ($roleCategory == 'Student') {
                    $student = true;
                }
                if ($roleCategory == 'Parent') {
                    $parent = true;
                }
                if ($roleCategory == 'Other') {
                    $other = true;
                }
            }

            //Proceed!
            $title = $_POST['title'] ?? '';
            $surname = $validator->sanitizeName($_POST['surname'] ?? '');
            $firstName = $validator->sanitizeName($_POST['firstName'] ?? '');
            $preferredName = $validator->sanitizeName($_POST['preferredName'] ?? '');
            $officialName = $validator->sanitizeName($_POST['officialName'] ?? '');
            $nameInCharacters = $_POST['nameInCharacters'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $username = isset($_POST['username'])? $_POST['username'] : $values['username'];
            $status = $_POST['status'] ?? '';
            $canLogin = $_POST['canLogin'] ?? '';
            $passwordForceReset = $_POST['passwordForceReset'] ?? '';

            // Put together an array of this user's current roles
            $currentUserRoles = (is_array($session->get('tawasulRoleIDAll'))) ? array_column($session->get('tawasulRoleIDAll'), 0) : array();
            $currentUserRoles[] = $session->get('tawasulRoleIDPrimary');

            $sqlRoles = 'SELECT tawasulRoleID, restriction, name FROM tawasulRole';
            $resultRoles = $connection2->prepare($sqlRoles);
            $resultRoles->execute();

            $tawasulRoleIDAll = array();
            $tawasulRoleIDPrimary = $row['tawasulRoleIDPrimary'];

            $selectedRoleIDPrimary = (isset($_POST['tawasulRoleIDPrimary'])) ? $_POST['tawasulRoleIDPrimary'] : null;
            $selectedRoleIDAll = (isset($_POST['tawasulRoleIDAll'])) ? $_POST['tawasulRoleIDAll'] : array();

            if ($resultRoles && $resultRoles->rowCount() > 0) {
                while ($rowRole = $resultRoles->fetch()) {

                    if ($rowRole['restriction'] == 'Admin Only') {
                        if (in_array('001', $currentUserRoles)) {
                            // Add selected roles only if they meet the restriction
                            if (in_array($rowRole['tawasulRoleID'], $selectedRoleIDAll)) {
                                $tawasulRoleIDAll[] = $rowRole['tawasulRoleID'];
                            }

                            if ($rowRole['tawasulRoleID'] == $selectedRoleIDPrimary) {
                                // Prevent primary role being changed to a restricted role (via modified POST)
                                $tawasulRoleIDPrimary = $selectedRoleIDPrimary;
                            }
                        } else if (in_array($rowRole['tawasulRoleID'], $roles)) {
                            // Add existing restricted roles because they cannot be removed by this user
                            $tawasulRoleIDAll[] = $rowRole['tawasulRoleID'];
                        }
                    } else if ($rowRole['restriction'] == 'Same Role') {
                        if (in_array($rowRole['tawasulRoleID'], $currentUserRoles) || in_array('001', $currentUserRoles)) {
                            if (in_array($rowRole['tawasulRoleID'], $selectedRoleIDAll)) {
                                $tawasulRoleIDAll[] = $rowRole['tawasulRoleID'];
                            }

                            if ($rowRole['tawasulRoleID'] == $selectedRoleIDPrimary) {
                                $tawasulRoleIDPrimary = $selectedRoleIDPrimary;
                            }
                        } else if (in_array($rowRole['tawasulRoleID'], $roles)) {
                            $tawasulRoleIDAll[] = $rowRole['tawasulRoleID'];
                        }
                    } else {
                        if (in_array($rowRole['tawasulRoleID'], $selectedRoleIDAll)) {
                            $tawasulRoleIDAll[] = $rowRole['tawasulRoleID'];
                        }

                        if ($rowRole['tawasulRoleID'] == $selectedRoleIDPrimary) {
                            $tawasulRoleIDPrimary = $selectedRoleIDPrimary;
                        }
                    }
                }
            }

            // Ensure the primary role is always in the all roles list
            if (!in_array($tawasulRoleIDPrimary, $tawasulRoleIDAll)) {
                $tawasulRoleIDAll[] = $tawasulRoleIDPrimary;
            }

            $tawasulRoleIDAll = (is_array($tawasulRoleIDAll))? implode(',', array_unique($tawasulRoleIDAll)) : $row['tawasulRoleIDAll'];

            $dob = !empty($_POST['dob']) ? Format::dateConvert($_POST['dob']) : null;
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $emailAlternate = filter_var(trim($_POST['emailAlternate'] ?? ''), FILTER_SANITIZE_EMAIL);
            $address1 = $_POST['address1'] ?? '';
            $address1District = $_POST['address1District'] ?? '';
            $address1Country = $_POST['address1Country'] ?? '';
            $address2 = $_POST['address2'] ?? '';
            $address2District = $_POST['address2District'] ?? '';
            $address2Country = $_POST['address2Country'] ?? '';
            $phone1Type = $_POST['phone1Type'] ?? '';
            if ($_POST['phone1'] != '' && $phone1Type == '') {
                $phone1Type = 'Other';
            }
            $phone1CountryCode = $_POST['phone1CountryCode'] ?? '';
            $phone1 = preg_replace('/[^0-9+]/', '', $_POST['phone1'] ?? '');
            $phone2Type = $_POST['phone2Type'] ?? '';
            if ($_POST['phone2'] != '' && $phone2Type == '') {
                $phone2Type = 'Other';
            }
            $phone2CountryCode = $_POST['phone2CountryCode'] ?? '';
            $phone2 = preg_replace('/[^0-9+]/', '', $_POST['phone2'] ?? '');
            $phone3Type = $_POST['phone3Type'] ?? '';
            if ($_POST['phone3'] != '' && $phone3Type == '') {
                $phone3Type = 'Other';
            }
            $phone3CountryCode = $_POST['phone3CountryCode'] ?? '';
            $phone3 = preg_replace('/[^0-9+]/', '', $_POST['phone3'] ?? '');
            $phone4Type = $_POST['phone4Type'] ?? '';
            if ($_POST['phone4'] != '' && $phone4Type == '') {
                $phone4Type = 'Other';
            }
            $phone4CountryCode = $_POST['phone4CountryCode'] ?? '';
            $phone4 = preg_replace('/[^0-9+]/', '', $_POST['phone4'] ?? '');
            $website = filter_var(trim($_POST['website'] ?? ''), FILTER_SANITIZE_URL);
            $languageFirst = $_POST['languageFirst'] ?? '';
            $languageSecond = $_POST['languageSecond'] ?? '';
            $languageThird = $_POST['languageThird'] ?? '';
            $countryOfBirth = $_POST['countryOfBirth'] ?? '';
            $ethnicity = $_POST['ethnicity'] ?? '';
            $religion = $_POST['religion'] ?? '';

            $profession = $_POST['profession'] ?? null;
            $employer = $_POST['employer'] ?? null;
            $jobTitle = $_POST['jobTitle'] ?? null;

            $emergency1Name = $_POST['emergency1Name'] ?? null;
            $emergency1Number1 = $_POST['emergency1Number1'] ?? null;
            $emergency1Number2 = $_POST['emergency1Number2'] ?? null;
            $emergency1Relationship = $_POST['emergency1Relationship'] ?? null;

            $emergency2Name = $_POST['emergency2Name'] ?? null;
            $emergency2Number1 = $_POST['emergency2Number1'] ?? null;
            $emergency2Number2 = $_POST['emergency2Number2'] ?? null;
            $emergency2Relationship = $_POST['emergency2Relationship'] ?? null;

            $tawasulHouseID = !empty($_POST['tawasulHouseID']) ? $_POST['tawasulHouseID'] : null;
            $studentID = $_POST['studentID'] ?? null;
            $dateStart = !empty($_POST['dateStart']) ? Format::dateConvert($_POST['dateStart']) : null;
            $dateEnd = !empty($_POST['dateEnd']) ? Format::dateConvert($_POST['dateEnd']) : null;
            $tawasulSchoolYearIDClassOf = !empty($_POST['tawasulSchoolYearIDClassOf']) ? $_POST['tawasulSchoolYearIDClassOf'] : null;
            $lastSchool = $_POST['lastSchool'] ?? null;
            $nextSchool = $_POST['nextSchool'] ?? null;
            $departureReason = $_POST['departureReason'] ?? null;
            $transport = $_POST['transport'] ?? null;
            $transportNotes = $_POST['transportNotes'] ?? null;
            $lockerNumber = $_POST['lockerNumber'] ?? null;
            $vehicleRegistration = $_POST['vehicleRegistration'] ?? '';

            $privacy = !empty($_POST['privacyOptions']) ? implode(',', $_POST['privacyOptions']) : null;
            $privacy_old = $row['privacy'];
            $agreements = !empty($_POST['studentAgreements']) ? implode(',', $_POST['studentAgreements']) : null;
            $dayType = $_POST['dayType'] ?? null;

            //Validate Inputs
            if ($surname == '' || $firstName == '' || $preferredName == '' || $officialName == '' || $gender == '' || $username == '' || $status == '' || $tawasulRoleIDPrimary == '') {
                $URL .= '&return=error3';
                header("Location: {$URL}");
            } else {
                //Check unique inputs for uniquness
                try {
                    $data = array('username' => $username, 'tawasulPersonID' => $tawasulPersonID);
                    $sql = 'SELECT * FROM tawasulPerson WHERE username=:username AND NOT tawasulPersonID=:tawasulPersonID';
                    if ($studentID != '') {
                        $data = array('username' => $username, 'tawasulPersonID' => $tawasulPersonID, 'studentID' => $studentID);
                        $sql = 'SELECT * FROM tawasulPerson WHERE (username=:username OR studentID=:studentID) AND NOT tawasulPersonID=:tawasulPersonID ';
                    }
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
                    $imageFail = false;
                    $fileMetaData = null;
                    $updateBackupPhoto = false;
                    if (!empty($_FILES['file1']['tmp_name']))
                    {
                        $path = $session->get('absolutePath');
                        $fileUploader = new TawasulOS\FileUploader($pdo, $session);

                        //Move 240 attached file, if there is one
                        if (!empty($_FILES['file1']['tmp_name'])) {
                            $file = (isset($_FILES['file1']))? $_FILES['file1'] : null;

                            // Upload the file, return the /uploads relative path
                            $fileUploader->setFileSuffixType(TawasulOS\FileUploader::FILE_SUFFIX_INCREMENTAL);
                            $attachment1 = $fileUploader->uploadAndResizeImage($file, $username.'_240', 480, 100);

                            if (empty($attachment1)) {
                                $imageFail = true;
                            } else {
                                $fileMetaData = $fileUploader->getFileMetaData($attachment1);
                                $updateBackupPhoto = true;
                            }
                        }
                    } else {
                        // Remove the attachment if it has been deleted, otherwise retain the original value
                        $attachment1 = empty($_POST['attachment1']) ? '' : $row['image_240'];
                    }

                    // CUSTOM FIELDS
                    $customRequireFail = false;
                    $params = compact('student', 'staff', 'parent', 'other');
                    $params['requiredOverride'] = 'N';
                    $fields = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('User', $params, $customRequireFail);

                    // PERSONAL DOCUMENTS
                    $personalDocumentFail = false;
                    $params = compact('student', 'staff', 'parent', 'other');
                    $container->get(PersonalDocumentHandler::class)->updateDocumentsFromPOST('tawasulPerson', $tawasulPersonID, $params, $personalDocumentFail);

                    if ($customRequireFail) {
                        $URL .= '&return=error3';
                        header("Location: {$URL}");
                    } else {
                        //Write to database
                        try {
                            $data = array('title' => $title, 'surname' => $surname, 'firstName' => $firstName, 'preferredName' => $preferredName, 'officialName' => $officialName, 'nameInCharacters' => $nameInCharacters, 'gender' => $gender, 'username' => $username, 'status' => $status, 'canLogin' => $canLogin, 'passwordForceReset' => $passwordForceReset, 'tawasulRoleIDPrimary' => $tawasulRoleIDPrimary, 'tawasulRoleIDAll' => $tawasulRoleIDAll, 'dob' => $dob, 'email' => $email, 'emailAlternate' => $emailAlternate, 'address1' => $address1, 'address1District' => $address1District, 'address1Country' => $address1Country, 'address2' => $address2, 'address2District' => $address2District, 'address2Country' => $address2Country, 'phone1Type' => $phone1Type, 'phone1CountryCode' => $phone1CountryCode, 'phone1' => $phone1, 'phone2Type' => $phone2Type, 'phone2CountryCode' => $phone2CountryCode, 'phone2' => $phone2, 'phone3Type' => $phone3Type, 'phone3CountryCode' => $phone3CountryCode, 'phone3' => $phone3, 'phone4Type' => $phone4Type, 'phone4CountryCode' => $phone4CountryCode, 'phone4' => $phone4, 'website' => $website, 'languageFirst' => $languageFirst, 'languageSecond' => $languageSecond, 'languageThird' => $languageThird, 'countryOfBirth' => $countryOfBirth, 'ethnicity' => $ethnicity, 'religion' => $religion, 'emergency1Name' => $emergency1Name, 'emergency1Number1' => $emergency1Number1, 'emergency1Number2' => $emergency1Number2, 'emergency1Relationship' => $emergency1Relationship, 'emergency2Name' => $emergency2Name, 'emergency2Number1' => $emergency2Number1, 'emergency2Number2' => $emergency2Number2, 'emergency2Relationship' => $emergency2Relationship, 'profession' => $profession, 'employer' => $employer, 'jobTitle' => $jobTitle, 'attachment1' => $attachment1, 'tawasulHouseID' => $tawasulHouseID, 'studentID' => $studentID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'tawasulSchoolYearIDClassOf' => $tawasulSchoolYearIDClassOf, 'lastSchool' => $lastSchool, 'nextSchool' => $nextSchool, 'departureReason' => $departureReason, 'transport' => $transport, 'transportNotes' => $transportNotes, 'lockerNumber' => $lockerNumber, 'vehicleRegistration' => $vehicleRegistration, 'privacy' => $privacy, 'agreements' => $agreements, 'dayType' => $dayType, 'fields' => $fields, 'tawasulPersonID' => $tawasulPersonID);
                            $sql = 'UPDATE tawasulPerson SET title=:title, surname=:surname, firstName=:firstName, preferredName=:preferredName, officialName=:officialName, nameInCharacters=:nameInCharacters, gender=:gender, username=:username, status=:status, canLogin=:canLogin, passwordForceReset=:passwordForceReset, tawasulRoleIDPrimary=:tawasulRoleIDPrimary, tawasulRoleIDAll=:tawasulRoleIDAll, dob=:dob, email=:email, emailAlternate=:emailAlternate, address1=:address1, address1District=:address1District, address1Country=:address1Country, address2=:address2, address2District=:address2District, address2Country=:address2Country, phone1Type=:phone1Type, phone1CountryCode=:phone1CountryCode, phone1=:phone1, phone2Type=:phone2Type, phone2CountryCode=:phone2CountryCode, phone2=:phone2, phone3Type=:phone3Type, phone3CountryCode=:phone3CountryCode, phone3=:phone3, phone4Type=:phone4Type, phone4CountryCode=:phone4CountryCode, phone4=:phone4, website=:website, languageFirst=:languageFirst, languageSecond=:languageSecond, languageThird=:languageThird, countryOfBirth=:countryOfBirth, ethnicity=:ethnicity,  religion=:religion, emergency1Name=:emergency1Name, emergency1Number1=:emergency1Number1, emergency1Number2=:emergency1Number2, emergency1Relationship=:emergency1Relationship, emergency2Name=:emergency2Name, emergency2Number1=:emergency2Number1, emergency2Number2=:emergency2Number2, emergency2Relationship=:emergency2Relationship, profession=:profession, employer=:employer, jobTitle=:jobTitle, image_240=:attachment1, tawasulHouseID=:tawasulHouseID, studentID=:studentID, dateStart=:dateStart, dateEnd=:dateEnd, tawasulSchoolYearIDClassOf=:tawasulSchoolYearIDClassOf, lastSchool=:lastSchool, nextSchool=:nextSchool, departureReason=:departureReason, transport=:transport, transportNotes=:transportNotes, lockerNumber=:lockerNumber, vehicleRegistration=:vehicleRegistration, privacy=:privacy, studentAgreements=:agreements, dayType=:dayType, fields=:fields WHERE tawasulPersonID=:tawasulPersonID';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $URL .= '&return=error2';
                            header("Location: {$URL}");
                            exit();
                        }

                        // Record file tracking
                        if (!empty($fileMetaData) && !empty($tawasulPersonID)) {
                            $tawasulFileID = $container->get(FileHandler::class)->recordFileUpload($fileMetaData, 'tawasulPerson', $tawasulPersonID, 'image_240');
                            
                            if (empty($tawasulFileID)) {
                                $imageFail = true;
                            }
                        }

                        // Handle file deletion when user removes attachment
                        if (empty($attachment1) && !empty($row['image_240'])) {
                            $deleted = $container->get(FileHandler::class)->deleteFile('tawasulPerson', $tawasulPersonID, 'image_240');
                        }

                        // Manage custom field file uploads
                        if (!empty($fields)) {
                            $params = compact('student', 'staff', 'parent', 'other');
                            $params['requiredOverride'] = 'N';
                            $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('User', $params, $fields, 'tawasulPerson', $tawasulPersonID, $row['fields'] ?? null);
                        }
                        
                        if ($row['status'] != $status) {
                            $statusReason = $_POST['statusReason'] ?? '';

                            $userStatusLogGateway = $container->get(UserStatusLogGateway::class);
                            $userStatusLogGateway->insert(['tawasulPersonID' => $tawasulPersonID, 'statusOld' => $row['status'], 'statusNew' => $status, 'reason' => $statusReason, 'tawasulPersonIDModified' => $session->get('tawasulPersonID')]);
                        }
                        
                        if (!empty($updateBackupPhoto) && !empty($attachment1)) {
                            $personPhotoGateway = $container->get(PersonPhotoGateway::class);

                            $photoUpdated = $personPhotoGateway->insertAndUpdate([
                                'tawasulPersonID' => $tawasulPersonID,
                                'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'),
                                'personImage' => $attachment1,
                                'tawasulPersonIDCreated' => $session->get('tawasulPersonID'),
                            ], [
                                'personImage' => $attachment1,
                                'tawasulPersonIDCreated' => $session->get('tawasulPersonID'),
                            ]);
                        }

                        // ALERTS: possible change to Privacy alert status, recalculate alerts
                        $container->get(Alert::class)->recalculateAlerts($tawasulPersonID);

                        //Deal with change to privacy settings
                        if ($student && $container->get(SettingGateway::class)->getSettingByScope('User Admin', 'privacy') == 'Y') {
                            if ($privacy_old != $privacy && !(empty($privacy_old) && empty($privacy))) {

                                //Notify tutor

                                    $dataDetail = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'), 'tawasulPersonID' => $tawasulPersonID);
                                    $sqlDetail = 'SELECT tawasulPersonIDTutor, tawasulPersonIDTutor2, tawasulPersonIDTutor3, tawasulYearGroupID FROM tawasulFormGroup JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID) JOIN tawasulPerson ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:tawasulSchoolYearID AND tawasulStudentEnrolment.tawasulPersonID=:tawasulPersonID';
                                    $resultDetail = $connection2->prepare($sqlDetail);
                                    $resultDetail->execute($dataDetail);
                                if ($resultDetail->rowCount() == 1) {

                                    $rowDetail = $resultDetail->fetch();

                                    // Initialize the notification sender & gateway objects
                                    $notificationGateway = $container->get(NotificationGateway::class);
                                    $notificationSender = $container->get(NotificationSender::class);

                                    // Raise a new notification event
                                    $event = new NotificationEvent('Students', 'Updated Privacy Settings');

                                    $staffName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);
                                    $studentName = Format::name('', $preferredName, $surname, 'Student', false);
                                    $actionLink = "/index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID=$tawasulPersonID&search=";

                                    $privacyText = __('Privacy').' (<i>'.__('New Value').'</i>): ';
                                    $privacyText .= !empty($privacy) ? $privacy : __('None');

                                    $notificationText = sprintf(__('%1$s has altered the privacy settings for %2$s.'), $staffName, $studentName).'<br/><br/>';
                                    $notificationText .= $privacyText;

                                    $event->setNotificationText($notificationText);
                                    $event->setActionLink($actionLink);

                                    $event->addScope('tawasulPersonIDStudent', $tawasulPersonID);
                                    $event->addScope('tawasulYearGroupID', $rowDetail['tawasulYearGroupID']);

                                    // Add event listeners to the notification sender
                                    $event->pushNotifications($notificationGateway, $notificationSender);

                                    // Add direct notifications to form group tutors
                                    if ($event->getEventDetails($notificationGateway, 'active') == 'Y') {
                                        $notificationText = sprintf(__('Your tutee, %1$s, has had their privacy settings altered.'), $studentName).'<br/><br/>';
                                        $notificationText .= $privacyText;

                                        if ($rowDetail['tawasulPersonIDTutor'] != null && $rowDetail['tawasulPersonIDTutor'] != $session->get('tawasulPersonID')) {
                                            $notificationSender->addNotification($rowDetail['tawasulPersonIDTutor'], $notificationText, 'Students', $actionLink);
                                        }
                                        if ($rowDetail['tawasulPersonIDTutor2'] != null && $rowDetail['tawasulPersonIDTutor2'] != $session->get('tawasulPersonID')) {
                                            $notificationSender->addNotification($rowDetail['tawasulPersonIDTutor2'], $notificationText, 'Students', $actionLink);
                                        }
                                        if ($rowDetail['tawasulPersonIDTutor3'] != null && $rowDetail['tawasulPersonIDTutor3'] != $session->get('tawasulPersonID')) {
                                            $notificationSender->addNotification($rowDetail['tawasulPersonIDTutor3'], $notificationText, 'Students', $actionLink);
                                        }
                                    }

                                    // Send all notifications
                                    $notificationSender->sendNotifications();
                                }

                                //Set log
                                $privacyValues=array() ;
                                $privacyValues['oldValue'] = $privacy_old ;
                                $privacyValues['newValue'] = $privacy ;
                                $logGateway->addLog($session->get("tawasulSchoolYearID"), 'User Admin', $session->get("tawasulPersonID"), 'Privacy - Value Changed', $privacyValues, $_SERVER['REMOTE_ADDR']) ;
                            }
                        }

                        //Update matching addresses
                        $partialFail = false;
                        $matchAddressCount = null;
                        if (isset($_POST['matchAddressCount'])) {
                            $matchAddressCount = $_POST['matchAddressCount'] ?? '';
                        }
                        if ($matchAddressCount > 0) {
                            for ($i = 0; $i < $matchAddressCount; ++$i) {
                                if (!empty($_POST[$i.'-matchAddress'])) {
                                    try {
                                        $dataAddress = array('address1' => $address1, 'address1District' => $address1District, 'address1Country' => $address1Country, 'tawasulPersonID' => $_POST[$i.'-matchAddress']);
                                        $sqlAddress = 'UPDATE tawasulPerson SET address1=:address1, address1District=:address1District, address1Country=:address1Country WHERE tawasulPersonID=:tawasulPersonID';
                                        $resultAddress = $connection2->prepare($sqlAddress);
                                        $resultAddress->execute($dataAddress);
                                    } catch (PDOException $e) {
                                        $partialFail = true;
                                    }
                                }
                            }
                        }
                        if ($partialFail || $personalDocumentFail) {
                            $URL .= '&return=warning1';
                            header("Location: {$URL}");
                        } else if ($imageFail) {
                            $URL .= '&return=warning3';
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
}
