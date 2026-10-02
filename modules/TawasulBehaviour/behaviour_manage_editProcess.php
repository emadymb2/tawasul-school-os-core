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
use TawasulOS\Comms\NotificationEvent;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Domain\System\SettingGateway;
use TawasulOS\Domain\Students\StudentGateway;
use TawasulOS\Domain\IndividualNeeds\INGateway;
use TawasulOS\Domain\Behaviour\BehaviourGateway;
use TawasulOS\Domain\FormGroups\FormGroupGateway;
use TawasulOS\Domain\Behaviour\BehaviourFollowUpGateway;
use TawasulOS\Domain\IndividualNeeds\INAssistantGateway;
use TawasulOS\UI\Components\Alert;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$settingGateway = $container->get(SettingGateway::class);

$enableDescriptors = $settingGateway->getSettingByScope('Behaviour', 'enableDescriptors');
$enableLevels = $settingGateway->getSettingByScope('Behaviour', 'enableLevels');
$behaviourGateway = $container->get(BehaviourGateway::class);

$tawasulBehaviourID = $_GET['tawasulBehaviourID'] ?? '';
$address = $_POST['address'] ?? '';
$tawasulPersonID = $_GET['tawasulPersonID'] ?? '';
$tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? '';
$tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? '';
$type = $_GET['type'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/behaviour_manage_edit.php&tawasulBehaviourID=$tawasulBehaviourID&tawasulPersonID=$tawasulPersonID&tawasulFormGroupID=$tawasulFormGroupID&tawasulYearGroupID=$tawasulYearGroupID&type=$type";

if (isActionAccessible($guid, $connection2, '/modules/TawasulBehaviour/behaviour_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false) {
        $URL .= "&return=error0$params";
        header("Location: {$URL}");
    } else {
        // Proceed!
        // Check if tawasulBehaviourID specified
        if ($tawasulBehaviourID == '') {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            if ($highestAction == 'Manage Behaviour Records_all') {
                $behaviourRecord = $behaviourGateway->getBehaviourDetails($session->get('tawasulSchoolYearID'), $tawasulBehaviourID);
                $canEdit = true;
            } elseif ($highestAction == 'Manage Behaviour Records_my') {
                $behaviourRecord = $behaviourGateway->getBehaviourDetailsByCreator($session->get('tawasulSchoolYearID'), $tawasulBehaviourID, $session->get('tawasulPersonID'));
                $canEdit = true;
            }
            
            if (empty($behaviourRecord) && ($highestAction == 'Manage Behaviour Records_all' || $highestAction == 'Manage Behaviour Records_my')) {
                $behaviourRecord = $behaviourGateway->getBehaviourDetails($session->get('tawasulSchoolYearID'), $tawasulBehaviourID);
                $canEdit = false;
            }

            if (empty($behaviourRecord)) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
            } else {
                $tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
                $date = $_POST['date'] ?? '';
                $type = $_POST['type'] ?? '';
                $descriptor = $_POST['descriptor'] ?? null;
                $level = $_POST['level'] ?? null;
                $comment = $_POST['comment'] ?? '';
                $followUp = $_POST['followUp'] ?? '';
                $tawasulPlannerEntryID = !empty($_POST['tawasulPlannerEntryID']) ? $_POST['tawasulPlannerEntryID'] : null;
                $tawasulBehaviourLinkToID = !empty($_POST['tawasulBehaviourLinkToID']) ? $_POST['tawasulBehaviourLinkToID'] : null;
                
                if($canEdit && !empty($tawasulBehaviourLinkToID)) {
                    $linkToBehaviour = $container->get(BehaviourGateway::class)->getByID($tawasulBehaviourLinkToID);
                    $linkToMultiIncidentID = '';

                    if(!empty($linkToBehaviour['tawasulMultiIncidentID'])) {
                        $linkToMultiIncidentID = $linkToBehaviour['tawasulMultiIncidentID'];
                    } else {
                        $salt = getSalt();
                        $linkToMultiIncidentID = hash('sha256', $salt);

                        $updatedNew = $behaviourGateway->updateMultiIncidentIDByBehaviourID($linkToBehaviour['tawasulBehaviourID'], $linkToMultiIncidentID);
                    }

                    $updated = $behaviourGateway->updateMultiIncidentIDByBehaviourID($tawasulBehaviourID, $linkToMultiIncidentID);
                }
                                      
                $customRequireFail = false;
                $fields = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('Behaviour', [], $customRequireFail);

                if ($tawasulPersonID == '' or $date == '' or $type == '' or ($descriptor == '' and $enableDescriptors == 'Y') || $customRequireFail) {
                    $URL .= '&return=error1';
                    header("Location: {$URL}");
                } else {
                    if ($canEdit) {
                        $data = [
                            'tawasulPersonID' => $tawasulPersonID,
                            'date' => Format::dateConvert($date),
                            'type' => $type,
                            'descriptor' => $descriptor,
                            'level' => $level,
                            'comment' => $comment,
                            'fields' => $fields,
                            'tawasulPlannerEntryID' => $tawasulPlannerEntryID,
                            'tawasulSchoolYearID' => $session->get('tawasulSchoolYearID') ?? '',
                        ];

                        $updated = $behaviourGateway->update($tawasulBehaviourID, $data);

                        if (!$updated) {
                            $URL .= '&return=error2';
                            header("Location: {$URL}");
                            exit();
                        }

                        // Manage custom field file uploads and deletions
                        if (!empty($fields)) {
                            $filesRecorded = $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('Behaviour', [], $fields, 'tawasulBehaviour', $tawasulBehaviourID, $behaviourRecord['fields']);
                        }
                    }

                    // ALERTS: possible change to Behaviour alert status, recalculate alerts
                    $container->get(Alert::class)->recalculateAlerts($tawasulPersonID);

                    // Add a new follow up log, if needed
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

                    // Send a notification to student's tutors and anyone subscribed to the notification event
                    $studentGateway = $container->get(StudentGateway::class);
                    $formGroupGateway = $container->get(FormGroupGateway::class);
                    $inAssistantGateway = $container->get(INAssistantGateway::class);

                    // Send behaviour notifications
                    $student = $studentGateway->selectActiveStudentByPerson($session->get('tawasulSchoolYearID'), $tawasulPersonID)->fetch();
                    if (!empty($student)) {
                        $studentName = Format::name('', $student['preferredName'], $student['surname'], 'Student', false, true);
                        $editorName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);
                        $actionLink = "/index.php?q=/modules/TawasulBehaviour/behaviour_manage_edit.php&tawasulPersonID=$tawasulPersonID&tawasulFormGroupID=&tawasulYearGroupID=&type=$type&tawasulBehaviourID=$tawasulBehaviourID";

                        // Add extra details to the notification
                        $details = [__('Date') => Format::date($date), __('Time') => date('H:i'), __('Type') => $type];
                        if (!empty($descriptor)) $details[__('Descriptor')] = $descriptor;
                        if (!empty($level)) $details[__('Level')] = $level;

                        // Raise a new notification event
                        $event = new NotificationEvent('Behaviour', 'Updated Behaviour Record');

                        $event->setNotificationDetails($details);
                        $event->setNotificationText(sprintf(__('A %1$s behaviour record for %2$s has been updated by %3$s.'), strtolower($type), $studentName, $editorName));
                        $event->setActionLink($actionLink);

                        $event->addScope('tawasulPersonIDStudent', $tawasulPersonID);
                        $event->addScope('tawasulYearGroupID', $student['tawasulYearGroupID']);

                        // Add the person who created the behaviour record, if edited by someone else
                        if ($behaviourRecord['tawasulPersonIDCreator'] != $session->get('tawasulPersonID')) {
                            $event->addRecipient($behaviourRecord['tawasulPersonIDCreator']);
                        }

                        // Add direct notifications to form group tutors
                        if ($settingGateway->getSettingByScope('Behaviour', 'notifyTutors') == 'Y') {
                            $tutors = $formGroupGateway->selectTutorsByFormGroup($student['tawasulFormGroupID'])->fetchAll();
                            foreach ($tutors as $tutor) {
                                $event->addRecipient($tutor['tawasulPersonID']);
                            }
                        }

                        // Add notifications for Educational Assistants
                        if ($settingGateway->getSettingByScope('Behaviour', 'notifyEducationalAssistants') == 'Y') {
                            $educationalAssistants = $inAssistantGateway->selectINAssistantsByStudent($tawasulPersonID)->fetchAll();
                            foreach ($educationalAssistants as $ea) {
                                $event->addRecipient($ea['tawasulPersonID']);
                            }
                        }

                        $event->sendNotifications($pdo, $session);

                        // Check if this is an IN student 
                        $studentIN = $container->get(INGateway::class)->selectIndividualNeedsDescriptorsByStudent($tawasulPersonID)->fetchAll();
                        if (!empty($studentIN)) {
                            // Raise a notification event for IN students
                            $eventIN = new NotificationEvent('Behaviour', 'Behaviour Record for IN Student');
                            
                            $eventIN->setNotificationDetails($details);
                            $eventIN->setNotificationText(sprintf(__('A %1$s behaviour record for %2$s has been updated by %3$s.'), strtolower($type), $studentName, $editorName));
                            $eventIN->setActionLink($actionLink);

                            $eventIN->addScope('tawasulPersonIDStudent', $tawasulPersonID);
                            $eventIN->addScope('tawasulYearGroupID', $student['tawasulYearGroupID']);

                            $eventIN->sendNotifications($pdo, $session);
                        }
                    }

                    $URL .= '&return=success0';
                    header("Location: {$URL}");
                }
            }
        }
    }
}
