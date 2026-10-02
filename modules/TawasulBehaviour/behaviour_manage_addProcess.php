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

use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Comms\NotificationSender;
use TawasulOS\Data\Validator;
use TawasulOS\Domain\Behaviour\BehaviourFollowUpGateway;
use TawasulOS\Domain\Behaviour\BehaviourGateway;
use TawasulOS\Domain\FormGroups\FormGroupGateway;
use TawasulOS\Domain\IndividualNeeds\INAssistantGateway;
use TawasulOS\Domain\IndividualNeeds\INGateway;
use TawasulOS\Domain\Students\StudentNoteGateway;
use TawasulOS\Domain\System\NotificationGateway;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Services\Format;
use TawasulOS\UI\Components\Alert;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$settingGateway = $container->get(SettingGateway::class);

$enableDescriptors = $settingGateway->getSettingByScope('Behaviour', 'enableDescriptors');
$enableLevels = $settingGateway->getSettingByScope('Behaviour', 'enableLevels');

$address = $_POST['address'] ?? '';
$tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
$tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? '';
$tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? '';
$type = $_GET['type'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/behaviour_manage_add.php&tawasulPersonID=$tawasulPersonID&tawasulFormGroupID=$tawasulFormGroupID&tawasulYearGroupID=$tawasulYearGroupID&type=$type";

if (isActionAccessible($guid, $connection2, '/modules/TawasulBehaviour/behaviour_manage_add.php') == false) {
    $URL .= '&return=error0&step=1';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $address, $connection2);
    if ($highestAction == false) {
        $URL .= '&return=error0&step=1';
        header("Location: {$URL}");
    } else {
        $step = $_GET['step'] ?? null;

        if ($step != 1 and $step != 2) {
            $step = 1;
        }
        $tawasulBehaviourID = $_POST['tawasulBehaviourID'] ?? null;
        $behaviourGateway = $container->get(BehaviourGateway::class);

        // Step 1
        if ($step == 1 or $tawasulBehaviourID == null) {
            // Proceed!
            $data = [
                'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID') ?? '',    
                'tawasulPersonID' => $_POST['tawasulPersonID'] ?? '',
                'date' => Format::dateConvert($_POST['date'])  ?? '',
                'type' => $_POST['type'] ?? '',
                'descriptor' => $_POST['descriptor'] ?? null,
                'level' => $_POST['level'] ?? null,
                'comment' => $_POST['comment'] ?? '',
                'tawasulPersonIDCreator' => $session->get('tawasulPersonID') ?? '',
               
            ];

            $customRequireFail = false;
            $fields = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('Behaviour', [], $customRequireFail);
            $data['fields'] = $fields ?? '';

            // Validate the required values are present
            if (empty($data['tawasulPersonID']) || empty($data['date']) || empty($data['type']) || (empty($data['descriptor'] && $enableDescriptors == 'Y') || $customRequireFail)) {
                $URL .= '&return=error1&step=1';
                header("Location: {$URL}");
                exit;
            }

            // Write to database
            $tawasulBehaviourID = $behaviourGateway->insert($data);

            if (empty($tawasulBehaviourID)) {
                $URL .= '&return=error2&step=1';
                header("Location: {$URL}");
                exit();
            }

            // Record custom field file uploads
            if (!empty($fields)) {
                $filesRecorded = $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('Behaviour', [], $fields, 'tawasulBehaviour', $tawasulBehaviourID);
            }

            $copyToNotes = $_POST['copyToNotes'] ?? null;
            $followUp = $_POST['followUp'] ?? '';
          
            // ALERTS: possible change to Behaviour alert status, recalculate alerts
            $container->get(Alert::class)->recalculateAlerts($tawasulPersonID);
                
            // Add a follow up log
            if (!empty($followUp)) {
                $behaviourFollowUpGateway = $container->get(BehaviourFollowUpGateway::class);

                $data = [
                    'tawasulBehaviourID' => $tawasulBehaviourID,
                    'tawasulPersonID' => $session->get('tawasulPersonID'),
                    'followUp' => $followUp,
                ];

                $inserted = $behaviourFollowUpGateway->insert($data);

                if (!$inserted) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit;
                }
            } 

            // Attempt to notify tutor(s) and EA(s) of negative behaviour
            $resultDetail = $container->get(FormGroupGateway::class)->selectTutorsByStudent($session->get('tawasulSchoolYearID'), $tawasulPersonID);
            $student = $container->get(UserGateway::class)->getUserDetails($tawasulPersonID, $session->get('tawasulSchoolYearID'));

            if (!empty($student)) {
                $rowDetail = $resultDetail->fetch();

                // Initialize the notification sender & gateway objects
                $notificationGateway = $container->get(NotificationGateway::class);
                $notificationSender = $container->get(NotificationSender::class);;

                $studentName = Format::name('', $student['preferredName'], $student['surname'], 'Student', false);
                $staffName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);
                $actionLink = "/index.php?q=/modules/TawasulBehaviour/behaviour_manage_edit.php&tawasulPersonID=$tawasulPersonID&tawasulFormGroupID=&tawasulYearGroupID=&type=$type&tawasulBehaviourID=$tawasulBehaviourID";

                // Add extra details to the notification
                $details = [__('Date') => Format::date($data['date']), __('Time') => date('H:i'), __('Type') => $data['type']];
                if (!empty($data['descriptor'])) $details[__('Descriptor')] = $data['descriptor'];
                if (!empty($data['level'])) $details[__('Level')] = $data['level'];

                // Raise a new notification event
                $eventType = '';
                $type = $data['type'] ?? '';
                switch ($type) {
                    case 'Positive':
                        $eventType = 'New Positive Record';
                        break;
                    case 'Negative':
                        $eventType = 'New Negative Record';
                        break;
                    case 'Observation':
                        $eventType = 'New Observation Record';
                        break;
                }
                $event = new NotificationEvent('Behaviour', $eventType);
                $event->setNotificationDetails($details);
                $event->setNotificationText(__('{person} has created a {type} behaviour record for {student}.', [
                    'type' => strtolower($type),
                    'person' => $staffName,
                    'student' => $studentName,
                ]));
                    
                $event->setActionLink($actionLink);
                $event->addScope('tawasulPersonIDStudent', $tawasulPersonID);
                $event->addScope('tawasulYearGroupID', $rowDetail['tawasulYearGroupID']);
                $event->addScope('context', $data['descriptor']);

                // Add notifications for Educational Assistants
                if ($settingGateway->getSettingByScope('Behaviour', 'notifyEducationalAssistants') == 'Y') {
                    $educationalAssistants = $container->get(INAssistantGateway::class)->selectINAssistantsByStudent($tawasulPersonID)->fetchAll();
                    foreach ($educationalAssistants as $ea) {
                        $event->addRecipient($ea['tawasulPersonID']);
                    }
                }

                // Add event listeners to the notification sender
                $event->pushNotifications($notificationGateway, $notificationSender);

                // Add direct notifications to form group tutors
                if ($event->getEventDetails($notificationGateway, 'active') == 'Y') {
                    if ($settingGateway->getSettingByScope('Behaviour', 'notifyTutors') == 'Y') {

                        $notificationText = __('{person} has created a {type} behaviour record for your tutee, {student}.', [
                            'type' => strtolower($type),
                            'person' => $staffName,
                            'student' => $studentName,
                        ]);

                        if ($rowDetail['tawasulPersonIDTutor'] != null and $rowDetail['tawasulPersonIDTutor'] != $session->get('tawasulPersonID')) {
                            $notificationSender->addNotification($rowDetail['tawasulPersonIDTutor'], $notificationText, 'Behaviour', $actionLink, $details);
                        }
                        if ($rowDetail['tawasulPersonIDTutor2'] != null and $rowDetail['tawasulPersonIDTutor2'] != $session->get('tawasulPersonID')) {
                            $notificationSender->addNotification($rowDetail['tawasulPersonIDTutor2'], $notificationText, 'Behaviour', $actionLink, $details);
                        }
                        if ($rowDetail['tawasulPersonIDTutor3'] != null and $rowDetail['tawasulPersonIDTutor3'] != $session->get('tawasulPersonID')) {
                            $notificationSender->addNotification($rowDetail['tawasulPersonIDTutor3'], $notificationText, 'Behaviour', $actionLink, $details);
                        }
                    }
                }

                // Check if this is an IN student
                $studentIN = $container->get(INGateway::class)->selectIndividualNeedsDescriptorsByStudent($tawasulPersonID)->fetchAll();
                if (!empty($studentIN)) {
                    // Raise a notification event for IN students
                    $eventIN = new NotificationEvent('Behaviour', 'Behaviour Record for IN Student');
                    $eventIN->setNotificationDetails($details);
                    $eventIN->setNotificationText(__('{person} has created a {type} behaviour record for {student}.', [
                        'type' => strtolower($type),
                        'person' => $staffName, 
                        'student' => $studentName,
                    ]));
                    $eventIN->setActionLink($actionLink);

                    $eventIN->addScope('tawasulPersonIDStudent', $tawasulPersonID);
                    $eventIN->addScope('tawasulYearGroupID', $rowDetail['tawasulYearGroupID']);

                    // Add event listeners to the notification sender
                    $eventIN->pushNotifications($notificationGateway, $notificationSender);
                }
                
                // Send all notifications
                $notificationSender->sendNotifications();
            }

            if ($copyToNotes == 'on') {
                // Write to notes
                $noteGateway = $container->get(StudentNoteGateway::class);
                $note = [
                    'title'                       => __('Behaviour').': '.$data['descriptor'],
                    'note'                        => empty($followUp) ? $data['comment'] : $data['comment'].' <br/><br/>'.$followUp,
                    'tawasulPersonID'              => $tawasulPersonID,
                    'tawasulPersonIDCreator'       => $session->get('tawasulPersonID'),
                    'tawasulStudentNoteCategoryID' => $noteGateway->getNoteCategoryIDByName('Behaviour') ?? null,
                    'timestamp'                   => date('Y-m-d H:i:s', time()),
                ];

                $inserted = $noteGateway->insert($note);

                if (!$inserted) {
                    $URL .= "&return=warning1&step=2&tawasulBehaviourID=$tawasulBehaviourID&editID=$tawasulBehaviourID";
                    header("Location: {$URL}");
                    exit;
                }
            }

            $URL .= "&return=success1&step=2&tawasulBehaviourID=$tawasulBehaviourID&editID=$tawasulBehaviourID";
            header("Location: {$URL}");
        } elseif ($step == 2 and $tawasulBehaviourID != null) {
            // Proceed!
            $tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
            $tawasulPlannerEntryID = !empty($_POST['tawasulPlannerEntryID']) ? $_POST['tawasulPlannerEntryID'] : null;
            $AI = $_GET['editID'] ?? '';

            if ($tawasulPersonID == '') {
                $URL .= '&return=error1';
                header("Location: {$URL}");
            } else {
                $result = $behaviourGateway->getBehaviourRecordByID($tawasulBehaviourID);

                if (empty($result)) {
                    $URL .= '&return=error2&step=2';
                    header("Location: {$URL}");
                    exit();
                } else {
                    // Update to database                       
                    $updated = $behaviourGateway->update($tawasulBehaviourID, ['tawasulPlannerEntryID' => $tawasulPlannerEntryID]);

                    if (!$updated) {
                        $URL .= '&return=warning0&step=2';
                        header("Location: {$URL}");
                        exit();
                    }

                    $URL .= "&return=success0&editID=$tawasulBehaviourID";
                    header("Location: {$URL}");
                }
            }
        }
    }
}
