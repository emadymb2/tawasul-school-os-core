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

use TawasulOS\Http\Url;
use TawasulOS\Forms\Form;
use TawasulOS\Services\Format;
use TawasulOS\Contracts\Comms\Mailer;
use TawasulOS\Data\UsernameGenerator;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Data\PasswordPolicy;
use TawasulOS\Domain\System\LogGateway;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\User\PersonalDocumentGateway;
use TawasulOS\Domain\Timetable\CourseEnrolmentGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/TawasulStudents/applicationForm_manage_accept.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $tawasulApplicationFormID = $_GET['tawasulApplicationFormID'] ?? '';
    $tawasulSchoolYearID = $_GET['tawasulSchoolYearID'] ?? '';
    $search = $_GET['search'] ?? '';

    $partialFailures = [];

    $page->breadcrumbs
        ->add(__('Manage Applications'), 'applicationForm_manage.php', ['tawasulSchoolYearID' => $tawasulSchoolYearID])
        ->add(__('Accept Application'));

    //Check if tawasulApplicationFormID and tawasulSchoolYearID specified
    if ($tawasulApplicationFormID == '' or $tawasulSchoolYearID == '') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {

            $data = array('tawasulApplicationFormID' => $tawasulApplicationFormID);
            $sql = "SELECT * FROM tawasulApplicationForm WHERE tawasulApplicationFormID=:tawasulApplicationFormID AND (status='Pending' OR status='Waiting List')";
            $result = $connection2->prepare($sql);
            $result->execute($data);

        if ($result->rowCount() != 1) {
            echo "<div class='error'>";
            echo __('The selected application does not exist or has already been processed.');
            echo '</div>';
        } else {
            // Grab family ID from Sibling Applications that have been accepted
            $data = array( 'tawasulApplicationFormID' => $tawasulApplicationFormID );
            $sql = "SELECT DISTINCT tawasulApplicationFormID, tawasulFamilyID FROM tawasulApplicationForm
                    JOIN tawasulApplicationFormLink ON (tawasulApplicationForm.tawasulApplicationFormID=tawasulApplicationFormLink.tawasulApplicationFormID1 OR tawasulApplicationForm.tawasulApplicationFormID=tawasulApplicationFormLink.tawasulApplicationFormID2)
                    WHERE tawasulApplicationForm.tawasulFamilyID IS NOT NULL
                    AND tawasulApplicationForm.status='Accepted'
                    AND (tawasulApplicationFormID1=:tawasulApplicationFormID OR tawasulApplicationFormID2=:tawasulApplicationFormID)
                    LIMIT 1";

            $resultLinked = $pdo->executeQuery($data, $sql);

            if ($resultLinked && $resultLinked->rowCount() == 1) {
                $linkedApplication = $resultLinked->fetch();
            }

            //Let's go!
            $values = $result->fetch();
            $step = '';
            if (isset($_GET['step'])) {
                $step = $_GET['step'] ?? '';
            }
            if ($step != 1 and $step != 2) {
                $step = 1;
            }

            $settingGateway = $container->get(SettingGateway::class);

            //Step 1
            if ($step == 1) {
                echo '<h3>';
                echo __('Step')." $step";
                echo '</h3>';

                if ($search != '') {
                    $params = [
                        "search" => $search,
                        "tawasulSchoolYearID" => $tawasulSchoolYearID
                    ];
                    $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulStudents', 'applicationForm_manage.php')->withQueryParams($params));
                }

                $form = Form::create('action', $session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module').'/applicationForm_manage_accept.php&step=2&tawasulApplicationFormID='.$tawasulApplicationFormID.'&tawasulSchoolYearID='.$tawasulSchoolYearID.'&search='.$search);

                $form->addHiddenValue('address', $session->get('address'));
                $form->addHiddenValue('tawasulApplicationFormID', $tawasulApplicationFormID);
                $form->addHiddenValue('tawasulSchoolYearID', $tawasulSchoolYearID);

                $col = $form->addRow()->addColumn()->addClass('stacked');

                $sqlSchoolYear = 'SELECT status FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearID';
                $entryYearStatus = $pdo->selectOne($sqlSchoolYear, ['tawasulSchoolYearID' => $values['tawasulSchoolYearIDEntry']]);
                if ($entryYearStatus == 'Upcoming') {
                    $col->addContent(Format::alert(__('Students and parents accepted to an upcoming school year will have their status set to "Expected", unless you choose to send a welcome email to them, in which case their status will be "Full".'), 'message'));
                }

                $applicantName = Format::name('', $values['preferredName'], $values['surname'], 'Student');
                $col->addContent(sprintf(__('Are you sure you want to accept the application for %1$s?'), $applicantName))->wrap('<b>', '</b>');

                $informStudent = ($settingGateway->getSettingByScope('Application Form', 'notificationStudentDefault') == 'Y');
                $col->addCheckbox('informStudent')
                    ->description(__('Automatically inform <u>student</u> of TawasulOS login details by email?'))
                    ->inline(true)
                    ->checked($informStudent)
                    ->setClass('');

                $informParents = ($settingGateway->getSettingByScope('Application Form', 'notificationParentsDefault') == 'Y');
                $col->addCheckbox('informParents')
                    ->description(__('Automatically inform <u>parents</u> of their TawasulOS login details by email?'))
                    ->inline(true)
                    ->checked($informParents)
                    ->setClass('');

                $col->addContent(__('The system will perform the following actions:'))->wrap('<i><u>', '</u></i>');
                $list = $col->addContent();

                $list->append('<li>'.__('Create a TawasulOS user account for the student.').'</li>');

                if (!empty($values['tawasulFormGroupID'])) {
                    $list->append('<li>'.__('Enrol the student in the selected school year (as the student has been assigned to a form group).').'</li>');
                }

                if (!empty($values['tawasulFamilyID']) || !empty($linkedApplication['tawasulFamilyID'])) {
                    $list->append('<li>'.__('Link student to family (who are already in TawasulOS).').'</li>');
                } else {
                    $list->append('<li>'.__('Create a new family.').'</li>')
                         ->append('<li>'.__('Create user accounts for the parents.').'</li>')
                         ->append('<li>'.__('Link student and parents to the family.').'</li>');
                }

                $list->append('<li>'.__('Create a medical record for the student.').'</li>')
                     ->append('<li>'.__('Save the student\'s payment preferences.').'</li>')
                     ->append('<li>'.__('Set the status of the application to "Accepted".').'</li>');

                $list->wrap('<ol>', '</ol>');

                // Handle optional auto-enrol feature
                if (!empty($values['tawasulFormGroupID'])) {
                    $data = array('tawasulFormGroupID' => $values['tawasulFormGroupID']);
                    $sql = "SELECT COUNT(*) FROM tawasulCourseClassMap WHERE tawasulFormGroupID=:tawasulFormGroupID";
                    $resultClassMap = $pdo->executeQuery($data, $sql);
                    $classMapCount = ($resultClassMap->rowCount() > 0)? $resultClassMap->fetchColumn(0) : 0;

                    // Student has a form group and mapped classes exist
                    if ($classMapCount > 0) {
                        $autoEnrolStudent = ($settingGateway->getSettingByScope('Timetable Admin', 'autoEnrolCourses') == 'Y');

                        $col->addContent(__('The system can optionally perform the following actions:'))->wrap('<i><u>', '</u></i>');
                        $col->addCheckbox('autoEnrolStudent')
                            ->description(__('Automatically enrol student in classes for Form Group.'))
                            ->inline(true)
                            ->setValue('Y')
                            ->checked($autoEnrolStudent? 'Y' : 'N')
                            ->setClass('')
                            ->wrap('<ol><li>', '</li></ol>');
                    }
                }

                $col->addContent(__('But you may wish to manually do the following:'))->wrap('<i><u>', '</u></i>');
                $list = $col->addContent();

                if (empty($values['tawasulFormGroupID'])) {
                    $list->append('<li>'.__('Enrol the student in the selected school year (as the student has not been assigned to a form group).').'</li>');
                }

                $list->append('<li>'.__('Create an individual needs record for the student.').'</li>')
                     ->append('<li>'.__('Create a note of the student\'s scholarship information outside of TawasulOS.').'</li>')
                     ->append('<li>'.__('Create a timetable for the student.').'</li>');

                $list->wrap('<ol>', '</ol>');

                $form->addRow()->addSubmit(__('Accept'));

                echo $form->getOutput();

            } elseif ($step == 2) {
                echo '<h3>';
                echo __('Step')." $step";
                echo '</h3>';

                if ($search != '') {
                    $params = [
                        "search" => $search,
                        "tawasulSchoolYearID" => $tawasulSchoolYearID
                    ];
                    $page->navigator->addSearchResultsAction(Url::fromModuleRoute('TawasulStudents', 'applicationForm_manage.php')->withQueryParams($params));
                }

                //Set up variables for automatic email to participants, if selected in Step 1.
                $informParents = 'N';
                if (isset($_POST['informParents'])) {
                    if ($_POST['informParents'] == 'on') {
                        $informParents = 'Y';
                        $informParentsArray = array();
                    }
                }
                $informStudent = 'N';
                if (isset($_POST['informStudent'])) {
                    if ($_POST['informStudent'] == 'on') {
                        $informStudent = 'Y';
                        $informStudentArray = array();
                    }
                }

                //CREATE STUDENT
                $failStudent = true;

                // Generate a unique username for the new student, or use the pre-defined one.
                if (!empty($values['username'])) {
                    $username = $values['username'];
                } else {
                    $generator = new UsernameGenerator($pdo);
                    $generator->addToken('preferredName', $values['preferredName']);
                    $generator->addToken('firstName', $values['firstName']);
                    $generator->addToken('surname', $values['surname']);

                    $username = $generator->generateByRole('003');
                }

                // Generate a random password from site's password policy.
                /** @var PasswordPolicy */
                $passwordPolicy = $container->get(PasswordPolicy::class);
                $password = $passwordPolicy->generate();
                $salt = getSalt();
                $passwordStrong = hash('sha256', $salt.$password);

                $lastSchool = '';
                if ($values['schoolDate1'] > $values['schoolDate2']) {
                    $lastSchool = $values['schoolName1'];
                } elseif ($values['schoolDate2'] > $values['schoolDate1']) {
                    $lastSchool = $values['schoolName2'];
                }

                $continueLoop = !(!empty($username) && $username != 'usernamefailed' && !empty($password));

                // Use the pre-defined student ID, otherwise set it to an empty string (not null).
                $values['studentID'] = $values['studentID'] ?? '';

                //Set default email address for student
                $email = $values['email'];
                $emailAlternate = '';
                $studentDefaultEmail = $settingGateway->getSettingByScope('Application Form', 'studentDefaultEmail');
                if ($studentDefaultEmail != '') {
                    $emailAlternate = $email;
                    $email = str_replace('[username]', $username, $studentDefaultEmail);
                }

                //Set default website address for student
                $website = '';
                $studentDefaultWebsite = $settingGateway->getSettingByScope('Application Form', 'studentDefaultWebsite');
                if ($studentDefaultWebsite != '') {
                    $website = str_replace('[username]', $username, $studentDefaultWebsite);
                }

                // Get student's school year at entry info
                $dataSchoolYear = array('tawasulSchoolYearID' => $values['tawasulSchoolYearIDEntry']);
                $sqlSchoolYear = 'SELECT name, status FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearID';
                $resultSchoolYear = $connection2->prepare($sqlSchoolYear);
                $resultSchoolYear->execute($dataSchoolYear);
                $schoolYearEntry = $resultSchoolYear->fetch();
                $schoolYearName = $schoolYearEntry['name'] ?? '';
                $status = $schoolYearEntry['status'] == 'Upcoming' && $informStudent != 'Y' ? 'Expected' : 'Full';

                // Get student's year group info
                $dataYearGroup = array('tawasulYearGroupID' => $values['tawasulYearGroupIDEntry']);
                $sqlYearGroup = 'SELECT name FROM tawasulYearGroup WHERE tawasulYearGroupID=:tawasulYearGroupID';
                $resultYearGroup = $connection2->prepare($sqlYearGroup);
                $resultYearGroup->execute($dataYearGroup);
                $yearGroupName = ($resultYearGroup->rowCount() == 1)? $resultYearGroup->fetchColumn(0) : '';

                // Get student's form group info (if any)
                $dataFormGroup = array('tawasulFormGroupID' => $values['tawasulFormGroupID']);
                $sqlFormGroup = 'SELECT name FROM tawasulFormGroup WHERE tawasulFormGroupID=:tawasulFormGroupID';
                $resultFormGroup = $connection2->prepare($sqlFormGroup);
                $resultFormGroup->execute($dataFormGroup);
                $formGroupName = ($resultFormGroup->rowCount() == 1)? $resultFormGroup->fetchColumn(0) : '';

                //Email website and email address to admin for creation
                if ($studentDefaultEmail != '' or $studentDefaultWebsite != '') {
                    echo '<h4>';
                    echo __('Student Email & Website');
                    echo '</h4>';
                    $to = $session->get('organisationAdministratorEmail');
                    $subject = sprintf(__('Create Student Email/Websites for %1$s at %2$s'), $session->get('systemName'), $session->get('organisationNameShort'));
                    $body = sprintf(__('Please create the following for new student %1$s.'), Format::name('', $values['preferredName'], $values['surname'], 'Student'))."<br/><br/>";
                    if ($studentDefaultEmail != '') {
                        $body .= __('Email').': '.$email."<br/>";
                    }
                    if ($studentDefaultWebsite != '') {
                        $body .= __('Website').': '.$website."<br/>";
                    }
                    if ($values['tawasulSchoolYearIDEntry'] != '' && !empty($schoolYearName)) {
                        $body .= __('School Year').': '.$schoolYearName."<br/>";
                    }
                    if ($values['tawasulYearGroupIDEntry'] != '' && !empty($yearGroupName)) {
                        $body .= __('Year Group').': '.$yearGroupName."<br/>";
                    }
                    if ($values['tawasulFormGroupID'] != '' && !empty($formGroupName)) {
                        $body .= __('Form Group').': '.$formGroupName."<br/>";
                    }
                    if ($values['dateStart'] != '') {
                        $body .= __('Start Date').': '.Format::date($values['dateStart'])."<br/>";
                    }

                    $mail = $container->get(Mailer::class);
                    $mail->SetFrom($session->get('organisationAdministratorEmail'), $session->get('organisationAdministratorName'));
                    $mail->AddAddress($to);
                    $mail->Subject = $subject;
                    $mail->renderBody('mail/email.twig.html', [
                        'title'  => $subject,
                        'body'   => $body,
                    ]);

                    if ($mail->Send()) {
                        echo "<div class='success'>";
                        echo sprintf(__('A request to create a student email address and/or website address was successfully sent to %1$s.'), $session->get('organisationAdministratorName'));
                        echo '</div>';
                    } else {
                        echo "<div class='error'>";
                        echo sprintf(__('A request to create a student email address and/or website address failed. Please contact %1$s to request these manually.'), $session->get('organisationAdministratorName'));
                        echo '</div>';
                    }
                }

                //ATTEMPT AUTOMATIC HOUSE ASSIGNMENT
                $tawasulHouseID = null;
                $house = '';
                if ($settingGateway->getSettingByScope('Application Form', 'autoHouseAssign') == 'Y') {
                    $houseFail = false;
                    if ($values['tawasulYearGroupIDEntry'] == '' or $values['tawasulSchoolYearIDEntry'] == '' and $values['gender'] == '') { //No year group or school year set, so return error
                        $houseFail = true;
                        $partialFailures[] = 'houseFail';
                    } else {
                        //Check boys and girls in each house in year group
                        try {
                            $dataHouse = array('tawasulYearGroupID' => $values['tawasulYearGroupIDEntry'], 'tawasulSchoolYearID' => $values['tawasulSchoolYearIDEntry'], 'gender' => $values['gender']);
                            $sqlHouse = "SELECT tawasulHouse.name AS house, tawasulHouse.tawasulHouseID, count(DISTINCT tawasulPerson.tawasulPersonID) AS count
                                FROM tawasulHouse
                                    LEFT JOIN tawasulPerson ON (tawasulPerson.tawasulHouseID=tawasulHouse.tawasulHouseID AND gender=:gender AND status='Full')
                                    LEFT JOIN tawasulStudentEnrolment ON (tawasulStudentEnrolment.tawasulPersonID=tawasulPerson.tawasulPersonID
                                        AND tawasulSchoolYearID=:tawasulSchoolYearID
                                        AND tawasulYearGroupID=:tawasulYearGroupID)
                                WHERE tawasulHouse.tawasulHouseID IS NOT NULL
                                GROUP BY house, tawasulHouse.tawasulHouseID
                                ORDER BY count, RAND(), tawasulHouse.tawasulHouseID";
                            $resultHouse = $connection2->prepare($sqlHouse);
                            $resultHouse->execute($dataHouse);
                        } catch (PDOException $e) {
                            $houseFail = true;
                            $partialFailures[] = 'houseFail';
                        }
                        if ($resultHouse->rowCount() > 0) {
                            $rowHouse = $resultHouse->fetch();
                            $tawasulHouseID = $rowHouse['tawasulHouseID'];
                            $house = $rowHouse['house'];
                        } else {
                            $houseFail = true;
                            $partialFailures[] = 'houseFail';
                        }
                    }

                    if ($houseFail == true) {
                        echo "<div class='warning'>";
                        echo __('The student could not automatically be added to a house, you may wish to manually add them to a house.');
                        echo '</div>';
                    } else {
                        echo "<div class='success'>";
                        echo sprintf(__('The student has automatically been assigned to %1$s house.'), $house);
                        echo '</div>';
                    }
                }

                if ($continueLoop == false) {
                    $insertOK = true;
                    try {
                        $data = array('username' => $username, 'passwordStrong' => $passwordStrong, 'passwordStrongSalt' => $salt, 'status' => $status, 'surname' => $values['surname'], 'firstName' => $values['firstName'], 'preferredName' => $values['preferredName'], 'officialName' => $values['officialName'], 'nameInCharacters' => $values['nameInCharacters'], 'gender' => $values['gender'], 'dob' => $values['dob'], 'languageFirst' => $values['languageFirst'], 'languageSecond' => $values['languageSecond'], 'languageThird' => $values['languageThird'], 'countryOfBirth' => $values['countryOfBirth'], 'email' => $email, 'emailAlternate' => $emailAlternate, 'website' => $website, 'phone1Type' => $values['phone1Type'], 'phone1CountryCode' => $values['phone1CountryCode'], 'phone1' => $values['phone1'], 'phone2Type' => $values['phone2Type'], 'phone2CountryCode' => $values['phone2CountryCode'], 'phone2' => $values['phone2'], 'lastSchool' => $lastSchool, 'dateStart' => $values['dateStart'], 'privacy' => $values['privacy'], 'dayType' => $values['dayType'], 'tawasulHouseID' => $tawasulHouseID, 'studentID' => $values['studentID'], 'fields' => $values['fields']);
                        $sql = "INSERT INTO tawasulPerson SET username=:username, passwordStrong=:passwordStrong, passwordStrongSalt=:passwordStrongSalt, tawasulRoleIDPrimary='003', tawasulRoleIDAll='003', status=:status, surname=:surname, firstName=:firstName, preferredName=:preferredName, officialName=:officialName, nameInCharacters=:nameInCharacters, gender=:gender, dob=:dob, languageFirst=:languageFirst, languageSecond=:languageSecond, languageThird=:languageThird, countryOfBirth=:countryOfBirth, email=:email, emailAlternate=:emailAlternate, website=:website, phone1Type=:phone1Type, phone1CountryCode=:phone1CountryCode, phone1=:phone1, phone2Type=:phone2Type, phone2CountryCode=:phone2CountryCode, phone2=:phone2, lastSchool=:lastSchool, dateStart=:dateStart, privacy=:privacy, dayType=:dayType, tawasulHouseID=:tawasulHouseID, studentID=:studentID, fields=:fields";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $insertOK = false;
                        $partialFailures[] = 'insertOK';
                    }
                    if ($insertOK == true) {
                        $tawasulPersonID = $connection2->lastInsertID();

                        $failStudent = false;

                        //Populate informStudent array
                        if ($informStudent == 'Y') {
                            $informStudentArray[0]['email'] = $values['email'];
                            $informStudentArray[0]['surname'] = $values['surname'];
                            $informStudentArray[0]['preferredName'] = $values['preferredName'];
                            $informStudentArray[0]['username'] = $username;
                            $informStudentArray[0]['password'] = $password;
                        }

                        // Update personal document ownership
                        $container->get(PersonalDocumentGateway::class)->updatePersonalDocumentOwnership('tawasulApplicationForm', $tawasulApplicationFormID, 'tawasulPerson', $tawasulPersonID);
                    }
                }


                if ($failStudent == true) {
                    echo "<div class='error'>";
                    echo __('Student could not be created!');
                    echo '</div>';
                } else {
                    echo '<h4>';
                    echo __('Student Details');
                    echo '</h4>';
                    echo '<ul>';
                    echo "<li><b>tawasulPersonID</b>: $tawasulPersonID</li>";
                    echo '<li><b>'.__('Name').'</b>: '.Format::name('', $values['preferredName'], $values['surname'], 'Student').'</li>';
                    echo '<li><b>'.__('Email').'</b>: '.$email.'</li>';
                    echo '<li><b>'.__('Email Alternate').'</b>: '.$emailAlternate.'</li>';
                    echo '<li><b>'.__('Username')."</b>: $username</li>";
                    echo '<li><b>'.__('Password')."</b>: $password</li>";
                    echo '</ul>';

                    //Move documents to student notes

                        $dataDoc = array('tawasulApplicationFormID' => $tawasulApplicationFormID);
                        $sqlDoc = 'SELECT * FROM tawasulApplicationFormFile WHERE tawasulApplicationFormID=:tawasulApplicationFormID';
                        $resultDoc = $connection2->prepare($sqlDoc);
                        $resultDoc->execute($dataDoc);
                    if ($resultDoc->rowCount() > 0) {
                        $note = '<p>';
                        while ($rowDoc = $resultDoc->fetch()) {
                            $note .= "<a href='".$session->get('absoluteURL').'/'.$rowDoc['path']."'>".$rowDoc['name'].'</a><br/>';
                        }
                        $note .= '</p>';

                            $data = array('tawasulPersonID' => $tawasulPersonID, 'title' => __('Application Documents'), 'note' => $note, 'tawasulPersonIDCreator' => $session->get('tawasulPersonID'), 'timestamp' => date('Y-m-d H:i:s'));
                            $sql = 'INSERT INTO tawasulStudentNote SET tawasulPersonID=:tawasulPersonID, tawasulStudentNoteCategoryID=NULL, title=:title, note=:note, tawasulPersonIDCreator=:tawasulPersonIDCreator, timestamp=:timestamp';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                    }

                    //Create medical record if possible
                    $data = array('tawasulPersonID' => $tawasulPersonID, 'comment' => $values['medicalInformation']);
                    $sql = 'INSERT INTO tawasulPersonMedical SET tawasulPersonID=:tawasulPersonID, comment=:comment';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);

                    //Enrol student
                    $enrolmentOK = true;
                    if ($values['tawasulFormGroupID'] != '') {
                        if ($tawasulPersonID != '' and $values['tawasulSchoolYearIDEntry'] != '' and $values['tawasulYearGroupIDEntry'] != '') {
                            try {
                                $data = array('tawasulPersonID' => $tawasulPersonID, 'tawasulSchoolYearID' => $values['tawasulSchoolYearIDEntry'], 'tawasulYearGroupID' => $values['tawasulYearGroupIDEntry'], 'tawasulFormGroupID' => $values['tawasulFormGroupID']);
                                $sql = 'INSERT INTO tawasulStudentEnrolment SET tawasulPersonID=:tawasulPersonID, tawasulSchoolYearID=:tawasulSchoolYearID, tawasulYearGroupID=:tawasulYearGroupID, tawasulFormGroupID=:tawasulFormGroupID';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $enrolmentOK = false;
                            }
                        } else {
                            $enrolmentOK = false;
                            $partialFailures[] = 'enrolmentOK';
                        }

                        //Report back
                        if ($enrolmentOK == false) {
                            echo "<div class='warning'>";
                            echo __('Student could not be enrolled, so this will have to be done manually at a later date.');
                            echo '</div>';
                        } else {
                            echo '<h4>';
                            echo __('Student Enrolment');
                            echo '</h4>';
                            echo '<ul>';
                            echo '<li>'.__('The student has successfully been enrolled in the specified school year, year group and form group.').'</li>';

                            // Handle automatic course enrolment if enabled
                            $autoEnrolStudent = $_POST['autoEnrolStudent'] ?? 'N';
                            if ($autoEnrolStudent == 'Y') {
                                $enrolmentDate = $pdo->selectOne("SELECT GREATEST((SELECT firstDay FROM tawasulSchoolYear WHERE tawasulSchoolYearID=:tawasulSchoolYearIDEntry), CURRENT_DATE)", ['tawasulSchoolYearIDEntry' => $values['tawasulSchoolYearIDEntry']]);

                                $inserted = $container->get(CourseEnrolmentGateway::class)->insertAutomaticCourseEnrolments($values['tawasulFormGroupID'], $tawasulPersonID, $enrolmentDate);

                                if (!$inserted) {
                                    echo '<li class="warning">'.__('Student could not be automatically enrolled in courses, so this will have to be done manually at a later date.').'</li>';
                                    $partialFailures[] = 'autoEnrolStudent';
                                } else {
                                    echo '<li>'.__('The student has automatically been enrolled in courses for their Form Group.').'</li>';
                                }
                            }

                            echo '</ul>';
                        }
                    }

                    //SAVE PAYMENT PREFERENCES
                    $failPayment = true;
                    $invoiceTo = $values['payment'];
                    if ($invoiceTo == 'Company') {
                        $companyName = $values['companyName'];
                        $companyContact = $values['companyContact'];
                        $companyAddress = $values['companyAddress'];
                        $companyEmail = $values['companyEmail'];
                        $companyPhone = $values['companyPhone'];
                        $companyAll = $values['companyAll'];
                        $tawasulFinanceFeeCategoryIDList = null;
                        if ($companyAll == 'N') {
                            $tawasulFinanceFeeCategoryIDList = '';
                            $tawasulFinanceFeeCategoryIDArray = explode(',', $values['tawasulFinanceFeeCategoryIDList']);
                            if (count($tawasulFinanceFeeCategoryIDArray) > 0) {
                                foreach ($tawasulFinanceFeeCategoryIDArray as $tawasulFinanceFeeCategoryID) {
                                    $tawasulFinanceFeeCategoryIDList .= $tawasulFinanceFeeCategoryID.',';
                                }
                                $tawasulFinanceFeeCategoryIDList = substr($tawasulFinanceFeeCategoryIDList, 0, -1);
                            }
                        }
                    } else {
                        $companyName = null;
                        $companyContact = null;
                        $companyAddress = null;
                        $companyEmail = null;
                        $companyPhone = null;
                        $companyAll = null;
                        $tawasulFinanceFeeCategoryIDList = null;
                    }
                    $paymentOK = true;
                    try {
                        $data = array('tawasulPersonID' => $tawasulPersonID, 'invoiceTo' => $invoiceTo, 'companyName' => $companyName, 'companyContact' => $companyContact, 'companyAddress' => $companyAddress, 'companyEmail' => $companyEmail, 'companyPhone' => $companyPhone, 'companyAll' => $companyAll, 'tawasulFinanceFeeCategoryIDList' => $tawasulFinanceFeeCategoryIDList);
                        $sql = 'INSERT INTO tawasulFinanceInvoicee SET tawasulPersonID=:tawasulPersonID, invoiceTo=:invoiceTo, companyName=:companyName, companyContact=:companyContact, companyAddress=:companyAddress, companyEmail=:companyEmail, companyPhone=:companyPhone, companyAll=:companyAll, tawasulFinanceFeeCategoryIDList=:tawasulFinanceFeeCategoryIDList';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $paymentOK = false;
                        $partialFailures[] = 'paymentOK';
                    }

                    if ($paymentOK == false) {
                        echo "<div class='warning'>";
                        echo __('Student payment details could not be saved, but we will continue, as this is a minor issue.');
                        echo '</div>';
                    }

                    $failFamily = true;
                    if (!empty($values['tawasulFamilyID']) || !empty($linkedApplication['tawasulFamilyID'])) {

                        if (empty($values['tawasulFamilyID'])) {
                            // Associate the application with the tawasulFamilyID from linked application
                            $values['tawasulFamilyID'] = $linkedApplication['tawasulFamilyID'];
                        }

                        //CONNECT STUDENT TO FAMILY

                            $dataFamily = array('tawasulFamilyID' => $values['tawasulFamilyID']);
                            $sqlFamily = 'SELECT * FROM tawasulFamily WHERE tawasulFamilyID=:tawasulFamilyID';
                            $resultFamily = $connection2->prepare($sqlFamily);
                            $resultFamily->execute($dataFamily);
                        if ($resultFamily->rowCount() == 1) {
                            $rowFamily = $resultFamily->fetch();
                            $familyName = $rowFamily['name'];
                            if ($familyName != '') {
                                $insertFail = false;
                                try {
                                    $data = array('tawasulPersonID' => $tawasulPersonID, 'tawasulFamilyID' => $values['tawasulFamilyID']);
                                    $sql = 'INSERT INTO tawasulFamilyChild SET tawasulPersonID=:tawasulPersonID, tawasulFamilyID=:tawasulFamilyID';
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                                } catch (PDOException $e) {
                                    $insertFail == true;
                                    $partialFailures[] = 'failFamily1';
                                }
                                if ($insertFail == false) {
                                    $failFamily = false;
                                }
                            }
                        }

                        // Linked application only: try to find existing parents in this family
                        if (!empty($linkedApplication['tawasulApplicationFormID'])) {

                            for ($i = 1; $i <= 2; $i++) {
                                // Attempt to find parents using surname, preferredName within the existing family adults
                                if (empty($values["parent{$i}tawasulPersonID"])) {
                                    try {
                                        $dataParent = array('tawasulFamilyID' => $values['tawasulFamilyID'], 'parentSurname' => $values["parent{$i}surname"], 'parentPreferredName' => $values["parent{$i}preferredName"]);
                                        $sqlParent = 'SELECT tawasulPerson.tawasulPersonID FROM tawasulFamilyAdult JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulFamilyID=:tawasulFamilyID AND surname=:parentSurname AND preferredName=:parentPreferredName';
                                        $resultParent = $pdo->executeQuery($dataParent, $sqlParent);
                                    } catch (PDOException $e) {
                                    }

                                    if (isset($resultParent) && $resultParent->rowCount() == 1) {
                                        // Record the found ID -- otherwise the parent creation code further down will kick in
                                        $values["parent{$i}tawasulPersonID"] = $resultParent->fetchColumn(0);

                                        //Set parent relationship
                                        try {
                                            $dataParent = array('tawasulFamilyID' => $values['tawasulFamilyID'], 'tawasulPersonID1' => $values["parent{$i}tawasulPersonID"], 'tawasulPersonID2' => $tawasulPersonID, 'relationship' => $values["parent{$i}relationship"]);
                                            $sqlParent = 'INSERT INTO tawasulFamilyRelationship SET tawasulFamilyID=:tawasulFamilyID, tawasulPersonID1=:tawasulPersonID1, tawasulPersonID2=:tawasulPersonID2, relationship=:relationship';
                                            $resultParentRelationship = $pdo->executeQuery($dataParent, $sqlParent);
                                        } catch (PDOException $e) {
                                        }
                                    }
                                }
                            }
                        }


                            $dataParents = array('tawasulFamilyID' => $values['tawasulFamilyID']);
                            $sqlParents = 'SELECT tawasulFamilyAdult.*, tawasulPerson.tawasulRoleIDAll FROM tawasulFamilyAdult JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulFamilyID=:tawasulFamilyID';
                            $resultParents = $connection2->prepare($sqlParents);
                            $resultParents->execute($dataParents);
                        while ($rowParents = $resultParents->fetch()) {
                            //Update parent roles
                            if (strpos($rowParents['tawasulRoleIDAll'], '004') === false) {

                                    $dataRoleUpdate = array('tawasulPersonID' => $rowParents['tawasulPersonID']);
                                    $sqlRoleUpdate = "UPDATE tawasulPerson SET tawasulRoleIDAll=concat(tawasulRoleIDAll, ',004') WHERE tawasulPersonID=:tawasulPersonID";
                                    $resultRoleUpdate = $connection2->prepare($sqlRoleUpdate);
                                    $resultRoleUpdate->execute($dataRoleUpdate);
                            }

                            //Add relationship record for each parent

                                $dataRelationship = array('tawasulApplicationFormID' => $tawasulApplicationFormID, 'tawasulPersonID' => $rowParents['tawasulPersonID']);
                                $sqlRelationship = 'SELECT * FROM tawasulApplicationFormRelationship WHERE tawasulApplicationFormID=:tawasulApplicationFormID AND tawasulPersonID=:tawasulPersonID';
                                $resultRelationship = $connection2->prepare($sqlRelationship);
                                $resultRelationship->execute($dataRelationship);
                            if ($resultRelationship->rowCount() == 1) {
                                $rowRelationship = $resultRelationship->fetch();
                                $relationship = $rowRelationship['relationship'];

                                    $data = array('tawasulFamilyID' => $values['tawasulFamilyID'], 'tawasulPersonID1' => $rowParents['tawasulPersonID'], 'tawasulPersonID2' => $tawasulPersonID);
                                    $sql = 'SELECT * FROM tawasulFamilyRelationship WHERE tawasulFamilyID=:tawasulFamilyID AND tawasulPersonID1=:tawasulPersonID1 AND tawasulPersonID2=:tawasulPersonID2';
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                                if ($result->rowCount() == 0) {

                                        $data = array('tawasulFamilyID' => $values['tawasulFamilyID'], 'tawasulPersonID1' => $rowParents['tawasulPersonID'], 'tawasulPersonID2' => $tawasulPersonID, 'relationship' => $relationship);
                                        $sql = 'INSERT INTO tawasulFamilyRelationship SET tawasulFamilyID=:tawasulFamilyID, tawasulPersonID1=:tawasulPersonID1, tawasulPersonID2=:tawasulPersonID2, relationship=:relationship';
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                } elseif ($result->rowCount() == 1) {
                                    $existingRelationship = $result->fetch();

                                    if ($existingRelationship['relationship'] != $relationship) {

                                            $data = array('relationship' => $relationship, 'tawasulFamilyRelationshipID' => $existingRelationship['tawasulFamilyRelationshipID']);
                                            $sql = 'UPDATE tawasulFamilyRelationship SET relationship=:relationship WHERE tawasulFamilyRelationshipID=:tawasulFamilyRelationshipID';
                                            $result = $connection2->prepare($sql);
                                            $result->execute($data);
                                    }
                                } else {
                                }
                            }
                        }

                        if ($failFamily == true) {
                            echo "<div class='warning'>";
                            echo __('Student could not be linked to family!');
                            echo '</div>';
                            $partialFailures[] = 'failFamily2';
                        } else {
                            echo '<h4>';
                            echo __('Family');
                            echo '</h4>';
                            echo '<ul>';
                            echo '<li><b>tawasulFamilyID</b>: '.$values['tawasulFamilyID'].'</li>';
                            echo '<li><b>'.__('Family Name')."</b>: $familyName </li>";
                            echo '<li><b>'.__('Roles').'</b>: '.__('System has tried to assign parents "Parent" role access if they did not already have it.').'</li>';
                            echo '</ul>';

                            // Update the application information with the linked family ID, when connecting Sibling Applications 
                            $data = array('tawasulApplicationFormID' => $tawasulApplicationFormID, 'tawasulFamilyID' => $values['tawasulFamilyID']);
                            $sql = 'UPDATE tawasulApplicationForm SET tawasulFamilyID=:tawasulFamilyID WHERE tawasulApplicationFormID=:tawasulApplicationFormID';
                            $resultUpdateFamilyID = $pdo->executeQuery($data, $sql);
                        }
                    } else {
                        //CREATE A NEW FAMILY
                        $failFamily = true;

                        $familyName = $values['parent1preferredName'].' '.$values['parent1surname'];
                        if ($values['parent2preferredName'] != '' and $values['parent2surname'] != '') {
                            $familyName .= ' & '.$values['parent2preferredName'].' '.$values['parent2surname'];
                        }
                        $nameAddress = '';
                        //Parents share same surname and parent 2 has enough information to be added
                        if ($values['parent1surname'] == $values['parent2surname'] and $values['parent2preferredName'] != '' and $values['parent2title'] != '') {
                            $nameAddress = $values['parent1title'].' & '.$values['parent2title'].' '.$values['parent1surname'];
                        }
                        //Parents have different names, and parent2 is not blank and has enough information to be added
                        elseif ($values['parent1surname'] != $values['parent2surname'] and $values['parent2surname'] != '' and $values['parent2preferredName'] != '' and $values['parent2title'] != '') {
                            $nameAddress = $values['parent1title'].' '.$values['parent1surname'].' & '.$values['parent2title'].' '.$values['parent2surname'];
                        }
                        //Just use parent1's name
                        else {
                            $nameAddress = $values['parent1title'].' '.$values['parent1surname'];
                        }
                        $languageHomePrimary = $values['languageHomePrimary'];
                        $languageHomeSecondary = $values['languageHomeSecondary'];

                        $insertOK = true;
                        try {
                            $data = array('familyName' => $familyName, 'nameAddress' => $nameAddress, 'languageHomePrimary' => $languageHomePrimary, 'languageHomeSecondary' => $languageHomeSecondary, 'homeAddress' => $values['homeAddress'], 'homeAddressDistrict' => $values['homeAddressDistrict'], 'homeAddressCountry' => $values['homeAddressCountry']);
                            $sql = 'INSERT INTO tawasulFamily SET name=:familyName, nameAddress=:nameAddress, languageHomePrimary=:languageHomePrimary, languageHomeSecondary=:languageHomeSecondary, homeAddress=:homeAddress, homeAddressDistrict=:homeAddressDistrict, homeAddressCountry=:homeAddressCountry';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            $insertOK = false;
                            $partialFailures[] = 'failFamily3';
                        }

                        if ($insertOK == true) {
                            $failFamily = false;

                            $tawasulFamilyID = $connection2->lastInsertID();
                        }

                        if ($failFamily == true) {
                            echo "<div class='error'>";
                            echo __('Family could not be created!');
                            echo '</div>';
                        } else {
                            echo '<h4>';
                            echo __('Family Details');
                            echo '</h4>';
                            echo '<ul>';
                            echo "<li><b>tawasulFamilyID</b>: $tawasulFamilyID</li>";
                            echo '<li><b>'.__('Family Name')."</b>: $familyName</li>";
                            echo '<li><b>'.__('Address Name')."</b>: $nameAddress</li>";
                            echo '</ul>';

                            //LINK STUDENT INTO FAMILY
                            $failFamily = true;
                            if ($tawasulFamilyID != '') {

                                    $dataFamily = array('tawasulFamilyID' => $tawasulFamilyID);
                                    $sqlFamily = 'SELECT * FROM tawasulFamily WHERE tawasulFamilyID=:tawasulFamilyID';
                                    $resultFamily = $connection2->prepare($sqlFamily);
                                    $resultFamily->execute($dataFamily);

                                if ($resultFamily->rowCount() == 1) {
                                    $rowFamily = $resultFamily->fetch();
                                    $familyName = $rowFamily['name'];
                                    if ($familyName != '') {
                                        $insertOK = true;
                                        try {
                                            $data = array('tawasulPersonID' => $tawasulPersonID, 'tawasulFamilyID' => $tawasulFamilyID);
                                            $sql = 'INSERT INTO tawasulFamilyChild SET tawasulPersonID=:tawasulPersonID, tawasulFamilyID=:tawasulFamilyID';
                                            $result = $connection2->prepare($sql);
                                            $result->execute($data);
                                        } catch (PDOException $e) {
                                            $insertOK = false;
                                            $partialFailures[] = 'failFamily4';
                                        }
                                        if ($insertOK == true) {
                                            $failFamily = false;
                                        }
                                    }
                                }

                                if ($failFamily == true) {
                                    echo "<div class='warning'>";
                                    echo __('Student could not be linked to family!');
                                    echo '</div>';
                                } else {
                                    // Update the application information with the newly created family ID, for Sibling Applications to use
                                    $data = array('tawasulApplicationFormID' => $tawasulApplicationFormID, 'tawasulFamilyID' => $tawasulFamilyID);
                                    $sql = 'UPDATE tawasulApplicationForm SET tawasulFamilyID=:tawasulFamilyID WHERE tawasulApplicationFormID=:tawasulApplicationFormID';
                                    $resultUpdateFamilyID = $pdo->executeQuery($data, $sql);
                                }
                            }

                            //CREATE PARENT 1
                            $failParent1 = true;
                            if ($values['parent1tawasulPersonID'] != '') {
                                $tawasulPersonIDParent1 = $values['parent1tawasulPersonID'];
                                echo '<h4>';
                                echo 'Parent 1';
                                echo '</h4>';
                                echo '<ul>';
                                echo '<li>'.__('Parent 1 already exists in TawasulOS, and so does not need a new account.').'</li>';
                                echo "<li><b>tawasulPersonID</b>: $tawasulPersonIDParent1</li>";
                                echo '<li><b>'.__('Name').'</b>: '.Format::name('', $values['parent1preferredName'], $values['parent1surname'], 'Parent').'</li>';
                                echo '</ul>';

                                //LINK PARENT 1 INTO FAMILY
                                $failFamily = true;
                                if ($tawasulFamilyID != '') {

                                        $dataFamily = array('tawasulFamilyID' => $tawasulFamilyID);
                                        $sqlFamily = 'SELECT * FROM tawasulFamily WHERE tawasulFamilyID=:tawasulFamilyID';
                                        $resultFamily = $connection2->prepare($sqlFamily);
                                        $resultFamily->execute($dataFamily);
                                    if ($resultFamily->rowCount() == 1) {
                                        $rowFamily = $resultFamily->fetch();
                                        $familyName = $rowFamily['name'];
                                        if ($familyName != '') {
                                            $insertOK = true;
                                            try {
                                                $data = array('tawasulPersonID' => $tawasulPersonIDParent1, 'tawasulFamilyID' => $tawasulFamilyID);
                                                $sql = "INSERT INTO tawasulFamilyAdult SET tawasulPersonID=:tawasulPersonID, tawasulFamilyID=:tawasulFamilyID, contactPriority=1, contactCall='Y', contactSMS='Y', contactEmail='Y', contactMail='Y'";
                                                $result = $connection2->prepare($sql);
                                                $result->execute($data);
                                            } catch (PDOException $e) {
                                                $insertOK = false;
                                            }
                                            if ($insertOK == true) {
                                                $failFamily = false;
                                            }
                                        }
                                    }

                                    if ($failFamily == true) {
                                        echo "<div class='warning'>";
                                        echo __('Parent 1 could not be linked to family!');
                                        echo '</div>';
                                        $partialFailures[] = 'failFamily5';
                                    }
                                }

                                //Set parent relationship

                                    $data = array('tawasulFamilyID' => $tawasulFamilyID, 'tawasulPersonID1' => $tawasulPersonIDParent1, 'tawasulPersonID2' => $tawasulPersonID, 'relationship' => $values['parent1relationship']);
                                    $sql = 'INSERT INTO tawasulFamilyRelationship SET tawasulFamilyID=:tawasulFamilyID, tawasulPersonID1=:tawasulPersonID1, tawasulPersonID2=:tawasulPersonID2, relationship=:relationship';
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                            } else {
                                // Generate a unique username for parent 1
                                $generator = new UsernameGenerator($pdo);
                                $generator->addToken('preferredName', $values['parent1preferredName']);
                                $generator->addToken('firstName', $values['parent1firstName']);
                                $generator->addToken('surname', $values['parent1surname']);

                                $username = $generator->generateByRole('004');
                                $status = $schoolYearEntry['status'] == 'Upcoming' && $informParents != 'Y' ? 'Expected' : 'Full';

                                // Generate a random password
                                $password = $passwordPolicy->generate();
                                $salt = getSalt();
                                $passwordStrong = hash('sha256', $salt.$password);

                                $continueLoop = !(!empty($username) && $username != 'usernamefailed' && !empty($password));

                                if ($continueLoop == false) {
                                    $insertOK = true;
                                    try {
                                        $data = array('username' => $username, 'passwordStrong' => $passwordStrong, 'passwordStrongSalt' => $salt, 'title' => $values['parent1title'], 'status' => $status, 'surname' => $values['parent1surname'], 'firstName' => $values['parent1firstName'], 'preferredName' => $values['parent1preferredName'], 'officialName' => $values['parent1officialName'], 'nameInCharacters' => $values['parent1nameInCharacters'], 'gender' => $values['parent1gender'], 'parent1languageFirst' => $values['parent1languageFirst'], 'parent1languageSecond' => $values['parent1languageSecond'], 'email' => $values['parent1email'], 'phone1Type' => $values['parent1phone1Type'], 'phone1CountryCode' => $values['parent1phone1CountryCode'], 'phone1' => $values['parent1phone1'], 'phone2Type' => $values['parent1phone2Type'], 'phone2CountryCode' => $values['parent1phone2CountryCode'], 'phone2' => $values['parent1phone2'], 'profession' => $values['parent1profession'], 'employer' => $values['parent1employer'], 'parent1fields' => $values['parent1fields']);
                                        $sql = "INSERT INTO tawasulPerson SET username=:username, passwordStrong=:passwordStrong, passwordStrongSalt=:passwordStrongSalt, tawasulRoleIDPrimary='004', tawasulRoleIDAll='004', status=:status, title=:title, surname=:surname, firstName=:firstName, preferredName=:preferredName, officialName=:officialName, nameInCharacters=:nameInCharacters, gender=:gender, languageFirst=:parent1languageFirst, languageSecond=:parent1languageSecond, email=:email, phone1Type=:phone1Type, phone1CountryCode=:phone1CountryCode, phone1=:phone1, phone2Type=:phone2Type, phone2CountryCode=:phone2CountryCode, phone2=:phone2, profession=:profession, employer=:employer, fields=:parent1fields";
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                    } catch (PDOException $e) {
                                        $insertOK = false;
                                    }
                                    if ($insertOK == true) {
                                        $failParent1 = false;

                                        $tawasulPersonIDParent1 = $connection2->lastInsertID();

                                        //Populate parent1 in informParent array
                                        if ($informParents == 'Y') {
                                            $informParentsArray[0]['email'] = $values['parent1email'];
                                            $informParentsArray[0]['surname'] = $values['parent1surname'];
                                            $informParentsArray[0]['preferredName'] = $values['parent1preferredName'];
                                            $informParentsArray[0]['username'] = $username;
                                            $informParentsArray[0]['password'] = $password;
                                        }

                                        $container->get(PersonalDocumentGateway::class)->updatePersonalDocumentOwnership('tawasulApplicationFormParent1', $tawasulApplicationFormID, 'tawasulPerson', $tawasulPersonIDParent1);
                                    }
                                }

                                if ($failParent1 == true) {
                                    echo "<div class='error'>";
                                    echo __('Parent 1 could not be created!');
                                    echo '</div>';
                                    $partialFailures[] = 'failFamily6';
                                } else {
                                    echo '<h4>';
                                    echo __('Parent 1');
                                    echo '</h4>';
                                    echo '<ul>';
                                    echo "<li><b>tawasulPersonID</b>: $tawasulPersonIDParent1</li>";
                                    echo '<li><b>'.__('Name').'</b>: '.Format::name('', $values['parent1preferredName'], $values['parent1surname'], 'Parent').'</li>';
                                    echo '<li><b>'.__('Email').'</b>: '.$values['parent1email'].'</li>';
                                    echo '<li><b>'.__('Username')."</b>: $username</li>";
                                    echo '<li><b>'.__('Password')."</b>: $password</li>";
                                    echo '</ul>';

                                    //LINK PARENT 1 INTO FAMILY
                                    $failFamily = true;
                                    if ($tawasulFamilyID != '') {

                                            $dataFamily = array('tawasulFamilyID' => $tawasulFamilyID);
                                            $sqlFamily = 'SELECT * FROM tawasulFamily WHERE tawasulFamilyID=:tawasulFamilyID';
                                            $resultFamily = $connection2->prepare($sqlFamily);
                                            $resultFamily->execute($dataFamily);
                                        if ($resultFamily->rowCount() == 1) {
                                            $rowFamily = $resultFamily->fetch();
                                            $familyName = $rowFamily['name'];
                                            if ($familyName != '') {
                                                $insertOK = true;
                                                try {
                                                    $data = array('tawasulPersonID' => $tawasulPersonIDParent1, 'tawasulFamilyID' => $tawasulFamilyID, 'contactCall' => 'Y', 'contactSMS' => 'Y', 'contactEmail' => 'Y', 'contactMail' => 'Y');
                                                    $sql = 'INSERT INTO tawasulFamilyAdult SET tawasulPersonID=:tawasulPersonID, tawasulFamilyID=:tawasulFamilyID, contactPriority=1, contactCall=:contactCall, contactSMS=:contactSMS, contactEmail=:contactEmail, contactMail=:contactMail';
                                                    $result = $connection2->prepare($sql);
                                                    $result->execute($data);
                                                } catch (PDOException $e) {
                                                    $insertOK = false;
                                                }
                                                if ($insertOK == true) {
                                                    $failFamily = false;
                                                }
                                            }
                                        }

                                        if ($failFamily == true) {
                                            echo "<div class='warning'>";
                                            echo __('Parent 1 could not be linked to family!');
                                            echo '</div>';
                                            $partialFailures[] = 'failFamily7';
                                        }

                                        //Set parent relationship

                                            $data = array('tawasulFamilyID' => $tawasulFamilyID, 'tawasulPersonID1' => $tawasulPersonIDParent1, 'tawasulPersonID2' => $tawasulPersonID, 'relationship' => $values['parent1relationship']);
                                            $sql = 'INSERT INTO tawasulFamilyRelationship SET tawasulFamilyID=:tawasulFamilyID, tawasulPersonID1=:tawasulPersonID1, tawasulPersonID2=:tawasulPersonID2, relationship=:relationship';
                                            $result = $connection2->prepare($sql);
                                            $result->execute($data);
                                    }
                                }
                            }

                            //CREATE PARENT 2
                            if ($values['parent2preferredName'] != '' and $values['parent2surname'] != '') {
                                $failParent2 = true;

                                // Generate a unique username for parent 2
                                $generator = new UsernameGenerator($pdo);
                                $generator->addToken('preferredName', $values['parent2preferredName']);
                                $generator->addToken('firstName', $values['parent2firstName']);
                                $generator->addToken('surname', $values['parent2surname']);

                                $username = $generator->generateByRole('004');
                                $status = $schoolYearEntry['status'] == 'Upcoming' && $informParents != 'Y' ? 'Expected' : 'Full';

                                // Generate a random password
                                $password = $passwordPolicy->generate();
                                $salt = getSalt();
                                $passwordStrong = hash('sha256', $salt.$password);

                                $continueLoop = !(!empty($username) && $username != 'usernamefailed' && !empty($password));

                                if ($continueLoop == false) {
                                    $insertOK = true;
                                    try {
                                        $data = array('username' => $username, 'passwordStrong' => $passwordStrong, 'passwordStrongSalt' => $salt, 'title' => $values['parent2title'], 'status' => $status, 'surname' => $values['parent2surname'], 'firstName' => $values['parent2firstName'], 'preferredName' => $values['parent2preferredName'], 'officialName' => $values['parent2officialName'], 'nameInCharacters' => $values['parent2nameInCharacters'], 'gender' => $values['parent2gender'], 'parent2languageFirst' => $values['parent2languageFirst'], 'parent2languageSecond' => $values['parent2languageSecond'], 'email' => $values['parent2email'], 'phone1Type' => $values['parent2phone1Type'], 'phone1CountryCode' => $values['parent2phone1CountryCode'], 'phone1' => $values['parent2phone1'], 'phone2Type' => $values['parent2phone2Type'], 'phone2CountryCode' => $values['parent2phone2CountryCode'], 'phone2' => $values['parent2phone2'], 'profession' => $values['parent2profession'], 'employer' => $values['parent2employer'], 'parent2fields' => $values['parent2fields']);
                                        $sql = "INSERT INTO tawasulPerson SET username=:username, passwordStrong=:passwordStrong, passwordStrongSalt=:passwordStrongSalt, tawasulRoleIDPrimary='004', tawasulRoleIDAll='004', status=:status, title=:title, surname=:surname, firstName=:firstName, preferredName=:preferredName, officialName=:officialName, nameInCharacters=:nameInCharacters, gender=:gender, languageFirst=:parent2languageFirst, languageSecond=:parent2languageSecond, email=:email, phone1Type=:phone1Type, phone1CountryCode=:phone1CountryCode, phone1=:phone1, phone2Type=:phone2Type, phone2CountryCode=:phone2CountryCode, phone2=:phone2, profession=:profession, employer=:employer, fields=:parent2fields";
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                    } catch (PDOException $e) {
                                        $insertOK = false;
                                    }
                                    if ($insertOK == true) {
                                        $failParent2 = false;

                                        $tawasulPersonIDParent2 = $connection2->lastInsertID();

                                        //Populate parent2 in informParents array
                                        if ($informParents == 'Y') {
                                            $informParentsArray[1]['email'] = $values['parent2email'];
                                            $informParentsArray[1]['surname'] = $values['parent2surname'];
                                            $informParentsArray[1]['preferredName'] = $values['parent2preferredName'];
                                            $informParentsArray[1]['username'] = $username;
                                            $informParentsArray[1]['password'] = $password;
                                        }

                                        $container->get(PersonalDocumentGateway::class)->updatePersonalDocumentOwnership('tawasulApplicationFormParent2', $tawasulApplicationFormID, 'tawasulPerson', $tawasulPersonIDParent2);
                                    }
                                }

                                if ($failParent2 == true) {
                                    echo "<div class='error'>";
                                    echo __('Parent 2 could not be created!');
                                    echo '</div>';
                                    $partialFailures[] = 'failFamily8';
                                } else {
                                    echo '<h4>';
                                    echo __('Parent 2');
                                    echo '</h4>';
                                    echo '<ul>';
                                    echo "<li><b>tawasulPersonID</b>: $tawasulPersonIDParent2</li>";
                                    echo '<li><b>'.__('Name').'</b>: '.Format::name('', $values['parent2preferredName'], $values['parent2surname'], 'Parent').'</li>';
                                    echo '<li><b>'.__('Email').'</b>: '.$values['parent2email'].'</li>';
                                    echo '<li><b>'.__('Username')."</b>: $username</li>";
                                    echo '<li><b>'.__('Password')."</b>: $password</li>";
                                    echo '</ul>';

                                    //LINK PARENT 2 INTO FAMILY
                                    $failFamily = true;
                                    if ($tawasulFamilyID != '') {

                                            $dataFamily = array('tawasulFamilyID' => $tawasulFamilyID);
                                            $sqlFamily = 'SELECT * FROM tawasulFamily WHERE tawasulFamilyID=:tawasulFamilyID';
                                            $resultFamily = $connection2->prepare($sqlFamily);
                                            $resultFamily->execute($dataFamily);
                                        if ($resultFamily->rowCount() == 1) {
                                            $rowFamily = $resultFamily->fetch();
                                            $familyName = $rowFamily['name'];
                                            if ($familyName != '') {
                                                $insertOK = true;
                                                try {
                                                    $data = array('tawasulPersonID' => $tawasulPersonIDParent2, 'tawasulFamilyID' => $tawasulFamilyID, 'contactCall' => 'Y', 'contactSMS' => 'Y', 'contactEmail' => 'Y', 'contactMail' => 'Y');
                                                    $sql = 'INSERT INTO tawasulFamilyAdult SET tawasulPersonID=:tawasulPersonID, tawasulFamilyID=:tawasulFamilyID, contactPriority=2, contactCall=:contactCall, contactSMS=:contactSMS, contactEmail=:contactEmail, contactMail=:contactMail';
                                                    $result = $connection2->prepare($sql);
                                                    $result->execute($data);
                                                } catch (PDOException $e) {
                                                    $insertOK = false;
                                                }
                                                if ($insertOK == true) {
                                                    $failFamily = false;
                                                }
                                            }
                                        }

                                        if ($failFamily == true) {
                                            echo "<div class='warning'>";
                                            echo __('Parent 2 could not be linked to family!');
                                            echo '</div>';
                                            $partialFailures[] = 'failFamily9';
                                        }

                                        //Set parent relationship

                                            $data = array('tawasulFamilyID' => $tawasulFamilyID, 'tawasulPersonID1' => $tawasulPersonIDParent2, 'tawasulPersonID2' => $tawasulPersonID, 'relationship' => $values['parent2relationship']);
                                            $sql = 'INSERT INTO tawasulFamilyRelationship SET tawasulFamilyID=:tawasulFamilyID, tawasulPersonID1=:tawasulPersonID1, tawasulPersonID2=:tawasulPersonID2, relationship=:relationship';
                                            $result = $connection2->prepare($sql);
                                            $result->execute($data);
                                    }
                                }
                            }
                        }
                    }

                    //SEND STUDENT EMAIL
                    if ($informStudent == 'Y') {
                        echo '<h4>';
                        echo __('Student Welcome Email');
                        echo '</h4>';
                        $emailCount = 0 ;
                        $notificationStudentMessage = $settingGateway->getSettingByScope('Application Form', 'notificationStudentMessage');
                        foreach ($informStudentArray as $informStudentEntry) {
                            if ($informStudentEntry['email'] != '' and $informStudentEntry['surname'] != '' and $informStudentEntry['preferredName'] != '' and $informStudentEntry['username'] != '' and $informStudentEntry['password']) {
                                $to = $informStudentEntry['email'];
                                $subject = sprintf(__('Welcome to %1$s at %2$s'), $session->get('systemName'), $session->get('organisationNameShort'));
                                if ($notificationStudentMessage != '') {
                                    $body = sprintf(__('Dear %1$s,<br/><br/>Welcome to %2$s, %3$s\'s system for managing school information. You can access the system by going to %4$s and logging in with your new username (%5$s) and password (%6$s).<br/><br/>In order to maintain the security of your data, we highly recommend you change your password to something easy to remember but hard to guess. This can be done by using the Preferences page after logging in (top-right of the screen).<br/><br/>'), Format::name('', $informStudentEntry['preferredName'], $informStudentEntry['surname'], 'Student'), $session->get('systemName'), $session->get('organisationNameShort'), $session->get('absoluteURL'), $informStudentEntry['username'], $informStudentEntry['password']).$notificationStudentMessage.'<br/><br/>'.sprintf(__('Please feel free to reply to this email should you have any questions.<br/><br/>%1$s,<br/><br/>%2$s Admissions Administrator'), $session->get('organisationAdmissionsName'), $session->get('systemName'));
                                } else {
                                    $body = 'Dear '.Format::name('', $informStudentEntry['preferredName'], $informStudentEntry['surname'], 'Student').",<br/><br/>Welcome to ".$session->get('systemName').', '.$session->get('organisationNameShort')."'s system for managing school information. You can access the system by going to ".$session->get('absoluteURL').' and logging in with your new username ('.$informStudentEntry['username'].') and password ('.$informStudentEntry['password'].").<br/><br/>In order to maintain the security of your data, we highly recommend you change your password to something easy to remember but hard to guess. This can be done by using the Preferences page after logging in (top-right of the screen).<br/><br/>Please feel free to reply to this email should you have any questions.<br/><br/>".$session->get('organisationAdmissionsName').",<br/><br/>".$session->get('systemName').' Admissions Administrator';
                                }

                                $mail = $container->get(Mailer::class);
                                $mail->SetFrom($session->get('organisationAdmissionsEmail'), $session->get('organisationAdmissionsName'));
                                $mail->AddAddress($to);
                                $mail->Subject = $subject;
                                $mail->renderBody('mail/email.twig.html', [
                                    'title'  => $subject,
                                    'body'   => $body,
                                ]);

                                if ($mail->Send()) {
                                    echo "<div class='success'>";
                                    echo __('A welcome email was successfully sent to').' '.Format::name('', $informStudentEntry['preferredName'], $informStudentEntry['surname'], 'Student').'.';
                                    echo '</div>';
                                } else {
                                    echo "<div class='error'>";
                                    echo __('A welcome email could not be sent to').' '.Format::name('', $informStudentEntry['preferredName'], $informStudentEntry['surname'], 'Student').'.';
                                    echo '</div>';
                                }
                                $emailCount++ ;
                            }
                        }
                        if ($emailCount == 0) {
                            echo '<div class=\'warning\'>';
                            echo __('There are no student email addresses to send to.');
                            echo '</div>';
                        }
                    }

                    //SEND PARENTS EMAIL
                    if ($informParents == 'Y') {
                        echo '<h4>';
                        echo 'Parent Welcome Email';
                        echo '</h4>';
                        $emailCount = 0 ;
                        $notificationParentsMessage = $settingGateway->getSettingByScope('Application Form', 'notificationParentsMessage');
                        foreach ($informParentsArray as $informParentsEntry) {
                            if ($informParentsEntry['email'] != '' and $informParentsEntry['surname'] != '' and $informParentsEntry['preferredName'] != '' and $informParentsEntry['username'] != '' and $informParentsEntry['password']) {
                                $to = $informParentsEntry['email'];
                                $subject = sprintf(__('Welcome to %1$s at %2$s'), $session->get('systemName'), $session->get('organisationNameShort'));
                                if ($notificationParentsMessage != '') {
                                    $body = sprintf(__('Dear %1$s,<br/><br/>Welcome to %2$s, %3$s\'s system for managing school information. You can access the system by going to %4$s and logging in with your new username (%5$s) and password (%6$s). You can learn more about using %7$s on the official support website (https://docs.tawasuledu.org/user-guides/parents).<br/><br/>In order to maintain the security of your data, we highly recommend you change your password to something easy to remember but hard to guess. This can be done by using the Preferences page after logging in (top-right of the screen).<br/><br/>'), Format::name('', $informParentsEntry['preferredName'], $informParentsEntry['surname'], 'Student'), $session->get('systemName'), $session->get('organisationNameShort'), $session->get('absoluteURL'), $informParentsEntry['username'], $informParentsEntry['password'], $session->get('systemName')).$notificationParentsMessage.'<br/><br/>'.sprintf(__('Please feel free to reply to this email should you have any questions.<br/><br/>%1$s,<br/><br/>%2$s Admissions Administrator'), $session->get('organisationAdmissionsName'), $session->get('systemName'));
                                } else {
                                    $body = sprintf(__('Dear %1$s,<br/><br/>Welcome to %2$s, %3$s\'s system for managing school information. You can access the system by going to %4$s and logging in with your new username (%5$s) and password (%6$s). You can learn more about using %7$s on the official support website (https://docs.tawasuledu.org/user-guides/parents).<br/><br/>In order to maintain the security of your data, we highly recommend you change your password to something easy to remember but hard to guess. This can be done by using the Preferences page after logging in (top-right of the screen).<br/><br/>'), Format::name('', $informParentsEntry['preferredName'], $informParentsEntry['surname'], 'Student'), $session->get('systemName'), $session->get('organisationNameShort'), $session->get('absoluteURL'), $informParentsEntry['username'], $informParentsEntry['password'], $session->get('systemName')).sprintf(__('Please feel free to reply to this email should you have any questions.<br/><br/>%1$s,<br/><br/>%2$s Admissions Administrator'), $session->get('organisationAdmissionsName'), $session->get('systemName'));
                                }

                                $mail = $container->get(Mailer::class);
                                $mail->SetFrom($session->get('organisationAdmissionsEmail'), $session->get('organisationAdmissionsName'));
                                $mail->AddAddress($to);
                                $mail->Subject = $subject;
                                $mail->renderBody('mail/email.twig.html', [
                                    'title'  => $subject,
                                    'body'   => $body,
                                ]);

                                if ($mail->Send()) {
                                    echo "<div class='success'>";
                                    echo __('A welcome email was successfully sent to').' '.Format::name('', $informParentsEntry['preferredName'], $informParentsEntry['surname'], 'Student').'.';
                                    echo '</div>';
                                } else {
                                    echo "<div class='error'>";
                                    echo __('A welcome email could not be sent to').' '.Format::name('', $informParentsEntry['preferredName'], $informParentsEntry['surname'], 'Student').'.';
                                    echo '</div>';
                                }
                                $emailCount++ ;
                            }
                        }
                        if ($emailCount == 0) {
                            echo '<div class=\'warning\'>';
                            echo __('There are no parent email addresses to send to.');
                            echo '</div>';
                        }
                    }

                    // Raise a new notification event
                    $event = new NotificationEvent('Admissions', 'Application Form Accepted');

                    $studentName = Format::name('', $values['preferredName'], $values['surname'], 'Student');
                    $studentGroup = (!empty($formGroupName))? $formGroupName : $yearGroupName;

                    $notificationText = sprintf(__('An application form for %1$s (%2$s) has been accepted for the %3$s school year.'), $studentName, $studentGroup, $schoolYearName );
                    if ($enrolmentOK && !empty($values['tawasulFormGroupID'])) {
                        $notificationText .= ' '.__('The student has successfully been enrolled in the specified school year, year group and form group.');
                    } else {
                        $notificationText .= ' '.__('Student could not be enrolled, so this will have to be done manually at a later date.');
                    }

                    $event->addScope('tawasulYearGroupID', $values['tawasulYearGroupIDEntry']);
                    $event->addRecipient($session->get('organisationAdmissions'));
                    $event->setNotificationText($notificationText);
                    $event->setActionLink("/index.php?q=/modules/TawasulStudents/applicationForm_manage_edit.php&tawasulApplicationFormID=$tawasulApplicationFormID&tawasulSchoolYearID=".$values['tawasulSchoolYearIDEntry']."&search=");

                    $event->sendNotifications($pdo, $session);


                    // Raise a new notification event for SEN
                    if (!empty($values['senDetails']) || !empty($values['medicalInformation'])) {
                        $event = new NotificationEvent('Admissions', 'New Application with SEN/Medical');
                        $event->addScope('tawasulPersonIDStudent', $tawasulPersonID);
                        $event->addScope('tawasulYearGroupID', $values['tawasulYearGroupIDEntry']);

                        $event->setNotificationText(__('An application form has been accepted for {name} ({group}) with SEN or Medical needs. Please visit the student profile to review these details.', [
                            'name' => $studentName,
                            'group' => $studentGroup,
                        ]));
                        $event->setActionLink('/index.php?q=/modules/TawasulStudents/student_view_details.php&tawasulPersonID='.$tawasulPersonID.'&search=&allStudents=on');

                        // Send all notifications
                        $event->sendNotifications($pdo, $session);
                    }

                    //SET STATUS TO ACCEPTED
                    $failStatus = false;
                    try {
                        $data = array('tawasulApplicationFormID' => $tawasulApplicationFormID);
                        $sql = "UPDATE tawasulApplicationForm SET status='Accepted' WHERE tawasulApplicationFormID=:tawasulApplicationFormID";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $failStatus = true;
                        $partialFailures[] = 'failStatus';
                    }

                    if (!empty($partialFailures)) {
                        $container->get(LogGateway::class)->addLog($session->get('tawasulSchoolYearIDCurrent'), 'Students', $session->get('tawasulPersonID'), 'Application Form - Partial Fail', array('tawasulApplicationFormID' => $tawasulApplicationFormID, 'partialFailures' => implode(',', $partialFailures)), $_SERVER['REMOTE_ADDR']);
                    }

                    if ($failStatus == true) {
                        echo "<div class='error'>";
                        echo __('Student status could not be updated: student is in the system, but acceptance has failed.');
                        echo '</div>';


                    } else {
                        echo '<h4>';
                        echo __('Application Status');
                        echo '</h4>';
                        echo '<ul>';
                        echo '<li><b>'.__('Status').'</b>: '.__('Accepted').'</li>';
                        echo '</ul>';

                        echo "<div class='success' style='margin-bottom: 20px'>";
                        echo str_replace('ICHK', $session->get('organisationNameShort'), __('Applicant has been successfully accepted into ICHK.') );
                        echo ' <i><u>'.__('You may wish to now do the following:').'</u></i><br/>';
                        echo '<ol>';
                        echo '<li>'.__('Enrol the student in the relevant academic year.').'</li>';
                        echo '<li>'.__('Create a medical record for the student.').'</li>';
                        echo '<li>'.__('Create an individual needs record for the student.').'</li>';
                        echo '<li>'.__('Create a note of the student\'s scholarship information outside of TawasulOS.').'</li>';
                        echo '<li>'.__('Create a timetable for the student.').'</li>';
                        echo '<li>'.__('Inform the student and parents of their TawasulOS login details (if this was not done automatically).').'</li>';
                        echo '</ol>';
                        echo '</div>';
                    }
                }
            }
        }
    }
}
