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

// Generate a unique and random incidentID for multiple behavior records
$salt = getSalt();
$tawasulMultiIncidentID = hash('sha256', $salt);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/behaviour_manage_add.php&tawasulPersonID=$tawasulPersonID&tawasulFormGroupID=$tawasulFormGroupID&tawasulYearGroupID=$tawasulYearGroupID&type=$type";

if (isActionAccessible($guid, $connection2, '/modules/TawasulBehaviour/behaviour_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    // Proceed!    
    $rawIDs = $_POST['tawasulPersonIDMulti'] ?? [];
    $tawasulPersonIDMulti = is_array($rawIDs) ? array_values(array_unique($rawIDs)) : [];
    $date = $_POST['date'] ?? '';
    $type = $_POST['type'] ?? '';
    $descriptor = $_POST['descriptor'] ?? null;
    $level = $_POST['level'] ?? null;
    $comment = $_POST['comment'] ?? '';
    $followUp = $_POST['followUp'] ?? '';
    $copyToNotes = $_POST['copyToNotes'] ?? null;

    $customRequireFail = false;
    $fields = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('Behaviour', [], $customRequireFail);

    if (empty($tawasulPersonIDMulti) || $date == '' || $type == '' || ($descriptor == '' && $enableDescriptors == 'Y') || $customRequireFail) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {
        $partialFail = false;

        // Initialize the notification sender & gateway objects
        $behaviourGateway = $container->get(BehaviourGateway::class);
        $notificationGateway = $container->get(NotificationGateway::class);
        $notificationSender = $container->get(NotificationSender::class);
        $customFieldHandler = $container->get(CustomFieldHandler::class);
        $alertClass = $container->get(Alert::class);
        $formGroupGateway = $container->get(FormGroupGateway::class);
        $userGateway = $container->get(UserGateway::class);
        $inGateway = $container->get(INGateway::class);
        $behaviourFollowUpGateway = !empty($followUp) ? $container->get(BehaviourFollowUpGateway::class) : null;
        $inAssistantGateway = ($settingGateway->getSettingByScope('Behaviour', 'notifyEducationalAssistants') == 'Y') ? $container->get(INAssistantGateway::class) : null;
        $notifyTutors = $settingGateway->getSettingByScope('Behaviour', 'notifyTutors');

        // Pre-resolve note gateway and category ID once if needed
        $noteGateway    = null;
        $noteCategoryID = null;
        if ($copyToNotes == 'on') {
            $noteGateway = $container->get(StudentNoteGateway::class);
            $noteCategoryID = $noteGateway->getNoteCategoryIDByName('Behaviour') ?? null;
        }

        // Pre-compute constant values used in every iteration
        $tawasulPersonIDCreator = $session->get('tawasulPersonID');
        $tawasulSchoolYearID = $session->get('tawasulSchoolYearID');
        $convertedDate = Format::dateConvert($date);
        $staffName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);

        // Notification details are constant across all students
        $details = [__('Date') => Format::date($date), __('Time') => date('H:i'), __('Type') => $type];
        if (!empty($descriptor)) $details[__('Descriptor')] = $descriptor;
        if (!empty($level)) $details[__('Level')] = $level;

        // Event type is constant across all students
        $eventTypes = ['Positive' => 'New Positive Record', 'Negative' => 'New Negative Record', 'Observation' => 'New Observation Record'];
        $eventType  = $eventTypes[$type] ?? '';

        // Data fields that are constant across all students
        $data = [
            'date'                  => $convertedDate,
            'tawasulMultiIncidentID' => $tawasulMultiIncidentID,
            'type'                  => $type,
            'descriptor'            => $descriptor,
            'level'                 => $level,
            'comment'               => $comment,
            'fields'                => $fields,
            'tawasulPersonIDCreator' => $tawasulPersonIDCreator,
            'tawasulSchoolYearID'    => $tawasulSchoolYearID,
        ];

        foreach ($tawasulPersonIDMulti as $tawasulPersonID) {

            if (empty($tawasulPersonID)) {
                $partialFail = true;
                continue;
            }

            // Write to database
            $tawasulBehaviourID = $behaviourGateway->insert(['tawasulPersonID' => $tawasulPersonID] + $data);

            if (empty($tawasulBehaviourID)) {
                $partialFail = true;
                continue;
            }

            // Record custom field file uploads
            if (!empty($fields)) {
                $customFieldHandler->manageCustomFieldFileUploads('Behaviour', [], $fields, 'tawasulBehaviour', $tawasulBehaviourID);
            }

            // ALERTS: possible change to Behaviour alert status, recalculate alerts
            $alertClass->recalculateAlerts($tawasulPersonID);

            // Add a follow up log
            if ($behaviourFollowUpGateway !== null) {
                $inserted = $behaviourFollowUpGateway->insert([
                    'tawasulBehaviourID' => $tawasulBehaviourID,
                    'tawasulPersonID'    => $tawasulPersonIDCreator,
                    'followUp'          => $followUp,
                ]);
                if (!$inserted) {
                    $partialFail = true;
                }
            }

            // Attempt to notify tutor(s) and EA(s) of behaviour
            $resultDetail = $formGroupGateway->selectTutorsByStudent($tawasulSchoolYearID, $tawasulPersonID);
            $student = $userGateway->getUserDetails($tawasulPersonID, $tawasulSchoolYearID);
            $rowDetail = !empty($resultDetail) ? $resultDetail->fetch() : null;

            if (!empty($student)) {
                $studentName = Format::name('', $student['preferredName'], $student['surname'], 'Student', false);
                $actionLink  = "/index.php?q=/modules/TawasulBehaviour/behaviour_manage_edit.php&tawasulPersonID=$tawasulPersonID&tawasulFormGroupID=&tawasulYearGroupID=&type=$type&tawasulBehaviourID=$tawasulBehaviourID";

                // Raise a new notification event
                $event = new NotificationEvent('Behaviour', $eventType);
                $event->setNotificationDetails($details);
                $event->setNotificationText(__('{person} has created a {type} behaviour record for {student}.', [
                    'type'    => strtolower($type),
                    'person'  => $staffName,
                    'student' => $studentName,
                ]));
                $event->setActionLink($actionLink);
                $event->addScope('tawasulPersonIDStudent', $tawasulPersonID);
                $event->addScope('tawasulYearGroupID', $rowDetail['tawasulYearGroupID']);
                $event->addScope('context', $descriptor);

                // Add notifications for Educational Assistants
                if ($inAssistantGateway !== null) {
                    foreach ($inAssistantGateway->selectINAssistantsByStudent($tawasulPersonID)->fetchAll() as $ea) {
                        $event->addRecipient($ea['tawasulPersonID']);
                    }
                }

                // Add event listeners to the notification sender
                $event->pushNotifications($notificationGateway, $notificationSender);

                // Add direct notifications to form group tutors
                if ($notifyTutors == 'Y' && $event->getEventDetails($notificationGateway, 'active') == 'Y') {
                    $notificationText = __('{person} has created a {type} behaviour record for your tutee, {student}.', [
                        'type'    => strtolower($type),
                        'person'  => $staffName,
                        'student' => $studentName,
                    ]);
                    if ($rowDetail['tawasulPersonIDTutor'] != null and $rowDetail['tawasulPersonIDTutor'] != $tawasulPersonIDCreator) {
                        $notificationSender->addNotification($rowDetail['tawasulPersonIDTutor'], $notificationText, 'Behaviour', $actionLink, $details);
                    }
                    if ($rowDetail['tawasulPersonIDTutor2'] != null and $rowDetail['tawasulPersonIDTutor2'] != $tawasulPersonIDCreator) {
                        $notificationSender->addNotification($rowDetail['tawasulPersonIDTutor2'], $notificationText, 'Behaviour', $actionLink, $details);
                    }
                    if ($rowDetail['tawasulPersonIDTutor3'] != null and $rowDetail['tawasulPersonIDTutor3'] != $tawasulPersonIDCreator) {
                        $notificationSender->addNotification($rowDetail['tawasulPersonIDTutor3'], $notificationText, 'Behaviour', $actionLink, $details);
                    }
                }

                // Check if this is an IN student and raise a separate notification event
                $studentIN = $inGateway->selectIndividualNeedsDescriptorsByStudent($tawasulPersonID)->fetchAll();
                if (!empty($studentIN)) {
                    $eventIN = new NotificationEvent('Behaviour', 'Behaviour Record for IN Student');
                    $eventIN->setNotificationDetails($details);
                    $eventIN->setNotificationText(__('{person} has created a {type} behaviour record for {student}.', [
                        'type'    => strtolower($type),
                        'person'  => $staffName,
                        'student' => $studentName,
                    ]));
                    $eventIN->setActionLink($actionLink);
                    $eventIN->addScope('tawasulPersonIDStudent', $tawasulPersonID);
                    $eventIN->addScope('tawasulYearGroupID', $rowDetail['tawasulYearGroupID']);
                    $eventIN->pushNotifications($notificationGateway, $notificationSender);
                }
            }

            // Copy behaviour record to student notes
            if ($noteGateway !== null) {
                $inserted = $noteGateway->insert([
                    'title'                       => __('Behaviour').': '.$descriptor,
                    'note'                        => empty($followUp) ? $comment : $comment.' <br/><br/>'.$followUp,
                    'tawasulPersonID'              => $tawasulPersonID,
                    'tawasulPersonIDCreator'       => $tawasulPersonIDCreator,
                    'tawasulStudentNoteCategoryID' => $noteCategoryID,
                    'timestamp'                   => date('Y-m-d H:i:s', time()),
                ]);
                if (!$inserted) $partialFail = true;
            }
        }

        // Send all notifications
        $notificationSender->sendNotifications();

        if ($partialFail == true) {
            $URL .= '&return=warning1';
            header("Location: {$URL}");
        } else {
            $URL .= '&return=success0';
            header("Location: {$URL}");
        }
        exit;
    }
}
