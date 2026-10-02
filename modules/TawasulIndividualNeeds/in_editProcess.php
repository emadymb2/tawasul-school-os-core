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
use TawasulOS\Domain\User\UserGateway;
use TawasulOS\Forms\CustomFieldHandler;
use TawasulOS\Domain\IndividualNeeds\INGateway;
use TawasulOS\Domain\IndividualNeeds\INAssistantGateway;
use TawasulOS\UI\Components\Alert;

require_once __DIR__ . '/../../tawasul.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['targets' => 'HTML', 'strategies' => 'HTML', 'notes' => 'HTML']);

$tawasulPersonID = $_POST['tawasulPersonID'] ?? '';
$address = $_POST['address'] ?? '';
$search = $_GET['search'] ?? '';
$source = $_GET['source'] ?? '';
$tawasulINDescriptorID = $_GET['tawasulINDescriptorID'] ?? '';
$tawasulAlertLevelID = $_GET['tawasulAlertLevelID'] ?? '';
$tawasulFormGroupID = $_GET['tawasulFormGroupID'] ?? '';
$tawasulYearGroupID = $_GET['tawasulYearGroupID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/in_edit.php&tawasulPersonID=$tawasulPersonID&search=$search&source=$source&tawasulINDescriptorID=$tawasulINDescriptorID&tawasulAlertLevelID=$tawasulAlertLevelID&tawasulFormGroupID=$tawasulFormGroupID&tawasulYearGroupID=$tawasulYearGroupID";

if (isActionAccessible($guid, $connection2, '/modules/TawasulIndividualNeeds/in_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Get action with highest precedence
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == false or ($highestAction != 'Individual Needs Records_viewContribute' and $highestAction != 'Individual Needs Records_viewEdit')) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        //Check access to specified student
        $result = $container->get(UserGateway::class)->getUserDetails($tawasulPersonID, $session->get('tawasulSchoolYearID'));

        if (empty($result)) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            $partialFail = false;
            $row = $result;

            if ($highestAction == 'Individual Needs Records_viewEdit') {
                //UPDATE STATUS
                $statuses = array();
                if (isset($_POST['status'])) {
                    $statuses = $_POST['status'] ?? '';
                }
                try {
                    $data = array('tawasulPersonID' => $tawasulPersonID);
                    $sql = 'DELETE FROM tawasulINPersonDescriptor WHERE tawasulPersonID=:tawasulPersonID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $partialFail = true;
                }
                foreach ($statuses as $status) {
                    try {
                        $data = array('tawasulPersonID' => $tawasulPersonID, 'tawasulINDescriptorID' => substr($status, 0, 3), 'tawasulAlertLevelID' => substr($status, 4, 3));
                        $sql = 'INSERT INTO tawasulINPersonDescriptor SET tawasulPersonID=:tawasulPersonID, tawasulINDescriptorID=:tawasulINDescriptorID, tawasulAlertLevelID=:tawasulAlertLevelID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }
                }

                //UPDATE IEP
                $strategies = $_POST['strategies'] ?? '';
                $targets = $_POST['targets'] ?? '';
                $notes = $_POST['notes'] ?? '';

                $customRequireFail = false;
                $fields = $container->get(CustomFieldHandler::class)->getFieldDataFromPOST('Individual Needs', [], $customRequireFail);

                $result = $container->get(INGateway::class)->selectBy(['tawasulPersonID' => $tawasulPersonID]);

                // Fetch old record for file comparison
                $recordCount = $result->rowCount();
                $oldINRecord = null;
                if (!empty($result)) {
                    $oldINRecord = $result->fetch();
                }

                if ($recordCount > 1 || $customRequireFail) {
                    $partialFail = true;
                } else {
                    try {
                        $data = ['strategies' => $strategies, 'targets' => $targets, 'notes' => $notes, 'fields' => $fields, 'tawasulPersonID' => $tawasulPersonID];
                        if ($recordCount == 1) {
                            $tawasulINID = $oldINRecord['tawasulINID'];
                            $sql = 'UPDATE tawasulIN SET strategies=:strategies, targets=:targets, notes=:notes, fields=:fields WHERE tawasulPersonID=:tawasulPersonID';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } else {
                            $sql = 'INSERT INTO tawasulIN SET tawasulPersonID=:tawasulPersonID, strategies=:strategies, targets=:targets, notes=:notes, fields=:fields';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                            $tawasulINID = $connection2->lastInsertID();
                        }
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }

                    // Manage custom field file uploads
                    if (!empty($fields)) {
                        $container->get(CustomFieldHandler::class)->manageCustomFieldFileUploads('Individual Needs', [], $fields, 'tawasulIN', $tawasulINID, $oldINRecord['fields'] ?? null);
                    }
                }

                //Scan through assistants
                $staff = array();
                if (isset($_POST['staff'])) {
                    $staff = $_POST['staff'] ?? [];
                }
                $comment = $_POST['comment'] ?? '';
                if (count($staff) > 0) {
                    foreach ($staff as $t) {
                        //Check to see if person is already registered as an assistant
                        try {
                            $resultGuest = $container->get(INAssistantGateway::class)->selectBy(['tawasulPersonIDAssistant' => $t, 'tawasulPersonIDStudent' => $tawasulPersonID]);

                        } catch (PDOException $e) {
                            $partialFail = true;
                        }
                        if ($resultGuest->rowCount() == 0) {
                            try {
                                $data = array('tawasulPersonIDAssistant' => $t, 'tawasulPersonIDStudent' => $tawasulPersonID, 'comment' => $comment);
                                $sql = 'INSERT INTO tawasulINAssistant SET tawasulPersonIDAssistant=:tawasulPersonIDAssistant, tawasulPersonIDStudent=:tawasulPersonIDStudent, comment=:comment';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        }
                    }
                }
            } elseif ($highestAction == 'Individual Needs Records_viewContribute') {
                //UPDATE IEP
                $strategies = $_POST['strategies'] ?? '';
                try {
                    $result = $container->get(INGateway::class)->selectBy(['tawasulPersonID' => $tawasulPersonID]);
                    
                } catch (PDOException $e) {
                    $partialFail = true;
                }
                if ($result->rowCount() > 1) {
                    $partialFail = true;
                } else {
                    try {
                        $data = array('strategies' => $strategies, 'tawasulPersonID' => $tawasulPersonID);
                        if ($result->rowCount() == 1) {
                            $sql = 'UPDATE tawasulIN SET strategies=:strategies WHERE tawasulPersonID=:tawasulPersonID';
                        } else {
                            $sql = 'INSERT INTO tawasulIN SET tawasulPersonID=:tawasulPersonID, strategies=:strategies';
                        }
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $partialFail = true;
                    }
                }
            }

            // ALERTS: possible change to IN alert status, recalculate alerts
            $container->get(Alert::class)->recalculateAlerts($tawasulPersonID);

            if (!$partialFail) {
                // Raise a new notification event
                $event = new NotificationEvent('Individual Needs', 'Updated Individual Needs');

                $staffName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);
                $studentName = Format::name('', $row['preferredName'], $row['surname'], 'Student', false);
                $actionLink = "/index.php?q=/modules/TawasulIndividualNeeds/in_edit.php&tawasulPersonID=$tawasulPersonID&search=";

                $event->setNotificationText(sprintf(__('%1$s has updated the individual needs record for %2$s.'), $staffName, $studentName));
                $event->setActionLink($actionLink);

                $event->addScope('tawasulPersonIDStudent', $tawasulPersonID);
                $event->addScope('tawasulYearGroupID', $row['tawasulYearGroupID']);

                $event->sendNotifications($pdo, $session);
            }

            //DEAL WITH OUTCOME
            if ($partialFail) {
                $URL .= '&return=warning1';
                header("Location: {$URL}");
            } else {
                $URL .= '&return=success0';
                header("Location: {$URL}");
            }
        }
    }
}
