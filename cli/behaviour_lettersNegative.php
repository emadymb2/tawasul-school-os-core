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

use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Services\Format;
use TawasulOS\Contracts\Comms\Mailer;
use TawasulOS\Comms\EmailTemplate;
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Comms\NotificationSender;
use TawasulOS\Domain\Behaviour\BehaviourLetterGateway;
use TawasulOS\Domain\System\NotificationGateway;
use TawasulOS\Domain\System\EmailTemplateGateway;
use TawasulOS\Domain\User\UserGateway;

require getcwd().'/../tawasul.php';

//Check for CLI, so this cannot be run through browser
$settingGateway = $container->get(SettingGateway::class);
$remoteCLIKey = $settingGateway->getSettingByScope('System Admin', 'remoteCLIKey');
$remoteCLIKeyInput = $_GET['remoteCLIKey'] ?? null;
if (!(isCommandLineInterface() OR ($remoteCLIKey != '' AND $remoteCLIKey == $remoteCLIKeyInput))) {
    echo __('This script cannot be run from a browser, only via CLI.');
} else {
    $emailSendCount = 0;
    $emailFailCount = 0;
    $emailFailList = [];

    // Prep for email sending later
    $mail = $container->get(Mailer::class);
    $mail->SMTPKeepAlive = true;

    // Initialize the notification sender & gateway objects
    $notificationGateway = $container->get(NotificationGateway::class);
    $notificationSender = $container->get(NotificationSender::class);

    //Get settings
    $enableDescriptors = $settingGateway->getSettingByScope('Behaviour', 'enableDescriptors');
    $enableLevels = $settingGateway->getSettingByScope('Behaviour', 'enableLevels');
    $enableNegativeBehaviourLetters = $settingGateway->getSettingByScope('Behaviour', 'enableNegativeBehaviourLetters');
    if ($enableNegativeBehaviourLetters == 'Y') {
        $behaviourLettersNegativeLetter1Count = $settingGateway->getSettingByScope('Behaviour', 'behaviourLettersNegativeLetter1Count');
        $behaviourLettersNegativeLetter2Count = $settingGateway->getSettingByScope('Behaviour', 'behaviourLettersNegativeLetter2Count');
        $behaviourLettersNegativeLetter3Count = $settingGateway->getSettingByScope('Behaviour', 'behaviourLettersNegativeLetter3Count');

        $behaviourLetterGateway = $container->get(BehaviourLetterGateway::class);
        $emailTemplateGateway = $container->get(EmailTemplateGateway::class);
        $userGateway = $container->get(UserGateway::class);
        $template = $container->get(EmailTemplate::class);

        if ($behaviourLettersNegativeLetter1Count != '' and $behaviourLettersNegativeLetter2Count != '' and $behaviourLettersNegativeLetter3Count != '' and is_numeric($behaviourLettersNegativeLetter1Count) and is_numeric($behaviourLettersNegativeLetter2Count) and is_numeric($behaviourLettersNegativeLetter3Count)) {
            //SCAN THROUGH ALL STUDENTS

            $data = array('tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
            $sql = "SELECT tawasulPerson.tawasulPersonID, preferredName, surname, tawasulFormGroup.tawasulFormGroupID, tawasulFormGroup.name AS formGroup, 'Student' AS role, tawasulPersonIDTutor, tawasulPersonIDTutor2, tawasulPersonIDTutor3 FROM tawasulPerson, tawasulStudentEnrolment, tawasulFormGroup WHERE tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID AND tawasulStudentEnrolment.tawasulFormGroupID=tawasulFormGroup.tawasulFormGroupID AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND tawasulFormGroup.tawasulSchoolYearID=:tawasulSchoolYearID ORDER BY surname, preferredName";
            $result = $pdo->select($sql, $data);

            if ($result->rowCount() > 0) {
                while ($student = $result->fetch()) { //For every student
                    $studentName = Format::name('', $student['preferredName'], $student['surname'], 'Student', false);
                    $formGroup = $student['formGroup'];

                    //Check count of negative behaviour records in the current year=
                    $dataBehaviour = array('tawasulPersonID' => $student['tawasulPersonID'], 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
                    $sqlBehaviour = "SELECT * FROM tawasulBehaviour WHERE tawasulPersonID=:tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID AND type='Negative'";
                    $resultBehaviour = $pdo->select($sqlBehaviour, $dataBehaviour);

                    $behaviourCount = $resultBehaviour->rowCount();
                    if ($behaviourCount > 0) { //Only worry about students with more than zero negative records in the current year
                        //Get most recent letter entry
                        $dataLetters = array('tawasulPersonID' => $student['tawasulPersonID'], 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
                        $sqlLetters = "SELECT * FROM tawasulBehaviourLetter WHERE tawasulPersonID=:tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID AND type='Negative' ORDER BY timestamp DESC LIMIT 0, 1";
                        $resultLetters = $pdo->select($sqlLetters, $dataLetters);

                        $newLetterRequired = false;
                        $newLetterRequiredLevel = null;
                        $newLetterRequiredStatus = null;
                        $issueExistingLetter = false;
                        $issueExistingLetterLevel = null;
                        $issueExistingLetterID = null;

                        //DECIDE WHAT LETTERS TO SEND
                        if ($resultLetters->rowCount() != 1) { //NO LETTER EXISTS
                            $lastLetterLevel = null;
                            $lastLetterStatus = null;

                            if ($behaviourCount >= $behaviourLettersNegativeLetter3Count) { //Student is over or equal to level 3
                                $newLetterRequired = true;
                                $newLetterRequiredLevel = 3;
                                $newLetterRequiredStatus = 'Issued';
                            } elseif ($behaviourCount >= $behaviourLettersNegativeLetter2Count and $behaviourCount < $behaviourLettersNegativeLetter3Count) { //Student is equal to or greater than level 2 but less than level 3
                                $newLetterRequired = true;
                                $newLetterRequiredLevel = 2;
                                $newLetterRequiredStatus = 'Issued';
                            } elseif ($behaviourCount >= $behaviourLettersNegativeLetter1Count and $behaviourCount < $behaviourLettersNegativeLetter2Count) { //Student is equal to or greater than level 1 but less than level 2
                                $newLetterRequired = true;
                                $newLetterRequiredLevel = 1;
                                $newLetterRequiredStatus = 'Issued';
                            } elseif ($behaviourCount == ($behaviourLettersNegativeLetter1Count - 1)) { //Student is one less than level 1
                                $newLetterRequired = true;
                                $newLetterRequiredLevel = 1;
                                $newLetterRequiredStatus = 'Warning';
                            }
                        } else { //YES LETTER EXISTS
                            $rowLetters = $resultLetters->fetch();
                            $lastLetterLevel = $rowLetters['letterLevel'];
                            $lastLetterStatus = $rowLetters['status'];

                            if ($behaviourCount > $rowLetters['recordCountAtCreation']) { //Only consider action if count has increased since creation (stops second day issue of warning when count has not changed)
                                if ($lastLetterStatus == 'Warning') { //Last letter is warning
                                    if ($behaviourCount >= ${'behaviourLettersNegativeLetter'.$lastLetterLevel.'Count'} and $behaviourCount < ${'behaviourLettersNegativeLetter'.($lastLetterLevel + 1).'Count'}) { //Count escalted to above warning, and less than next full level
                                        $issueExistingLetter = true;
                                        $issueExistingLetterID = $rowLetters['tawasulBehaviourLetterID'];
                                        $issueExistingLetterLevel = $rowLetters['letterLevel'];
                                    } elseif ($behaviourCount >= ${'behaviourLettersNegativeLetter'.($lastLetterLevel + 1).'Count'}) { //Count escalated to equal to or above next level
                                        $newLetterRequired = true;
                                        if ($behaviourCount >= $behaviourLettersNegativeLetter3Count) {
                                            $newLetterRequiredLevel = 3;
                                        } elseif ($behaviourCount >= $behaviourLettersNegativeLetter2Count and $behaviourCount < $behaviourLettersNegativeLetter3Count) {
                                            $newLetterRequiredLevel = 2;
                                        }
                                        $newLetterRequiredStatus = 'Issued';
                                    }
                                } else { //Last letter is issued
                                    if ($behaviourCount == (${'behaviourLettersNegativeLetter'.($lastLetterLevel + 1).'Count'} - 1)) { //Count escalated to next warning
                                        $newLetterRequired = true;
                                        if ($behaviourCount == ($behaviourLettersNegativeLetter3Count - 1)) {
                                            $newLetterRequiredLevel = 3;
                                        } elseif ($behaviourCount == ($behaviourLettersNegativeLetter2Count - 1)) {
                                            $newLetterRequiredLevel = 2;
                                        }
                                        $newLetterRequiredStatus = 'Warning';
                                    } elseif ($behaviourCount > (${'behaviourLettersNegativeLetter'.($lastLetterLevel + 1).'Count'} - 1)) { //Count escalated above next warning
                                        $newLetterRequired = true;
                                        if ($behaviourCount >= $behaviourLettersNegativeLetter3Count) {
                                            $newLetterRequiredLevel = 3;
                                        } elseif ($behaviourCount >= $behaviourLettersNegativeLetter2Count) {
                                            $newLetterRequiredLevel = 2;
                                        }
                                        $newLetterRequiredStatus = 'Issued';
                                    }
                                }
                            }
                        }

                        //SEND LETTERS ACCORDING TO DECISIONS ABOVE
                        $email = false;
                        $letterUpdateFail = false;
                        $tawasulBehaviourLetterID = null;

                        //PREPARE BEHAVIOUR RECORD
                        if ($issueExistingLetter or ($newLetterRequired and $newLetterRequiredStatus == 'Issued')) {
                            //Prepare behaviour record for replacement
                            $behaviourRecord = '<ul>';

                            $dataBehaviourRecord = array('tawasulPersonID' => $student['tawasulPersonID'], 'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID'));
                            $sqlBehaviourRecord = "SELECT * FROM tawasulBehaviour WHERE tawasulPersonID=:tawasulPersonID AND tawasulSchoolYearID=:tawasulSchoolYearID AND type='Negative' ORDER BY timestamp DESC";
                            $resultBehaviourRecord = $pdo->select($sqlBehaviourRecord, $dataBehaviourRecord);

                            while ($rowBehaviourRecord = $resultBehaviourRecord->fetch()) {
                                $behaviourRecord .= '<li>';
                                $behaviourRecord .= Format::date(substr($rowBehaviourRecord['timestamp'], 0, 10));
                                if ($enableDescriptors == 'Y' and $rowBehaviourRecord['descriptor'] != '') {
                                    $behaviourRecord .= ' - '.$rowBehaviourRecord['descriptor'];
                                }
                                if ($enableLevels == 'Y' and $rowBehaviourRecord['level'] != '') {
                                    $behaviourRecord .= ' - '.$rowBehaviourRecord['level'];
                                }
                                $behaviourRecord .= '</li>';
                            }
                            $behaviourRecord .= '</ul>';
                        }

                        if ($issueExistingLetter) { //Issue existing letter
                            //Update record
                            $tawasulBehaviourLetterID = $issueExistingLetterID;

                            $updated = $behaviourLetterGateway->update($tawasulBehaviourLetterID, ['status' => 'Issued']);

                            if ($updated) {
                                //Flag parents to receive email
                                $email = true;

                                //Notify tutor(s)
                                $notificationText = sprintf(__('A student (%1$s) in your form group has received a behaviour letter.'), $studentName);
                                if ($student['tawasulPersonIDTutor'] != '') {
                                    $notificationSender->addNotification($student['tawasulPersonIDTutor'], $notificationText, 'Behaviour', '/index.php?q=/modules/TawasulBehaviour/behaviour_letters.php&tawasulPersonID='.$student['tawasulPersonID']);
                                }
                                if ($student['tawasulPersonIDTutor2'] != '') {
                                    $notificationSender->addNotification($student['tawasulPersonIDTutor2'], $notificationText, 'Behaviour', '/index.php?q=/modules/TawasulBehaviour/behaviour_letters.php&tawasulPersonID='.$student['tawasulPersonID']);
                                }
                                if ($student['tawasulPersonIDTutor3'] != '') {
                                    $notificationSender->addNotification($student['tawasulPersonIDTutor3'], $notificationText, 'Behaviour', '/index.php?q=/modules/TawasulBehaviour/behaviour_letters.php&tawasulPersonID='.$student['tawasulPersonID']);
                                }
                            }
                        } elseif ($newLetterRequired) { //Issue new letter
                            if ($newLetterRequiredStatus == 'Warning') { //It's a warning
                                //Create new record
                                $tawasulBehaviourLetterID = $behaviourLetterGateway->insert([
                                    'type'                  => 'Negative',
                                    'status'                => 'Warning',
                                    'tawasulSchoolYearID'    => $session->get('tawasulSchoolYearID'),
                                    'tawasulPersonID'        => $student['tawasulPersonID'],
                                    'letterLevel'           => $newLetterRequiredLevel,
                                    'recordCountAtCreation' => $behaviourCount,
                                ]);

                                if (!empty($tawasulBehaviourLetterID)) {
                                    //Notify tutor(s)
                                    $notificationText = sprintf(__('A warning has been issued for a student (%1$s) in your form group,
                                     pending a behaviour letter.'), $studentName);
                                    if ($student['tawasulPersonIDTutor'] != '') {
                                        $notificationSender->addNotification($student['tawasulPersonIDTutor'], $notificationText, 'Behaviour', '/index.php?q=/modules/TawasulBehaviour/behaviour_letters.php&tawasulPersonID='.$student['tawasulPersonID']);
                                    }
                                    if ($student['tawasulPersonIDTutor2'] != '') {
                                        $notificationSender->addNotification($student['tawasulPersonIDTutor2'], $notificationText, 'Behaviour', '/index.php?q=/modules/TawasulBehaviour/behaviour_letters.php&tawasulPersonID='.$student['tawasulPersonID']);
                                    }
                                    if ($student['tawasulPersonIDTutor3'] != '') {
                                        $notificationSender->addNotification($student['tawasulPersonIDTutor3'], $notificationText, 'Behaviour', '/index.php?q=/modules/TawasulBehaviour/behaviour_letters.php&tawasulPersonID='.$student['tawasulPersonID']);
                                    }

                                    //Notify teachers
                                    $notificationText = sprintf(__('A warning has been issued for a student (%1$s) in one of your classes, pending a behaviour letter.'), $studentName);

                                    $dataTeachers = array('tawasulPersonID' => $student['tawasulPersonID']);
                                    $sqlTeachers = "SELECT DISTINCT teacher.tawasulPersonID FROM tawasulPerson AS teacher JOIN tawasulCourseClassPerson AS teacherClass ON (teacherClass.tawasulPersonID=teacher.tawasulPersonID)  JOIN tawasulCourseClassPerson AS studentClass ON (studentClass.tawasulCourseClassID=teacherClass.tawasulCourseClassID) JOIN tawasulPerson AS student ON (studentClass.tawasulPersonID=student.tawasulPersonID) JOIN tawasulCourseClass ON (studentClass.tawasulCourseClassID=tawasulCourseClass.tawasulCourseClassID) JOIN tawasulCourse ON (tawasulCourseClass.tawasulCourseID=tawasulCourse.tawasulCourseID) WHERE teacher.status='Full' AND teacherClass.role='Teacher' AND studentClass.role='Student' AND student.tawasulPersonID=:tawasulPersonID AND tawasulCourse.tawasulSchoolYearID=(SELECT tawasulSchoolYearID FROM tawasulSchoolYear WHERE status='Current') ORDER BY teacher.preferredName, teacher.surname, teacher.email ;";
                                    $resultTeachers = $pdo->select($sqlTeachers, $dataTeachers);

                                    while ($rowTeachers = $resultTeachers->fetch()) {
                                        $notificationSender->addNotification($rowTeachers['tawasulPersonID'], $notificationText, 'Behaviour', '/index.php?q=/modules/TawasulBehaviour/behaviour_letters.php&tawasulPersonID='.$student['tawasulPersonID']);
                                    }
                                }
                            } else { //It's being issued
                                //Create new record
                                $tawasulBehaviourLetterID = $behaviourLetterGateway->insert([
                                    'type'                  => 'Negative',
                                    'status'                => 'Issued',
                                    'tawasulSchoolYearID'    => $session->get('tawasulSchoolYearID'),
                                    'tawasulPersonID'        => $student['tawasulPersonID'],
                                    'letterLevel'           => $newLetterRequiredLevel,
                                    'recordCountAtCreation' => $behaviourCount,
                                ]);

                                if (!empty($tawasulBehaviourLetterID)) {
                                    //Flag parents to receive email
                                    $email = true;

                                    //Notify tutor(s)
                                    $notificationText = sprintf(__('A student (%1$s) in your form group has received a behaviour letter.'), $studentName);
                                    if ($student['tawasulPersonIDTutor'] != '') {
                                        $notificationSender->addNotification($student['tawasulPersonIDTutor'], $notificationText, 'Behaviour', '/index.php?q=/modules/TawasulBehaviour/behaviour_letters.php&tawasulPersonID='.$student['tawasulPersonID']);
                                    }
                                    if ($student['tawasulPersonIDTutor2'] != '') {
                                        $notificationSender->addNotification($student['tawasulPersonIDTutor2'], $notificationText, 'Behaviour', '/index.php?q=/modules/TawasulBehaviour/behaviour_letters.php&tawasulPersonID='.$student['tawasulPersonID']);
                                    }
                                    if ($student['tawasulPersonIDTutor3'] != '') {
                                        $notificationSender->addNotification($student['tawasulPersonIDTutor3'], $notificationText, 'Behaviour', '/index.php?q=/modules/TawasulBehaviour/behaviour_letters.php&tawasulPersonID='.$student['tawasulPersonID']);
                                    }
                                }
                            }
                        }

                        //DEAL WIITH EMAILS
                        if ($email) {
                            $recipientList = '';

                            // Setup template to send
                            $templateCount = $issueExistingLetter ? $issueExistingLetterLevel : $newLetterRequiredLevel;
                            $templateType  = "Negative Behaviour Letter $templateCount";
                            $templateDetails = $emailTemplateGateway->selectBy(['templateType' => $templateType], ['templateName'])->fetch();
                            $template->setTemplate($templateDetails['templateName'] ?? 'Negative Behaviour Letter 1');

                            // Get form tutor details
                            $formTutor = $userGateway->getByID($student['tawasulPersonIDTutor'], ['title', 'surname', 'preferredName', 'email']);

                            //Send emails
                            $dataMember = array('tawasulPersonID' => $student['tawasulPersonID']);
                            $sqlMember = "SELECT DISTINCT email, preferredName, surname, title FROM tawasulFamilyChild JOIN tawasulFamily ON (tawasulFamilyChild.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulFamilyAdult ON (tawasulFamilyAdult.tawasulFamilyID=tawasulFamily.tawasulFamilyID) JOIN tawasulPerson ON (tawasulFamilyAdult.tawasulPersonID=tawasulPerson.tawasulPersonID) WHERE tawasulFamilyChild.tawasulPersonID=:tawasulPersonID AND tawasulPerson.status='Full' AND contactEmail='Y' ORDER BY contactPriority, surname, preferredName";
                            $resultMember = $pdo->select($sqlMember, $dataMember);

                            while ($parent = $resultMember->fetch()) {
                                ++$emailSendCount;
                                if ($parent['email'] == '') {
                                    ++$emailFailCount;
                                    $emailFailList[] = $parent['surname'].', '.$parent['preferredName'].' (no email)';
                                } else {
                                    $recipientList .= $parent['email'].', ';

                                    // Setup template data
                                    $templateData = [
                                        'behaviourCount'         => $behaviourCount,
                                        'behaviourRecord'        => $behaviourRecord,
                                        'studentPreferredName'   => $student['preferredName'],
                                        'studentSurname'         => $student['surname'],
                                        'studentFormGroup'       => $student['formGroup'],
                                        'parentPreferredName'    => $parent['preferredName'],
                                        'parentSurname'          => $parent['surname'],
                                        'parentTitle'            => $parent['title'],
                                        'formTutorPreferredName' => $formTutor['preferredName'],
                                        'formTutorSurname'       => $formTutor['surname'],
                                        'formTutorTitle'         => $formTutor['title'],
                                        'formTutorEmail'         => $formTutor['email'],
                                        'date'                   => Format::date(date('Y-m-d')),
                                    ];

                                    // Render the templates for this email
                                    $subject = $template->renderSubject($templateData);
                                    $body = $template->renderBody($templateData);

                                    // Send message
                                    $mail->AddAddress($parent['email'], $parent['surname'].', '.$parent['preferredName']);
                                    if ($session->has('organisationEmail')) {
                                        $mail->SetFrom($session->get('organisationEmail'), $session->get('organisationName'));
                                    } else {
                                        $mail->SetFrom($session->get('organisationAdministratorEmail'), $session->get('organisationAdministratorName'));
                                    }

                                    $mail->Subject = $subject;
                                    $mail->renderBody('mail/message.twig.html', [
                                        'title'  => $subject,
                                        'body'   => $body,
                                    ]);

                                    if ($mail->Send()) {
                                        $behaviourLetterGateway->update($tawasulBehaviourLetterID, ['body' => $body]);
                                    } else {
                                        ++$emailFailCount;
                                        $emailFailList[] = $parent['surname'].', '.$parent['preferredName'].' ('.$parent['email'].')';
                                    }

                                    // Clear addresses
                                    $mail->ClearAllRecipients();
                                }
                            }

                            if ($recipientList != '') {
                                $recipientList = substr($recipientList, 0, -2);

                                //Record email recipients in letter record
                                $dataUpdate = array('recipientList' => $recipientList, 'tawasulBehaviourLetterID' => $tawasulBehaviourLetterID);
                                $sqlUpdate = 'UPDATE tawasulBehaviourLetter set recipientList=:recipientList WHERE tawasulBehaviourLetterID=:tawasulBehaviourLetterID';
                                $resultUpdate = $pdo->select($sqlUpdate, $dataUpdate);
                            }
                        }
                    }
                }
            }
        }
    }

    // Close SMTP connection
    $mail->smtpClose();

    // Raise a new notification event
    $event = new NotificationEvent('Behaviour', 'Behaviour Letters');

    //Notify admin
    if (empty($email)) {
        $event->setNotificationText(__('The Behaviour Letter CLI script has run: no emails were sent.'));
    } else {
        $event->setNotificationText(sprintf(__('The Behaviour Letter CLI script has run: %1$s emails were sent, of which %2$s failed.'), $emailSendCount, $emailFailCount).'<br/><br/>'.Format::list($emailFailList));
    }

    $event->setActionLink('/index.php?q=/modules/TawasulBehaviour/behaviour_letters.php');

    // Add admin, then push the event to the notification sender
    $event->addRecipient($session->get('organisationAdministrator'));
    $event->pushNotifications($notificationGateway, $notificationSender);

    // Send all notifications
    $sendReport = $notificationSender->sendNotifications();

    // Output the result to terminal
    echo sprintf('Sent %1$s notifications: %2$s inserts, %3$s updates, %4$s emails sent, %5$s emails failed.', $sendReport['count'], $sendReport['inserts'], $sendReport['updates'], $emailSendCount, $emailFailCount)."\n";
}
